<?php
/**
 * PayPal Orders API v2 gateway.
 *
 * Creates PayPal orders using direct REST API calls — no PayPal SDK
 * required. The customer is redirected to PayPal's approval page,
 * then returns to a callback URL where the order is captured.
 *
 * Client credentials are loaded server-side only and NEVER exposed
 * to the browser or logged.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_PayPal' ) ) :

	/**
	 * Class QP_PayPal
	 *
	 * PayPal Orders API v2 integration via REST.
	 */
	class QP_PayPal {

		/**
		 * PayPal API base URLs.
		 *
		 * @var array
		 */
		const API_URLS = array(
			'sandbox' => 'https://api-m.sandbox.paypal.com',
			'live'    => 'https://api-m.paypal.com',
		);

		/*--------------------------------------------------------------
		 * Environment
		 *------------------------------------------------------------*/

		/**
		 * Get the PayPal API base URL for the configured environment.
		 *
		 * @return string
		 */
		private static function get_api_base() {
			$mode = QP_Helpers::get_setting( 'paypal_mode', 'sandbox' );
			return isset( self::API_URLS[ $mode ] ) ? self::API_URLS[ $mode ] : self::API_URLS['sandbox'];
		}

		/*--------------------------------------------------------------
		 * OAuth2 access token
		 *------------------------------------------------------------*/

		/**
		 * Obtain a PayPal access token via client credentials grant.
		 *
		 * Caches the token transiently for its lifetime minus a 60-second
		 * buffer to avoid edge-case expiry during a request.
		 *
		 * @return string|WP_Error Access token string or WP_Error.
		 */
		public static function get_access_token() {
			$cached = get_transient( 'qp_paypal_access_token' );
			if ( ! empty( $cached ) ) {
				return $cached;
			}

			$client_id     = QP_Helpers::get_setting( 'paypal_client_id', '' );
			$client_secret = QP_Helpers::get_setting( 'paypal_secret', '' );

			if ( empty( $client_id ) || empty( $client_secret ) ) {
				return new WP_Error(
					'qp_paypal_no_credentials',
					__( 'PayPal credentials are not configured.', 'quote-pilot' )
				);
			}

			$response = wp_remote_post(
				self::get_api_base() . '/v1/oauth2/token',
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						'Content-Type'  => 'application/x-www-form-urlencoded',
					),
					'body'    => 'grant_type=client_credentials',
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'QuotePilot PayPal Auth Error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_paypal_auth_failed',
					__( 'Unable to authenticate with PayPal. Please try again.', 'quote-pilot' )
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
				$error = isset( $body['error_description'] ) ? $body['error_description'] : 'Unknown error';
				error_log( 'QuotePilot PayPal Auth Error (' . $code . '): ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_paypal_auth_error',
					__( 'PayPal authentication failed. Please check your credentials.', 'quote-pilot' )
				);
			}

			$token    = $body['access_token'];
			$lifetime = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;

			// Cache with a 60-second safety buffer.
			set_transient( 'qp_paypal_access_token', $token, max( 60, $lifetime - 60 ) );

			return $token;
		}

		/*--------------------------------------------------------------
		 * Order creation
		 *------------------------------------------------------------*/

		/**
		 * Create a PayPal order for a booking.
		 *
		 * Uses POST /v2/checkout/orders with CAPTURE intent.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param float  $amount     Amount to charge (already computed).
		 * @param string $currency   ISO 4217 currency code (lowercase).
		 * @param string $return_url URL to redirect on approval.
		 * @param string $cancel_url URL to redirect on cancel.
		 * @return array|WP_Error Array with 'redirect_url' and 'order_id', or WP_Error.
		 */
		public static function create_order( $booking_id, $amount, $currency, $return_url, $cancel_url ) {
			$token = self::get_access_token();

			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$order_body = array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array(
					array(
						'reference_id' => 'booking_' . $booking_id,
						'custom_id'    => (string) $booking_id,
						'description'  => sprintf(
							/* translators: %d: booking ID */
							__( 'QuotePilot Booking #%d', 'quote-pilot' ),
							$booking_id
						),
						'amount'       => array(
							'currency_code' => strtoupper( $currency ),
							'value'         => number_format( $amount, 2, '.', '' ),
						),
					),
				),
				'application_context' => array(
					'return_url'          => $return_url,
					'cancel_url'          => $cancel_url,
					'brand_name'          => get_bloginfo( 'name' ),
					'user_action'         => 'PAY_NOW',
					'shipping_preference' => 'NO_SHIPPING',
				),
			);

			$response = wp_remote_post(
				self::get_api_base() . '/v2/checkout/orders',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
						'Prefer'        => 'return=representation',
					),
					'body'    => wp_json_encode( $order_body ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'QuotePilot PayPal Order Error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_paypal_request_failed',
					__( 'Unable to connect to PayPal. Please try again.', 'quote-pilot' )
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
				$error = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
				error_log( 'QuotePilot PayPal API Error (' . $code . '): ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_paypal_api_error',
					__( 'PayPal returned an error. Please try again or contact support.', 'quote-pilot' )
				);
			}

			// Find the approval link.
			$redirect_url = '';
			if ( isset( $body['links'] ) && is_array( $body['links'] ) ) {
				foreach ( $body['links'] as $link ) {
					if ( 'approve' === $link['rel'] ) {
						$redirect_url = $link['href'];
						break;
					}
				}
			}

			if ( empty( $redirect_url ) ) {
				return new WP_Error(
					'qp_paypal_no_approval_url',
					__( 'PayPal did not return an approval URL.', 'quote-pilot' )
				);
			}

			// Store the PayPal order ID on the booking for later capture.
			QP_Database::update_booking(
				(int) $booking_id,
				array(
					'gateway'        => 'paypal',
					'gateway_txn_id' => sanitize_text_field( $body['id'] ),
				)
			);

			return array(
				'redirect_url' => $redirect_url,
				'order_id'     => $body['id'],
			);
		}

		/*--------------------------------------------------------------
		 * Order capture
		 *------------------------------------------------------------*/

		/**
		 * Capture a previously approved PayPal order.
		 *
		 * Called on the return URL after the customer approves payment.
		 *
		 * @param string $order_id The PayPal order ID.
		 * @return array|WP_Error Array with 'status', 'capture_id', 'amount', or WP_Error.
		 */
		public static function capture_order( $order_id ) {
			$token = self::get_access_token();

			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$response = wp_remote_post(
				self::get_api_base() . '/v2/checkout/orders/' . urlencode( $order_id ) . '/capture',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
						'Prefer'        => 'return=representation',
					),
					'body'    => '{}',
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'QuotePilot PayPal Capture Error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error(
					'qp_paypal_capture_failed',
					__( 'Failed to capture PayPal payment. Please contact support.', 'quote-pilot' )
				);
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 ) {
				$error = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
				error_log( 'QuotePilot PayPal Capture Error (' . $code . '): ' . $error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				// If already captured, that's fine — idempotent.
				if ( isset( $body['details'][0]['issue'] ) && 'ORDER_ALREADY_CAPTURED' === $body['details'][0]['issue'] ) {
					return array(
						'status'     => 'COMPLETED',
						'capture_id' => $order_id,
						'amount'     => 0.0,
					);
				}

				return new WP_Error(
					'qp_paypal_capture_error',
					__( 'PayPal capture failed.', 'quote-pilot' )
				);
			}

			// Extract capture details.
			$status     = isset( $body['status'] ) ? $body['status'] : '';
			$capture_id = '';
			$amount     = 0.0;

			if ( isset( $body['purchase_units'][0]['payments']['captures'][0] ) ) {
				$capture    = $body['purchase_units'][0]['payments']['captures'][0];
				$capture_id = isset( $capture['id'] ) ? $capture['id'] : $order_id;
				$amount     = isset( $capture['amount']['value'] ) ? (float) $capture['amount']['value'] : 0.0;
			}

			return array(
				'status'     => $status,
				'capture_id' => $capture_id,
				'amount'     => $amount,
			);
		}

		/*--------------------------------------------------------------
		 * Webhook signature verification
		 *------------------------------------------------------------*/

		/**
		 * Verify a PayPal webhook notification via their verification API.
		 *
		 * PayPal's recommended approach: POST the headers + body back
		 * to their /v1/notifications/verify-webhook-signature endpoint.
		 *
		 * @param string $webhook_id  The webhook ID from settings.
		 * @param array  $headers     HTTP headers from the webhook request.
		 * @param string $raw_body    Raw request body.
		 * @return bool|WP_Error True if verified, WP_Error if not.
		 */
		public static function verify_webhook_signature( $webhook_id, $headers, $raw_body ) {
			$token = self::get_access_token();

			if ( is_wp_error( $token ) ) {
				return $token;
			}

			// PayPal sends these headers for verification.
			$auth_algo         = isset( $headers['PAYPAL-AUTH-ALGO'] ) ? $headers['PAYPAL-AUTH-ALGO'] : '';
			$cert_url          = isset( $headers['PAYPAL-CERT-URL'] ) ? $headers['PAYPAL-CERT-URL'] : '';
			$transmission_id   = isset( $headers['PAYPAL-TRANSMISSION-ID'] ) ? $headers['PAYPAL-TRANSMISSION-ID'] : '';
			$transmission_sig  = isset( $headers['PAYPAL-TRANSMISSION-SIG'] ) ? $headers['PAYPAL-TRANSMISSION-SIG'] : '';
			$transmission_time = isset( $headers['PAYPAL-TRANSMISSION-TIME'] ) ? $headers['PAYPAL-TRANSMISSION-TIME'] : '';

			if ( empty( $auth_algo ) || empty( $transmission_id ) || empty( $transmission_sig ) ) {
				return new WP_Error( 'qp_paypal_webhook_missing_headers', 'Missing PayPal webhook headers.' );
			}

			$verify_body = array(
				'auth_algo'         => $auth_algo,
				'cert_url'          => $cert_url,
				'transmission_id'   => $transmission_id,
				'transmission_sig'  => $transmission_sig,
				'transmission_time' => $transmission_time,
				'webhook_id'        => $webhook_id,
				'webhook_event'     => json_decode( $raw_body, true ),
			);

			$response = wp_remote_post(
				self::get_api_base() . '/v1/notifications/verify-webhook-signature',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $verify_body ),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error( 'qp_paypal_webhook_verify_error', 'Verification API returned an error.' );
			}

			$verification_status = isset( $body['verification_status'] ) ? $body['verification_status'] : '';

			if ( 'SUCCESS' !== $verification_status ) {
				return new WP_Error( 'qp_paypal_webhook_invalid', 'Webhook signature verification failed.' );
			}

			return true;
		}
	}

endif;
