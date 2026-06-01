<?php
/**
 * WhatsApp click-to-chat prefilled text generator.
 *
 * Generates free-tier wa.me click-to-chat links pre-populated with booking summaries
 * for both frontend confirmation screens and administrator alerts. Offers a clearly
 * marked developer hook/stub for upgrading to premium API tiers (Twilio / Meta Cloud API).
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_WhatsApp' ) ) :

	/**
	 * Class QP_WhatsApp
	 */
	class QP_WhatsApp {

		/**
		 * Expose active hooks if any.
		 *
		 * @return void
		 */
		public static function register() {
			// Free tier runs on-demand when displaying screens or email alerts.
			// Stub hook for future automated paid WhatsApp API triggers
			add_action( 'qp_booking_created', array( __CLASS__, 'trigger_paid_whatsapp_stub' ), 20, 3 );
		}

		/**
		 * Free tier click-to-chat generator link.
		 *
		 * @param object $booking Booking row database object.
		 * @return string Prefilled wa.me link.
		 */
		public static function get_click_to_chat_link( $booking ) {
			if ( ! $booking ) {
				return '';
			}

			// Load settings
			$settings       = get_option( 'qp_settings', array() );
			$business_phone = isset( $settings['whatsapp_business_phone'] ) ? sanitize_text_field( $settings['whatsapp_business_phone'] ) : '';
			$currency       = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

			$service_title = esc_html__( 'Cleaning Service', 'quote-pilot' );
			$service_post  = get_post( $booking->service_id );
			if ( $service_post ) {
				$service_title = get_the_title( $service_post );
			}

			// Prefilled message text
			$msg = sprintf(
				/* translators: 1: Booking ID, 2: Customer Name, 3: Service Name, 4: Date, 5: Time, 6: Price */
				__( "Hello, I'd like to confirm my QuotePilot Booking!\n\nDetails:\n- Booking ID: #%1$d\n- Customer: %2$s\n- Service: %3$s\n- Date: %4$s\n- Time: %5$s\n- Total: %6$s\n\nThank you!", 'quote-pilot' ),
				$booking->id,
				$booking->customer_name,
				$service_title,
				date_i18n( get_option( 'date_format' ), strtotime( $booking->preferred_date ) ),
				$booking->preferred_time,
				$currency . number_format( $booking->total_price, 2 )
			);

			// Sanitize business phone number (only digits)
			$clean_phone = preg_replace( '/[^0-9]/', '', $business_phone );

			if ( empty( $clean_phone ) ) {
				// Blank wa.me lets the user choose who to send to on click
				return 'https://wa.me/?text=' . rawurlencode( $msg );
			}

			return 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode( $msg );
		}

		/**
		 * Developer Stub for paid Automated WhatsApp API Tier (v1.1+ upgrade pathway).
		 *
		 * @param int   $booking_id  Booking ID.
		 * @param array $booking_row Saved booking row.
		 * @param array $input       Raw form inputs.
		 * @return void
		 */
		public static function trigger_paid_whatsapp_stub( $booking_id, $booking_row, $input ) {
			/**
			 * =================================================================
			 * DEVELOPER PAID API STUB — UPGRADE PATHWAY
			 * =================================================================
			 * To integrate automated outbound WhatsApp reminders via Twilio or
			 * Meta Cloud APIs:
			 *
			 * 1. Fetch credentials from settings:
			 *    $sid = QP_Helpers::get_setting('whatsapp_paid_sid');
			 *    $token = QP_Helpers::get_setting('whatsapp_paid_token');
			 *
			 * 2. Invoke wp_remote_post() to fire outbound payload:
			 *    wp_remote_post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", array(
			 *        'headers' => array( 'Authorization' => 'Basic ' . base64_encode("{$sid}:{$token}") ),
			 *        'body'    => array(
			 *            'From' => 'whatsapp:' . $twilio_number,
			 *            'To'   => 'whatsapp:' . $booking_row['customer_phone'],
			 *            'Body' => $message_compiled
			 *        )
			 *    ));
			 * =================================================================
			 */

			// For now, this is a clean, passive developer seam.
			do_action( 'qp_whatsapp_automated_stub_triggered', $booking_id );
		}
	}

endif;
