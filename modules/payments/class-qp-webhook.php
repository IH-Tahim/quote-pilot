<?php
/**
 * Webhook handlers for Stripe and PayPal.
 *
 * Receives asynchronous notifications from payment gateways, verifies
 * their signatures cryptographically, and records confirmed payments.
 * Both handlers are idempotent — duplicate webhooks are safely ignored.
 *
 * SECURITY: These endpoints are nopriv (no WP login required, since
 * gateways POST from their servers). Authentication is performed
 * entirely through signature verification.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Webhook' ) ) :

	/**
	 * Class QP_Webhook
	 *
	 * Registers and handles incoming payment gateway webhooks.
	 */
	class QP_Webhook {

		/*--------------------------------------------------------------
		 * Registration
		 *------------------------------------------------------------*/

		/**
		 * Register webhook AJAX endpoints.
		 *
		 * These are nopriv because gateways POST from their own
		 * servers — no WordPress session exists. Authentication is
		 * handled via cryptographic signature verification.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'wp_ajax_nopriv_qp_stripe_webhook', array( __CLASS__, 'handle_stripe' ) );
			add_action( 'wp_ajax_nopriv_qp_paypal_webhook', array( __CLASS__, 'handle_paypal' ) );

			// Also register for logged-in users so admin-level testing works.
			add_action( 'wp_ajax_qp_stripe_webhook', array( __CLASS__, 'handle_stripe' ) );
			add_action( 'wp_ajax_qp_paypal_webhook', array( __CLASS__, 'handle_paypal' ) );
		}

		/*--------------------------------------------------------------
		 * Stripe webhook
		 *------------------------------------------------------------*/

		/**
		 * Handle a Stripe webhook event.
		 *
		 * Reads the raw body, verifies the Stripe-Signature header
		 * against the webhook secret using HMAC-SHA256, and processes
		 * checkout.session.completed events.
		 *
		 * @return void Sends HTTP response and exits.
		 */
		public static function handle_stripe() {
			$payload    = file_get_contents( 'php://input' );
			$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
			$secret     = QP_Helpers::get_setting( 'stripe_webhook_secret', '' );

			// Signature verification.
			if ( empty( $secret ) ) {
				status_header( 400 );
				echo wp_json_encode( array( 'error' => 'Webhook secret not configured.' ) );
				exit;
			}

			$verified = QP_Stripe::verify_webhook_signature( $payload, $sig_header, $secret );

			if ( is_wp_error( $verified ) ) {
				error_log( 'QuotePilot Stripe Webhook rejected: ' . $verified->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				status_header( 400 );
				echo wp_json_encode( array( 'error' => 'Invalid signature.' ) );
				exit;
			}

			// Parse the event.
			$event = json_decode( $payload, true );

			if ( empty( $event ) || ! isset( $event['type'] ) ) {
				status_header( 400 );
				echo wp_json_encode( array( 'error' => 'Invalid payload.' ) );
				exit;
			}

			// We only care about completed checkout sessions.
			if ( 'checkout.session.completed' === $event['type'] ) {
				self::process_stripe_checkout_completed( $event );
			}

			// Always return 200 to Stripe for recognised events.
			status_header( 200 );
			echo wp_json_encode( array( 'received' => true ) );
			exit;
		}

		/**
		 * Process a Stripe checkout.session.completed event.
		 *
		 * Extracts the booking_id from metadata and records the payment.
		 *
		 * @param array $event The parsed webhook event.
		 * @return void
		 */
		private static function process_stripe_checkout_completed( $event ) {
			$session = isset( $event['data']['object'] ) ? $event['data']['object'] : array();

			if ( empty( $session ) ) {
				return;
			}

			// Extract booking ID from metadata.
			$booking_id = 0;
			if ( isset( $session['metadata']['booking_id'] ) ) {
				$booking_id = absint( $session['metadata']['booking_id'] );
			}

			if ( ! $booking_id ) {
				error_log( 'QuotePilot Stripe Webhook: No booking_id in session metadata.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return;
			}

			// Only process paid sessions.
			$payment_status = isset( $session['payment_status'] ) ? $session['payment_status'] : '';
			if ( 'paid' !== $payment_status ) {
				return;
			}

			// Extract amount (Stripe returns in smallest unit).
			$amount_total = isset( $session['amount_total'] ) ? (int) $session['amount_total'] : 0;
			$currency     = isset( $session['currency'] ) ? strtolower( $session['currency'] ) : 'usd';

			// Convert back from smallest unit to decimal.
			$amount = self::from_smallest_unit( $amount_total, $currency );

			// Use the payment intent as the canonical txn ID.
			$txn_id = isset( $session['payment_intent'] ) ? $session['payment_intent'] : $session['id'];

			QP_Payments::record_payment( $booking_id, 'stripe', $txn_id, $amount );
		}

		/*--------------------------------------------------------------
		 * PayPal webhook
		 *------------------------------------------------------------*/

		/**
		 * Handle a PayPal webhook event.
		 *
		 * Reads the raw body, verifies the webhook signature via
		 * PayPal's verification API, and processes payment events.
		 *
		 * @return void Sends HTTP response and exits.
		 */
		public static function handle_paypal() {
			$raw_body   = file_get_contents( 'php://input' );
			$webhook_id = QP_Helpers::get_setting( 'paypal_webhook_id', '' );

			if ( empty( $webhook_id ) ) {
				status_header( 400 );
				echo wp_json_encode( array( 'error' => 'Webhook ID not configured.' ) );
				exit;
			}

			// Collect the PayPal-specific headers.
			$headers = self::get_paypal_headers();

			// Verify the webhook signature.
			$verified = QP_PayPal::verify_webhook_signature( $webhook_id, $headers, $raw_body );

			if ( is_wp_error( $verified ) ) {
				error_log( 'QuotePilot PayPal Webhook rejected: ' . $verified->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				status_header( 400 );
				echo wp_json_encode( array( 'error' => 'Invalid signature.' ) );
				exit;
			}

			// Parse the event.
			$event = json_decode( $raw_body, true );

			if ( empty( $event ) || ! isset( $event['event_type'] ) ) {
				status_header( 400 );
				echo wp_json_encode( array( 'error' => 'Invalid payload.' ) );
				exit;
			}

			$event_type = $event['event_type'];

			// Process relevant payment events.
			if ( 'PAYMENT.CAPTURE.COMPLETED' === $event_type ) {
				self::process_paypal_capture_completed( $event );
			} elseif ( 'CHECKOUT.ORDER.APPROVED' === $event_type ) {
				self::process_paypal_order_approved( $event );
			}

			// Always return 200 to PayPal for recognised events.
			status_header( 200 );
			echo wp_json_encode( array( 'received' => true ) );
			exit;
		}

		/**
		 * Process a PayPal PAYMENT.CAPTURE.COMPLETED event.
		 *
		 * This is the primary confirmation event — money has been captured.
		 *
		 * @param array $event The parsed webhook event.
		 * @return void
		 */
		private static function process_paypal_capture_completed( $event ) {
			$resource = isset( $event['resource'] ) ? $event['resource'] : array();

			if ( empty( $resource ) ) {
				return;
			}

			// Extract booking ID from custom_id.
			$booking_id = 0;
			if ( isset( $resource['custom_id'] ) ) {
				$booking_id = absint( $resource['custom_id'] );
			}

			if ( ! $booking_id ) {
				error_log( 'QuotePilot PayPal Webhook: No booking_id (custom_id) in capture resource.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return;
			}

			$capture_id = isset( $resource['id'] ) ? $resource['id'] : '';
			$amount     = isset( $resource['amount']['value'] ) ? (float) $resource['amount']['value'] : 0.0;
			$status     = isset( $resource['status'] ) ? $resource['status'] : '';

			if ( 'COMPLETED' !== $status || empty( $capture_id ) ) {
				return;
			}

			QP_Payments::record_payment( $booking_id, 'paypal', $capture_id, $amount );
		}

		/**
		 * Process a PayPal CHECKOUT.ORDER.APPROVED event.
		 *
		 * The order has been approved but not yet captured. We attempt
		 * to capture it server-side for reliability (in case the return
		 * URL capture failed).
		 *
		 * @param array $event The parsed webhook event.
		 * @return void
		 */
		private static function process_paypal_order_approved( $event ) {
			$resource = isset( $event['resource'] ) ? $event['resource'] : array();

			if ( empty( $resource ) ) {
				return;
			}

			$order_id = isset( $resource['id'] ) ? $resource['id'] : '';

			if ( empty( $order_id ) ) {
				return;
			}

			// Extract booking ID from custom_id in the purchase units.
			$booking_id = 0;
			if ( isset( $resource['purchase_units'][0]['custom_id'] ) ) {
				$booking_id = absint( $resource['purchase_units'][0]['custom_id'] );
			}

			if ( ! $booking_id ) {
				return;
			}

			// Check if already captured (idempotent).
			$booking = QP_Database::get_booking( $booking_id );
			if ( $booking && in_array( $booking->payment_status, array( 'paid', 'deposit_paid' ), true ) ) {
				return;
			}

			// Attempt capture.
			$capture = QP_PayPal::capture_order( $order_id );

			if ( ! is_wp_error( $capture ) && 'COMPLETED' === $capture['status'] ) {
				$amount     = isset( $capture['amount'] ) ? (float) $capture['amount'] : 0.0;
				$capture_id = isset( $capture['capture_id'] ) ? $capture['capture_id'] : $order_id;

				QP_Payments::record_payment( $booking_id, 'paypal', $capture_id, $amount );
			}
		}

		/*--------------------------------------------------------------
		 * Helpers
		 *------------------------------------------------------------*/

		/**
		 * Extract PayPal-specific headers from the request.
		 *
		 * PayPal sends uppercase-hyphenated header names. PHP converts
		 * them to HTTP_PAYPAL_xxx in $_SERVER.
		 *
		 * @return array Associative array of PayPal headers.
		 */
		private static function get_paypal_headers() {
			$map = array(
				'HTTP_PAYPAL_AUTH_ALGO'         => 'PAYPAL-AUTH-ALGO',
				'HTTP_PAYPAL_CERT_URL'          => 'PAYPAL-CERT-URL',
				'HTTP_PAYPAL_TRANSMISSION_ID'   => 'PAYPAL-TRANSMISSION-ID',
				'HTTP_PAYPAL_TRANSMISSION_SIG'  => 'PAYPAL-TRANSMISSION-SIG',
				'HTTP_PAYPAL_TRANSMISSION_TIME' => 'PAYPAL-TRANSMISSION-TIME',
			);

			$headers = array();

			foreach ( $map as $server_key => $header_name ) {
				if ( isset( $_SERVER[ $server_key ] ) ) {
					$headers[ $header_name ] = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
				}
			}

			return $headers;
		}

		/**
		 * Convert an amount from the smallest currency unit to decimal.
		 *
		 * @param int    $amount   Amount in smallest unit.
		 * @param string $currency ISO 4217 code (lowercase).
		 * @return float Decimal amount.
		 */
		private static function from_smallest_unit( $amount, $currency ) {
			$zero_decimal = array(
				'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw',
				'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf',
				'xof', 'xpf',
			);

			if ( in_array( strtolower( $currency ), $zero_decimal, true ) ) {
				return (float) $amount;
			}

			return round( $amount / 100, 2 );
		}
	}

endif;
