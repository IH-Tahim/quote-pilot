<?php
/**
 * Payment router — the single entry point for the payments module.
 *
 * Decides whether a booking needs a gateway step, delegates to the
 * correct gateway class, and provides an idempotent record_payment()
 * helper that both return-URL handlers and webhooks call.
 *
 * If no gateway API keys are configured, bookings complete normally
 * with payment_status='unpaid'. Payment is NEVER mandatory.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-qp-stripe.php';
require_once __DIR__ . '/class-qp-paypal.php';
require_once __DIR__ . '/class-qp-webhook.php';

if ( ! class_exists( 'QP_Payments' ) ) :

	/**
	 * Class QP_Payments
	 *
	 * Central payment orchestrator.
	 */
	class QP_Payments {

		/*--------------------------------------------------------------
		 * Module bootstrap
		 *------------------------------------------------------------*/

		/**
		 * Initialise the payments module.
		 *
		 * Called from QP_Modules::boot() when payments are enabled.
		 *
		 * @return void
		 */
		public static function init() {
			// Hook into the booking pipeline (after notifications at 10).
			add_action( 'qp_booking_created', array( __CLASS__, 'attach_payment_context' ), 20, 3 );

			// AJAX: front-end requests a payment session after booking is created.
			add_action( 'wp_ajax_qp_create_payment_session', array( __CLASS__, 'ajax_create_session' ) );
			add_action( 'wp_ajax_nopriv_qp_create_payment_session', array( __CLASS__, 'ajax_create_session' ) );

			// AJAX: gateway return URL handler (success / cancel).
			add_action( 'wp_ajax_qp_payment_return', array( __CLASS__, 'ajax_payment_return' ) );
			add_action( 'wp_ajax_nopriv_qp_payment_return', array( __CLASS__, 'ajax_payment_return' ) );

			// Register webhook listeners.
			QP_Webhook::register();
		}

		/*--------------------------------------------------------------
		 * Gateway detection
		 *------------------------------------------------------------*/

		/**
		 * Return the first active gateway with configured credentials.
		 *
		 * Priority: Stripe > PayPal. Returns 'none' when no keys exist.
		 *
		 * @return string 'stripe'|'paypal'|'none'
		 */
		public static function get_active_gateway() {
			$stripe_key = QP_Helpers::get_setting( 'stripe_secret_key', '' );
			if ( ! empty( $stripe_key ) ) {
				return 'stripe';
			}

			$paypal_id     = QP_Helpers::get_setting( 'paypal_client_id', '' );
			$paypal_secret = QP_Helpers::get_setting( 'paypal_secret', '' );
			if ( ! empty( $paypal_id ) && ! empty( $paypal_secret ) ) {
				return 'paypal';
			}

			return 'none';
		}

		/**
		 * Check which gateways are available (have credentials set).
		 *
		 * @return array List of available gateway slugs.
		 */
		public static function get_available_gateways() {
			$gateways = array();

			if ( ! empty( QP_Helpers::get_setting( 'stripe_secret_key', '' ) ) ) {
				$gateways[] = 'stripe';
			}

			$paypal_id     = QP_Helpers::get_setting( 'paypal_client_id', '' );
			$paypal_secret = QP_Helpers::get_setting( 'paypal_secret', '' );
			if ( ! empty( $paypal_id ) && ! empty( $paypal_secret ) ) {
				$gateways[] = 'paypal';
			}

			return $gateways;
		}

		/*--------------------------------------------------------------
		 * Amount computation
		 *------------------------------------------------------------*/

		/**
		 * Compute the amount due now based on payment mode.
		 *
		 * @param float  $total Booking total price.
		 * @param string $mode  Payment mode (full_advance|half_advance|pay_after|none).
		 * @return float
		 */
		public static function get_amount_due( $total, $mode ) {
			$total = (float) $total;

			switch ( $mode ) {
				case 'full_advance':
					return round( $total, 2 );

				case 'half_advance':
					return round( $total / 2, 2 );

				case 'pay_after':
				case 'none':
				default:
					return 0.0;
			}
		}

		/**
		 * Determine whether a booking requires a payment gateway step.
		 *
		 * @param object $booking_row The booking database row.
		 * @return bool
		 */
		public static function needs_payment( $booking_row ) {
			// Already paid or processing — nothing to do.
			if ( in_array( $booking_row->payment_status, array( 'paid', 'deposit_paid' ), true ) ) {
				return false;
			}

			$amount_due = self::get_amount_due(
				(float) $booking_row->total_price,
				$booking_row->payment_mode
			);

			if ( $amount_due <= 0 ) {
				return false;
			}

			// No gateway configured.
			if ( 'none' === self::get_active_gateway() ) {
				return false;
			}

			return true;
		}

		/*--------------------------------------------------------------
		 * Booking-created hook
		 *------------------------------------------------------------*/

		/**
		 * Attach payment context to the booking after creation.
		 *
		 * Hooked to qp_booking_created at priority 20. Stores a transient
		 * with payment info that the front-end AJAX can retrieve.
		 *
		 * @param int    $booking_id  Booking ID.
		 * @param object $booking_row The stored booking row.
		 * @param array  $input       Sanitised form input.
		 * @return void
		 */
		public static function attach_payment_context( $booking_id, $booking_row, $input ) {
			if ( ! self::needs_payment( $booking_row ) ) {
				return;
			}

			$amount_due = self::get_amount_due(
				(float) $booking_row->total_price,
				$booking_row->payment_mode
			);

			$context = array(
				'booking_id'         => (int) $booking_id,
				'amount_due'         => $amount_due,
				'currency'           => strtolower( QP_Helpers::get_setting( 'currency_symbol', '$' ) === '$' ? 'usd' : 'aud' ),
				'available_gateways' => self::get_available_gateways(),
				'payment_mode'       => $booking_row->payment_mode,
			);

			// Store for 30 minutes — the front-end retrieves this via AJAX.
			set_transient( 'qp_payment_ctx_' . $booking_id, $context, 30 * MINUTE_IN_SECONDS );
		}

		/*--------------------------------------------------------------
		 * AJAX: Create payment session
		 *------------------------------------------------------------*/

		/**
		 * Handle the AJAX request to create a payment session.
		 *
		 * Called by the front-end after a booking is created and the
		 * response indicates payment is required.
		 *
		 * @return void
		 */
		public static function ajax_create_session() {
			// Verify nonce — uses the same quote form nonce.
			if ( ! QP_Helpers::verify_request( 'qp_quote_form' ) ) {
				wp_send_json_error(
					array(
						'code'    => 'bad_nonce',
						'message' => __( 'Session expired. Please refresh and try again.', 'quote-pilot' ),
					),
					403
				);
			}

			$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$gateway    = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! $booking_id ) {
				wp_send_json_error(
					array(
						'code'    => 'missing_booking',
						'message' => __( 'Booking ID is required.', 'quote-pilot' ),
					),
					400
				);
			}

			// Validate booking exists and is unpaid.
			$booking = QP_Database::get_booking( $booking_id );

			if ( ! $booking ) {
				wp_send_json_error(
					array(
						'code'    => 'booking_not_found',
						'message' => __( 'Booking not found.', 'quote-pilot' ),
					),
					404
				);
			}

			if ( in_array( $booking->payment_status, array( 'paid', 'deposit_paid' ), true ) ) {
				wp_send_json_error(
					array(
						'code'    => 'already_paid',
						'message' => __( 'This booking has already been paid.', 'quote-pilot' ),
					),
					400
				);
			}

			// Resolve gateway.
			$available = self::get_available_gateways();
			if ( empty( $gateway ) || ! in_array( $gateway, $available, true ) ) {
				$gateway = self::get_active_gateway();
			}

			if ( 'none' === $gateway ) {
				wp_send_json_error(
					array(
						'code'    => 'no_gateway',
						'message' => __( 'No payment gateway is configured. Please contact the site owner.', 'quote-pilot' ),
					),
					500
				);
			}

			$amount_due = self::get_amount_due(
				(float) $booking->total_price,
				$booking->payment_mode
			);

			if ( $amount_due <= 0 ) {
				wp_send_json_error(
					array(
						'code'    => 'no_amount_due',
						'message' => __( 'No payment is required for this booking.', 'quote-pilot' ),
					),
					400
				);
			}

			// Resolve currency code from settings.
			$currency = self::get_currency_code();

			// Build return/cancel URLs.
			$return_url = add_query_arg(
				array(
					'action'     => 'qp_payment_return',
					'booking_id' => $booking_id,
					'status'     => 'success',
					'nonce'      => wp_create_nonce( 'qp_payment_return' ),
				),
				admin_url( 'admin-ajax.php' )
			);

			$cancel_url = add_query_arg(
				array(
					'action'     => 'qp_payment_return',
					'booking_id' => $booking_id,
					'status'     => 'cancelled',
					'nonce'      => wp_create_nonce( 'qp_payment_return' ),
				),
				admin_url( 'admin-ajax.php' )
			);

			// Delegate to gateway.
			$result = null;

			if ( 'stripe' === $gateway ) {
				$result = QP_Stripe::create_session( $booking_id, $amount_due, $currency, $return_url, $cancel_url );
			} elseif ( 'paypal' === $gateway ) {
				$result = QP_PayPal::create_order( $booking_id, $amount_due, $currency, $return_url, $cancel_url );
			}

			if ( is_wp_error( $result ) ) {
				wp_send_json_error(
					array(
						'code'    => 'gateway_error',
						'message' => $result->get_error_message(),
					),
					500
				);
			}

			wp_send_json_success(
				array(
					'gateway'      => $gateway,
					'redirect_url' => $result['redirect_url'],
					'session_id'   => isset( $result['session_id'] ) ? $result['session_id'] : '',
				)
			);
		}

		/*--------------------------------------------------------------
		 * AJAX: Payment return handler
		 *------------------------------------------------------------*/

		/**
		 * Handle the return redirect from a payment gateway.
		 *
		 * Stripe and PayPal redirect the customer here after checkout.
		 * For PayPal, we capture the order. For Stripe, the webhook
		 * handles the actual payment confirmation — this just shows
		 * the customer a status page.
		 *
		 * @return void
		 */
		public static function ajax_payment_return() {
			$nonce      = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( ! wp_verify_nonce( $nonce, 'qp_payment_return' ) || ! $booking_id ) {
				wp_die(
					esc_html__( 'Invalid payment return. Please contact support.', 'quote-pilot' ),
					esc_html__( 'Payment Error', 'quote-pilot' ),
					array( 'response' => 403 )
				);
			}

			$booking = QP_Database::get_booking( $booking_id );

			if ( ! $booking ) {
				wp_die(
					esc_html__( 'Booking not found.', 'quote-pilot' ),
					esc_html__( 'Payment Error', 'quote-pilot' ),
					array( 'response' => 404 )
				);
			}

			// PayPal: capture the order on return.
			if ( 'cancelled' !== $status && 'paypal' === $booking->gateway ) {
				$paypal_order_id = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( ! empty( $paypal_order_id ) ) {
					$capture = QP_PayPal::capture_order( $paypal_order_id );

					if ( ! is_wp_error( $capture ) && 'COMPLETED' === $capture['status'] ) {
						$amount = isset( $capture['amount'] ) ? (float) $capture['amount'] : 0.0;
						$txn_id = isset( $capture['capture_id'] ) ? $capture['capture_id'] : $paypal_order_id;

						self::record_payment( $booking_id, 'paypal', $txn_id, $amount );
					}
				}
			}

			// Redirect to the site home with a query arg the front-end can read.
			$redirect = add_query_arg(
				array(
					'qp_payment'  => ( 'cancelled' === $status ) ? 'cancelled' : 'processing',
					'qp_booking'  => $booking_id,
				),
				home_url( '/' )
			);

			wp_safe_redirect( $redirect );
			exit;
		}

		/*--------------------------------------------------------------
		 * Idempotent payment recording
		 *------------------------------------------------------------*/

		/**
		 * Record a payment against a booking.
		 *
		 * Idempotent: if gateway_txn_id already matches, no duplicate
		 * update occurs. Called by both return handlers and webhooks.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param string $gateway    Gateway slug ('stripe'|'paypal').
		 * @param string $txn_id     Gateway transaction/session ID.
		 * @param float  $amount     Amount captured.
		 * @return bool True if updated, false if skipped (idempotent).
		 */
		public static function record_payment( $booking_id, $gateway, $txn_id, $amount ) {
			$booking = QP_Database::get_booking( (int) $booking_id );

			if ( ! $booking ) {
				return false;
			}

			// Idempotency guard: same txn already recorded.
			if ( ! empty( $booking->gateway_txn_id ) && $booking->gateway_txn_id === $txn_id ) {
				return false;
			}

			// Determine payment status based on mode and amount.
			$total_price = (float) $booking->total_price;
			$amount      = round( (float) $amount, 2 );

			if ( 'half_advance' === $booking->payment_mode && $amount < $total_price ) {
				$payment_status = 'deposit_paid';
			} else {
				$payment_status = 'paid';
			}

			$updated = QP_Database::update_booking(
				(int) $booking_id,
				array(
					'payment_status' => $payment_status,
					'amount_paid'    => $amount,
					'gateway'        => sanitize_text_field( $gateway ),
					'gateway_txn_id' => sanitize_text_field( $txn_id ),
				)
			);

			if ( $updated ) {
				/**
				 * Fires after a payment has been successfully recorded.
				 *
				 * @param int    $booking_id The booking ID.
				 * @param string $txn_id     The gateway transaction ID.
				 * @param float  $amount     The amount captured.
				 * @param string $gateway    The gateway used.
				 */
				do_action( 'qp_payment_completed', $booking_id, $txn_id, $amount, $gateway );
			}

			return $updated;
		}

		/*--------------------------------------------------------------
		 * Helpers
		 *------------------------------------------------------------*/

		/**
		 * Resolve the ISO 4217 currency code from settings.
		 *
		 * Maps common currency symbols to codes. Falls back to 'usd'.
		 *
		 * @return string Lowercase 3-letter currency code.
		 */
		public static function get_currency_code() {
			$currency_code = QP_Helpers::get_setting( 'currency_code', '' );
			if ( ! empty( $currency_code ) ) {
				return strtolower( sanitize_text_field( $currency_code ) );
			}

			// Fallback: map symbol to code.
			$symbol = QP_Helpers::get_setting( 'currency_symbol', '$' );
			$map    = array(
				'$'  => 'usd',
				'£'  => 'gbp',
				'€'  => 'eur',
				'¥'  => 'jpy',
				'A$' => 'aud',
				'C$' => 'cad',
				'₹'  => 'inr',
				'R'  => 'zar',
			);

			return isset( $map[ $symbol ] ) ? $map[ $symbol ] : 'usd';
		}

		/**
		 * Normalise payment mode aliases from the settings page.
		 *
		 * The admin settings page uses hyphenated values while the
		 * submission handler uses underscored constants. This maps
		 * both conventions to the canonical internal form.
		 *
		 * @param string $mode Raw payment mode from settings.
		 * @return string Canonical mode.
		 */
		public static function normalise_payment_mode( $mode ) {
			$aliases = array(
				'pay-after'    => 'pay_after',
				'deposit-half' => 'half_advance',
				'deposit-full' => 'full_advance',
			);

			if ( isset( $aliases[ $mode ] ) ) {
				return $aliases[ $mode ];
			}

			$valid = array( 'full_advance', 'half_advance', 'pay_after', 'none' );

			return in_array( $mode, $valid, true ) ? $mode : 'none';
		}
	}

endif;
