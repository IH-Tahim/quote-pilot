<?php
/**
 * Outgoing Webhook & CRM Integrations.
 *
 * Dispatches automated JSON payloads to third-party workflows (Zapier/Make/n8n)
 * on new bookings, and pushes contact details to Mailchimp/Brevo newsletters
 * subject to marketing consent limits.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Integrations' ) ) :

	/**
	 * Class QP_Integrations
	 */
	class QP_Integrations {

		/**
		 * Register action hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'qp_booking_created', array( __CLASS__, 'trigger_webhook' ), 30, 3 );
			add_action( 'qp_booking_created', array( __CLASS__, 'push_to_crm' ), 40, 3 );
		}

		/**
		 * Trigger the generic outgoing JSON webhook.
		 *
		 * @param int   $booking_id  Booking ID.
		 * @param array $booking_row Saved booking row data.
		 * @param array $input       Raw sanitized form input payload.
		 * @return void
		 */
		public static function trigger_webhook( $booking_id, $booking_row, $input ) {
			if ( ! class_exists( 'QP_Database' ) ) {
				return;
			}

			// Load settings
			$settings    = get_option( 'qp_settings', array() );
			$webhook_url = isset( $settings['webhook_url'] ) ? esc_url_raw( $settings['webhook_url'] ) : '';

			if ( empty( $webhook_url ) ) {
				return;
			}

			$booking = QP_Database::get_booking( $booking_id );
			if ( ! $booking ) {
				return;
			}

			$items = QP_Database::get_booking_items( $booking_id );

			// Build full webhook payload
			$payload = array(
				'event'      => 'booking.created',
				'booking_id' => $booking->id,
				'customer'   => array(
					'name'    => $booking->customer_name,
					'email'   => $booking->customer_email,
					'phone'   => $booking->customer_phone,
					'address' => $booking->customer_address,
				),
				'schedule'   => array(
					'date' => $booking->preferred_date,
					'time' => $booking->preferred_time,
				),
				'pricing'    => array(
					'base'       => (float) $booking->base_price,
					'surcharges' => (float) $booking->surcharge_total,
					'discounts'  => (float) $booking->discount_total,
					'tax'        => (float) $booking->tax_total,
					'total'      => (float) $booking->total_price,
				),
				'items'      => $items,
				'created_at' => $booking->created_at,
			);

			// Post payload using wp_safe_remote_post
			wp_safe_remote_post(
				$webhook_url,
				array(
					'method'      => 'POST',
					'timeout'     => 15,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => false, // Fire-and-forget background execution
					'headers'     => array(
						'Content-Type' => 'application/json; charset=utf-8',
					),
					'body'        => wp_json_encode( $payload ),
					'cookies'     => array(),
				)
			);
		}

		/**
		 * Push contact to Mailchimp/Brevo list only if marketing consent is checked.
		 *
		 * @param int   $booking_id  Booking ID.
		 * @param array $booking_row Saved booking row data.
		 * @param array $input       Raw sanitized form input payload.
		 * @return void
		 */
		public static function push_to_crm( $booking_id, $booking_row, $input ) {
			// Check marketing consent gating
			// B5 stores consent_marketing = 1 if checked on submit, and maps it into booking consent_data.
			// Let's verify if marketing consent was given in input or booking row:
			$marketing_consent = ! empty( $input['consent_marketing'] );

			if ( ! $marketing_consent ) {
				return; // Quiet return: Never push contacts without explicit marketing consent
			}

			// Load settings
			$settings   = get_option( 'qp_settings', array() );
			$mc_api_key = isset( $settings['mailchimp_api_key'] ) ? sanitize_text_field( $settings['mailchimp_api_key'] ) : '';
			$mc_list_id = isset( $settings['mailchimp_list_id'] ) ? sanitize_text_field( $settings['mailchimp_list_id'] ) : '';

			if ( empty( $mc_api_key ) || empty( $mc_list_id ) ) {
				return;
			}

			// Split Mailchimp API key to discover datacenter prefix (e.g. us19)
			$parts = explode( '-', $mc_api_key );
			if ( count( $parts ) < 2 ) {
				return;
			}
			$dc = end( $parts );

			$url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$mc_list_id}/members";

			// Parse first/last name
			$name_parts = explode( ' ', $booking_row['customer_name'], 2 );
			$fname      = isset( $name_parts[0] ) ? $name_parts[0] : '';
			$lname      = isset( $name_parts[1] ) ? $name_parts[1] : '';

			$payload = array(
				'email_address' => $booking_row['customer_email'],
				'status'        => 'subscribed',
				'merge_fields'  => array(
					'FNAME' => $fname,
					'LNAME' => $lname,
					'PHONE' => $booking_row['customer_phone'],
				),
			);

			wp_safe_remote_post(
				$url,
				array(
					'method'      => 'POST',
					'timeout'     => 10,
					'blocking'    => false, // Background
					'headers'     => array(
						'Authorization' => 'Basic ' . base64_encode( 'user:' . $mc_api_key ),
						'Content-Type'  => 'application/json; charset=utf-8',
					),
					'body'        => wp_json_encode( $payload ),
				)
			);
		}
	}

endif;
