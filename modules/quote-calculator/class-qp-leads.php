<?php
/**
 * Lead Handler.
 *
 * Captures consent-gated partial form data prior to submission to help with
 * recovery of abandoned quotes. On full submission, links lead as converted.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Leads' ) ) :

	/**
	 * Class QP_Leads
	 */
	class QP_Leads {

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public static function register() {
			// Register AJAX endpoints
			add_action( 'wp_ajax_qp_save_lead', array( __CLASS__, 'save_partial' ) );
			add_action( 'wp_ajax_nopriv_qp_save_lead', array( __CLASS__, 'save_partial' ) );

			// Hook to flip lead status to 'converted' on booking creation
			add_action( 'qp_booking_created', array( __CLASS__, 'convert_lead_on_booking' ), 10, 3 );
		}

		/**
		 * AJAX handler for consent-gated partial lead capture.
		 *
		 * @return void
		 */
		public static function save_partial() {
			// 1. Verify Request Nonce
			if ( ! class_exists( 'QP_Helpers' ) || ! QP_Helpers::verify_request( 'qp_quote_form' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'quote-pilot' ) ), 403 );
			}

			// 2. Confirm consent_given is present and true
			$consent_given = ! empty( $_POST['consent_given'] );
			if ( ! $consent_given ) {
				// Return success-quietly: never store PII without consent
				wp_send_json_success( array( 'message' => esc_html__( 'PII not stored without explicit consent.', 'quote-pilot' ) ) );
			}

			// 3. Sanitize fields
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

			if ( empty( $email ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Email is required.', 'quote-pilot' ) ), 400 );
			}

			// Partial data payload parsing
			$partial_raw  = isset( $_POST['partial_data'] ) ? wp_unslash( $_POST['partial_data'] ) : '';
			$partial_data = json_decode( $partial_raw, true );

			if ( ! is_array( $partial_data ) ) {
				$partial_data = array();
			}

			// Sanitize partial data
			$sanitized_partial = array(
				'service_id'      => isset( $partial_data['service_id'] ) ? (int) $partial_data['service_id'] : 0,
				'bedrooms'        => isset( $partial_data['bedrooms'] ) ? (int) $partial_data['bedrooms'] : 0,
				'bathrooms'       => isset( $partial_data['bathrooms'] ) ? (int) $partial_data['bathrooms'] : 0,
				'extra_bathrooms' => isset( $partial_data['extra_bathrooms'] ) ? (int) $partial_data['extra_bathrooms'] : 0,
				'preferred_date'  => isset( $partial_data['preferred_date'] ) ? sanitize_text_field( $partial_data['preferred_date'] ) : '',
				'estimated_total' => isset( $partial_data['estimated_total'] ) ? (float) $partial_data['estimated_total'] : 0.0,
			);

			// Build consent data proof JSON
			$consent_proof = wp_json_encode(
				array(
					'consent_given' => true,
					'timestamp'     => current_time( 'mysql' ),
					'ip_address'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				)
			);

			$lead_payload = array(
				'email'         => $email,
				'phone'         => $phone,
				'partial_data'  => wp_json_encode( $sanitized_partial ),
				'consent_given' => 1,
				'consent_data'  => $consent_proof,
				'lead_status'   => 'new',
			);

			// 4. Upsert by Email
			if ( class_exists( 'QP_Database' ) ) {
				$existing = QP_Database::get_lead_by_email( $email );
				if ( $existing ) {
					// Only update if current lead status is 'new' or if we want to overwrite
					if ( 'converted' !== $existing->lead_status ) {
						QP_Database::update_lead( $existing->id, $lead_payload );
						wp_send_json_success( array( 'message' => esc_html__( 'Lead updated.', 'quote-pilot' ) ) );
					} else {
						wp_send_json_success( array( 'message' => esc_html__( 'Lead already converted.', 'quote-pilot' ) ) );
					}
				} else {
					QP_Database::insert_lead( $lead_payload );
					wp_send_json_success( array( 'message' => esc_html__( 'Lead captured.', 'quote-pilot' ) ) );
				}
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Database layer missing.', 'quote-pilot' ) ), 500 );
			}
		}

		/**
		 * Callback for qp_booking_created action hook.
		 * Marks a matching lead as 'converted' and records the booking ID.
		 *
		 * @param int   $booking_id  New booking row ID.
		 * @param array $booking_row Saved booking row associative array.
		 * @param array $input       Raw sanitized form input payload.
		 * @return void
		 */
		public static function convert_lead_on_booking( $booking_id, $booking_row, $input ) {
			$email = isset( $booking_row['customer_email'] ) ? sanitize_email( $booking_row['customer_email'] ) : '';
			if ( empty( $email ) ) {
				return;
			}

			if ( class_exists( 'QP_Database' ) ) {
				$lead = QP_Database::get_lead_by_email( $email );
				if ( $lead ) {
					QP_Database::update_lead(
						$lead->id,
						array(
							'lead_status'          => 'converted',
							'converted_booking_id' => (int) $booking_id,
						)
					);
				}
			}
		}
	}

endif;
