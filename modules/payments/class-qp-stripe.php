<?php
/**
 * Stripe Checkout Session gateway.
 *
 * Creates a Stripe Checkout Session using direct REST API calls —
 * no Stripe PHP SDK required. The customer is redirected to Stripe's
 * hosted checkout page, avoiding PCI compliance burdens entirely.
 *
 * Secret keys are loaded server-side only and NEVER exposed to the
 * browser or logged.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Stripe' ) ) :

	/**
	 * Class QP_Stripe
	 *
	 * Stripe Checkout Session integration via REST API.
	 */
	class QP_Stripe {

		/**
		 * Stripe API base URL.
		 *
		 * @var string
		 */
		const API_BASE = 'https://api.stripe.com/v1';

		/*--------------------------------------------------------------
		 * Checkout Session creation
		 *------------------------------------------------------------*/

		/**
		 * Create a Stripe Checkout Session for a booking.
		 *
		 * Uses POST /v1/checkout/sessions with Basic Auth. Returns
		 * the hosted checkout URL for client-side redirect.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param float  $amount     Amount to charge (already computed).
		 * @param string $currency   ISO 4217 currency code (lowercase).
		 * @param string $return_url URL to redirect on success.
		 * @param string $cancel_url URL to redirect on cancel.
		 * @return array|WP_Error Array with 'redirect_url' and 'session_id', or WP_Error.
		 */
		public static function create_session( $booking_id, $amount, $currency, $return_url, $cancel_url ) {
			$secret_key = QP_Helpers::get_setting( 'stripe_secret_key', '' );

			if ( empty( $secret_key ) ) {
				return new WP_Error(
					'qp_stripe_no_key',
					__( 'Stripe secret key is not configured.', 'quote-pilot' )
				);
			}

			// Stripe expects amounts in the smallest currency unit (cents).
			$amount_cents = self::to_smallest_unit( $amount, $currency );

			$body = array(
				'mode'                        => 'payment',
				'success_url'                 => $return_url . '&stripe_session={CHECKOUT_SESSION_ID}',
				'cancel_url'                  => $cancel_url,
				'line_items[0][price_data][currency]'     => $currency,
				'line_items[0][price_data][product_data][name]' => sprintf(
					/* translators: %d: booking ID */
					__( 'QuotePilot Booking #%d', 'quote-pilot' ),
					$booking_id
				),
				'line_items[0][price_data][unit_amount]'  => $amount_cents,
				'line_items[0][quantity]'                  => 1,
				'metadata[booking_id]'                     => $booking_id,
				'metadata[source]'                         => 'quotepilot',
				'payment_intent_data[metadata][booking_id]' => $booking_id,
			);

			$response = wp_remote_post(
				self::API_BASE . '/checkout/sessions',
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $secret_key . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					),
					'body'    => $body,
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'QuotePilot Stripe Error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_stripe_request_failed',
					__( 'Unable to connect to the payment gateway. Please try again.', 'quote-pilot' )
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 || empty( $body['url'] ) ) {
				$error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown error';
				error_log( 'QuotePilot Stripe API Error (' . $code . '): ' . $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_stripe_api_error',
					__( 'Payment gateway returned an error. Please try again or contact support.', 'quote-pilot' )
				);
			}

			// Store the Stripe session ID on the booking for later verification.
			QP_Database::update_booking(
				(int) $booking_id,
				array(
					'gateway'        => 'stripe',
					'gateway_txn_id' => sanitize_text_field( $body['id'] ),
				)
			);

			return array(
				'redirect_url' => $body['url'],
				'session_id'   => $body['id'],
			);
		}

		/*--------------------------------------------------------------
		 * Webhook signature verification
		 *------------------------------------------------------------*/

		/**
		 * Verify a Stripe webhook signature.
		 *
		 * Implements the Stripe v1 signing scheme: splits the header
		 * into timestamp and signature(s), computes the expected HMAC,
		 * and performs a timing-safe comparison.
		 *
		 * @param string $payload   Raw request body.
		 * @param string $sig_header The Stripe-Signature header value.
		 * @param string $secret    The webhook endpoint secret (whsec_xxx).
		 * @param int    $tolerance Max allowed age in seconds (default 300 = 5min).
		 * @return bool|WP_Error True if valid, WP_Error if not.
		 */
		public static function verify_webhook_signature( $payload, $sig_header, $secret, $tolerance = 300 ) {
			if ( empty( $sig_header ) || empty( $secret ) ) {
				return new WP_Error( 'qp_stripe_webhook_no_sig', 'Missing signature or secret.' );
			}

			// Parse the header: "t=timestamp,v1=sig1,v1=sig2,...".
			$parts     = explode( ',', $sig_header );
			$timestamp = null;
			$signatures = array();

			foreach ( $parts as $part ) {
				$kv = explode( '=', $part, 2 );
				if ( count( $kv ) !== 2 ) {
					continue;
				}

				if ( 't' === $kv[0] ) {
					$timestamp = (int) $kv[1];
				} elseif ( 'v1' === $kv[0] ) {
					$signatures[] = $kv[1];
				}
			}

			if ( null === $timestamp || empty( $signatures ) ) {
				return new WP_Error( 'qp_stripe_webhook_bad_header', 'Malformed signature header.' );
			}

			// Check timestamp tolerance.
			if ( abs( time() - $timestamp ) > $tolerance ) {
				return new WP_Error( 'qp_stripe_webhook_stale', 'Webhook timestamp outside tolerance.' );
			}

			// Compute expected signature.
			$signed_payload    = $timestamp . '.' . $payload;
			$expected_sig      = hash_hmac( 'sha256', $signed_payload, $secret );

			// Timing-safe comparison against all v1 signatures.
			foreach ( $signatures as $sig ) {
				if ( hash_equals( $expected_sig, $sig ) ) {
					return true;
				}
			}

			return new WP_Error( 'qp_stripe_webhook_invalid', 'Signature verification failed.' );
		}

		/*--------------------------------------------------------------
		 * Retrieve session details
		 *------------------------------------------------------------*/

		/**
		 * Retrieve a Checkout Session from Stripe (for verification).
		 *
		 * @param string $session_id The Checkout Session ID.
		 * @return array|WP_Error Session data array, or WP_Error.
		 */
		public static function retrieve_session( $session_id ) {
			$secret_key = QP_Helpers::get_setting( 'stripe_secret_key', '' );

			if ( empty( $secret_key ) ) {
				return new WP_Error( 'qp_stripe_no_key', 'Stripe key not configured.' );
			}

			$response = wp_remote_get(
				self::API_BASE . '/checkout/sessions/' . urlencode( $session_id ),
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $secret_key . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					),
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$code = wp_remote_retrieve_response_code( $response );

			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error( 'qp_stripe_session_error', 'Failed to retrieve session.' );
			}

			return $body;
		}

		/*--------------------------------------------------------------
		 * Currency helpers
		 *------------------------------------------------------------*/

		/**
		 * Convert a decimal amount to the smallest currency unit.
		 *
		 * Most currencies use cents (×100). Zero-decimal currencies
		 * (JPY, KRW, etc.) pass through unchanged.
		 *
		 * @param float  $amount   Decimal amount.
		 * @param string $currency ISO 4217 code (lowercase).
		 * @return int Amount in smallest unit.
		 */
		private static function to_smallest_unit( $amount, $currency ) {
			$zero_decimal = array(
				'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw',
				'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf',
				'xof', 'xpf',
			);

			if ( in_array( strtolower( $currency ), $zero_decimal, true ) ) {
				return (int) round( $amount );
			}

			return (int) round( $amount * 100 );
		}
	}

endif;
