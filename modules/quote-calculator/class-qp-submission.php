<?php
/**
 * Quote submission handler.
 *
 * The AJAX endpoint that turns a submitted form into a stored booking.
 * This is a security-critical pipeline: it verifies the nonce first,
 * whitelists/sanitizes every field, validates consent, re-evaluates
 * conditional visibility, and — crucially — RECALCULATES the price on the
 * server. Any total sent by the browser is ignored entirely; only
 * QP_Price_Engine::calculate() decides what is stored.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The submission pipeline depends on these; require defensively so it
// works regardless of module load order. All use class_exists guards.
require_once QP_PLUGIN_DIR . 'modules/quote-calculator/class-qp-price-engine.php';
require_once QP_PLUGIN_DIR . 'modules/quote-calculator/class-qp-conditional.php';

if ( ! class_exists( 'QP_Submission' ) ) :

	/**
	 * Class QP_Submission
	 */
	class QP_Submission {

		/**
		 * Register the AJAX endpoints (logged-in + guest).
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'wp_ajax_qp_submit', array( __CLASS__, 'handle_submit' ) );
			add_action( 'wp_ajax_nopriv_qp_submit', array( __CLASS__, 'handle_submit' ) );
		}

		/**
		 * Handle a quote submission end-to-end.
		 *
		 * @return void Sends a JSON response and exits.
		 */
		public static function handle_submit() {
			/*----------------------------------------------------------
			 * 1. Nonce check FIRST — no heavy work before this.
			 *--------------------------------------------------------*/
			if ( ! QP_Helpers::verify_request( QP_Quote::NONCE_ACTION ) ) {
				wp_send_json_error(
					array(
						'code'    => 'bad_nonce',
						'message' => __( 'Your session has expired. Please refresh the page and try again.', 'quote-pilot' ),
					),
					403
				);
			}

			/*----------------------------------------------------------
			 * 2. Whitelist + sanitize via the canonical field contract.
			 *--------------------------------------------------------*/
			$input = QP_Helpers::sanitize_quote_input( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.

			/*----------------------------------------------------------
			 * 2b. Validate the selected service.
			 *--------------------------------------------------------*/
			if ( ! class_exists( 'QP_Services_Meta' ) ) {
				wp_send_json_error(
					array(
						'code'    => 'services_unavailable',
						'message' => __( 'Quotes are temporarily unavailable. Please try again later.', 'quote-pilot' ),
					),
					500
				);
			}

			$service_id = isset( $input['service_id'] ) ? (int) $input['service_id'] : 0;
			$service    = $service_id ? get_post( $service_id ) : null;

			if ( ! $service || 'qp_service' !== $service->post_type || 'publish' !== $service->post_status ) {
				wp_send_json_error(
					array(
						'code'    => 'invalid_service',
						'message' => __( 'Please choose a valid service.', 'quote-pilot' ),
					),
					400
				);
			}

			$service_pricing = QP_Services_Meta::get_pricing( $service_id );
			if ( empty( $service_pricing['service_active'] ) ) {
				wp_send_json_error(
					array(
						'code'    => 'service_inactive',
						'message' => __( 'The selected service is not currently available.', 'quote-pilot' ),
					),
					400
				);
			}

			/*----------------------------------------------------------
			 * 2c. Require a usable contact email (the guest→account key).
			 *--------------------------------------------------------*/
			if ( empty( $input['customer_email'] ) || ! is_email( $input['customer_email'] ) ) {
				wp_send_json_error(
					array(
						'code'    => 'invalid_email',
						'message' => __( 'Please enter a valid email address.', 'quote-pilot' ),
						'fields'  => array( 'customer_email' ),
					),
					400
				);
			}

			if ( empty( $input['customer_name'] ) ) {
				wp_send_json_error(
					array(
						'code'    => 'missing_name',
						'message' => __( 'Please enter your name.', 'quote-pilot' ),
						'fields'  => array( 'customer_name' ),
					),
					400
				);
			}

			/*----------------------------------------------------------
			 * 3. Consent: required boxes must be ticked. Build proof.
			 *--------------------------------------------------------*/
			$consent_config = self::get_consent_config();
			$missing        = self::validate_consent( $input, $consent_config );

			if ( ! empty( $missing ) ) {
				wp_send_json_error(
					array(
						'code'    => 'consent_required',
						'message' => __( 'Please agree to the required terms before submitting.', 'quote-pilot' ),
						'fields'  => $missing,
					),
					400
				);
			}

			$consent_data = self::build_consent_data( $input, $consent_config );

			/*----------------------------------------------------------
			 * 4. Conditional visibility (server-side authority).
			 *--------------------------------------------------------*/
			$rules   = self::get_conditional_rules( $service_id );
			$visible = QP_Conditional::evaluate( $rules, $input );

			/*----------------------------------------------------------
			 * 5. RECALCULATE the price. The browser total is never used.
			 *--------------------------------------------------------*/
			$price = QP_Price_Engine::calculate( $input, $service_id, $visible );

			// Reject a bad coupon so the customer can correct it.
			if ( ! empty( $price['invalid_coupon'] ) ) {
				wp_send_json_error(
					array(
						'code'         => 'invalid_coupon',
						'message'      => __( 'The coupon code you entered is not valid.', 'quote-pilot' ),
						'coupon_error' => isset( $price['coupon_error'] ) ? $price['coupon_error'] : 'invalid',
						'fields'       => array( 'coupon_code' ),
					),
					400
				);
			}

			/*----------------------------------------------------------
			 * 6. Persist the booking with the recalculated figures.
			 *--------------------------------------------------------*/
			$user_id      = is_user_logged_in() ? get_current_user_id() : 0;
			$payment_mode = self::resolve_payment_mode( $service_id );

			$booking_data = array(
				'user_id'          => $user_id,
				'customer_email'   => $input['customer_email'],
				'customer_name'    => $input['customer_name'],
				'customer_phone'   => isset( $input['customer_phone'] ) ? $input['customer_phone'] : '',
				'customer_address' => isset( $input['customer_address'] ) ? $input['customer_address'] : '',
				'service_id'       => $service_id,
				'preferred_date'   => ! empty( $input['preferred_date'] ) ? $input['preferred_date'] : null,
				'preferred_time'   => isset( $input['preferred_time'] ) ? $input['preferred_time'] : '',
				'base_price'       => (float) $price['base'],
				'surcharge_total'  => (float) $price['surcharge_total'],
				'discount_total'   => (float) $price['discount_total'],
				'tax_rate'         => (float) $price['tax_rate'],
				'tax_total'        => (float) $price['tax_total'],
				'total_price'      => (float) $price['total'],
				'payment_mode'     => $payment_mode,
				'payment_status'   => 'unpaid',
				'gateway'          => 'none',
				'booking_status'   => 'pending',
				'consent_data'     => wp_json_encode( $consent_data ),
			);

			$booking_id = QP_Database::insert_booking( $booking_data );

			if ( ! $booking_id ) {
				wp_send_json_error(
					array(
						'code'    => 'save_failed',
						'message' => __( 'We could not save your quote. Please try again.', 'quote-pilot' ),
					),
					500
				);
			}

			QP_Database::insert_booking_items( $booking_id, $price['items'] );

			/*----------------------------------------------------------
			 * 7. Increment coupon usage only on a successful booking.
			 *--------------------------------------------------------*/
			if ( ! empty( $input['coupon_code'] ) && $price['discount_total'] > 0 ) {
				$coupon = QP_Database::get_coupon( $input['coupon_code'] );
				if ( $coupon && isset( $coupon->id ) ) {
					QP_Database::increment_coupon_usage( (int) $coupon->id );
				}
			}

			/*----------------------------------------------------------
			 * 8. Account opt-in (guests only; logged-in already attached).
			 *--------------------------------------------------------*/
			if ( ! $user_id && ! empty( $input['create_account'] ) && '' !== (string) $input['password'] ) {
				$new_user_id = self::maybe_create_account( $input );
				if ( $new_user_id > 0 ) {
					$user_id = $new_user_id;
					QP_Database::update_booking( $booking_id, array( 'user_id' => $user_id ) );
				}
			}

			/*----------------------------------------------------------
			 * 9. Integration seam: notifications / payments / recovery
			 *    all hook here. Designed now even though those come later.
			 *--------------------------------------------------------*/
			$booking_row = QP_Database::get_booking( $booking_id );

			/**
			 * Fires once a booking has been created and stored.
			 *
			 * @param int    $booking_id  The new booking ID.
			 * @param object $booking_row The stored booking row.
			 * @param array  $input       The sanitized form input.
			 */
			do_action( 'qp_booking_created', $booking_id, $booking_row, $input );

			/*----------------------------------------------------------
			 * 10. Success — return data for the confirmation screen.
			 *--------------------------------------------------------*/
			$amount_due = self::amount_due( (float) $price['total'], $payment_mode );

			wp_send_json_success(
				array(
					'booking_id' => (int) $booking_id,
					'breakdown'  => array(
						'items'           => $price['items'],
						'base'            => (float) $price['base'],
						'surcharge_total' => (float) $price['surcharge_total'],
						'discount_total'  => (float) $price['discount_total'],
						'tax_rate'        => (float) $price['tax_rate'],
						'tax_total'       => (float) $price['tax_total'],
						'total'           => (float) $price['total'],
						'total_formatted' => QP_Helpers::format_money( $price['total'] ),
					),
					'payment'    => array(
						'mode'       => $payment_mode,
						'status'     => 'unpaid',
						'amount_due' => $amount_due,
						'next_step'  => ( $amount_due > 0 ) ? 'payment' : 'confirmation',
					),
				)
			);
		}

		/*--------------------------------------------------------------
		 * Helpers
		 *------------------------------------------------------------*/

		/**
		 * Resolve the conditional rules for a service.
		 *
		 * No admin rules UI exists yet, so this defaults to an empty set
		 * (every field visible). The filter is the seam a future rules
		 * editor plugs into.
		 *
		 * @param int $service_id The service ID.
		 * @return array
		 */
		private static function get_conditional_rules( $service_id ) {
			/**
			 * Filter the conditional show/hide rules for a service.
			 *
			 * @param array $rules      Empty by default.
			 * @param int   $service_id The service ID.
			 */
			$rules = apply_filters( 'qp_conditional_rules', array(), $service_id );

			return is_array( $rules ) ? $rules : array();
		}

		/**
		 * Resolve the consent configuration (mirrors QP_Quote default).
		 *
		 * @return array
		 */
		private static function get_consent_config() {
			$default = array(
				'mode'  => 'split',
				'boxes' => array(
					'consent_privacy'   => array( 'required' => true ),
					'consent_terms'     => array( 'required' => true ),
					'consent_marketing' => array( 'required' => false ),
				),
			);

			$config = QP_Helpers::get_setting( 'consent_config', array() );

			if ( ! is_array( $config ) || empty( $config ) ) {
				return $default;
			}

			return wp_parse_args( $config, $default );
		}

		/**
		 * Validate required consent boxes.
		 *
		 * @param array $input  Sanitized input.
		 * @param array $config Consent configuration.
		 * @return array List of missing required field keys (empty = OK).
		 */
		private static function validate_consent( $input, $config ) {
			$missing = array();

			if ( isset( $config['mode'] ) && 'merged' === $config['mode'] ) {
				if ( empty( $input['consent_all'] ) ) {
					$missing[] = 'consent_all';
				}
				return $missing;
			}

			$boxes = isset( $config['boxes'] ) && is_array( $config['boxes'] ) ? $config['boxes'] : array();
			foreach ( $boxes as $key => $box ) {
				if ( ! empty( $box['required'] ) && empty( $input[ $key ] ) ) {
					$missing[] = $key;
				}
			}

			return $missing;
		}

		/**
		 * Build the consent proof payload (boxes ticked + timestamp + IP).
		 *
		 * @param array $input  Sanitized input.
		 * @param array $config Consent configuration.
		 * @return array
		 */
		private static function build_consent_data( $input, $config ) {
			$ticked = array();

			foreach ( array_keys( QP_Fields::by_pricing_role( 'consent' ) ) as $consent_key ) {
				$ticked[ $consent_key ] = ! empty( $input[ $consent_key ] ) ? 1 : 0;
			}

			return array(
				'mode'      => isset( $config['mode'] ) ? $config['mode'] : 'split',
				'boxes'     => $ticked,
				'timestamp' => current_time( 'mysql' ),
				'ip'        => self::get_client_ip(),
			);
		}

		/**
		 * Best-effort client IP for consent proof.
		 *
		 * Uses REMOTE_ADDR only — proxy headers are spoofable and not
		 * trusted for a legal record.
		 *
		 * @return string
		 */
		private static function get_client_ip() {
			if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
				return '';
			}

			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			return ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : '';
		}

		/**
		 * Resolve the payment mode for a booking.
		 *
		 * @param int $service_id The service ID.
		 * @return string One of full_advance|half_advance|pay_after|none.
		 */
		private static function resolve_payment_mode( $service_id ) {
			$valid = array( 'full_advance', 'half_advance', 'pay_after', 'none' );

			$mode = QP_Helpers::get_setting( 'default_payment_mode', 'none' );

			/**
			 * Filter the payment mode chosen for a new booking.
			 *
			 * @param string $mode       The resolved payment mode.
			 * @param int    $service_id The service ID.
			 */
			$mode = apply_filters( 'qp_booking_payment_mode', $mode, $service_id );

			return in_array( $mode, $valid, true ) ? $mode : 'none';
		}

		/**
		 * Compute the amount due now based on payment mode.
		 *
		 * @param float  $total Booking total.
		 * @param string $mode  Payment mode.
		 * @return float
		 */
		private static function amount_due( $total, $mode ) {
			switch ( $mode ) {
				case 'full_advance':
					return round( $total, 2 );
				case 'half_advance':
					return round( $total / 2, 2 );
				default:
					return 0.0;
			}
		}

		/**
		 * Create a qp_customer account from the submission, if possible.
		 *
		 * Failures (existing email, insert error) never abort the booking:
		 * the booking simply stays guest (user_id 0) and is backfilled on
		 * the customer's later registration.
		 *
		 * @param array $input Sanitized input.
		 * @return int New user ID, or 0 when no account was created.
		 */
		private static function maybe_create_account( $input ) {
			$email = $input['customer_email'];

			// Existing account → don't create; leave for email-keyed backfill.
			if ( email_exists( $email ) ) {
				return 0;
			}

			$login = $email;
			if ( username_exists( $login ) ) {
				$login = $email . '-' . wp_generate_password( 4, false );
			}

			$new_user_id = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $email,
					'user_pass'    => (string) $input['password'],
					'display_name' => $input['customer_name'],
					'first_name'   => $input['customer_name'],
					'role'         => 'qp_customer',
				)
			);

			if ( is_wp_error( $new_user_id ) ) {
				return 0;
			}

			return (int) $new_user_id;
		}
	}

endif;
