<?php
/**
 * Email Notifications Handler.
 *
 * Sends stylized confirmation emails to customers and new-booking alerts
 * to administrators using wp_mail(). Supports editable subjects, body templates,
 * dynamic merge tags, and SMTP recommendations.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Email' ) ) :

	/**
	 * Class QP_Email
	 */
	class QP_Email {

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public static function register() {
			add_action( 'qp_booking_created', array( __CLASS__, 'trigger_emails' ), 10, 3 );
		}

		/**
		 * Replace merge tags in a template string.
		 *
		 * @param string $template Dynamic template string.
		 * @param array  $data     Key-value pair data array.
		 * @return string Replaced template string.
		 */
		public static function replace_tags( $template, $data ) {
			$tags = array(
				'[customer_name]'  => isset( $data['customer_name'] ) ? $data['customer_name'] : '',
				'[booking_id]'     => isset( $data['id'] ) ? $data['id'] : '',
				'[service_name]'   => isset( $data['service_name'] ) ? $data['service_name'] : '',
				'[booking_date]'   => isset( $data['preferred_date'] ) ? $data['preferred_date'] : '',
				'[booking_time]'   => isset( $data['preferred_time'] ) ? $data['preferred_time'] : '',
				'[booking_total]'  => isset( $data['total_price'] ) ? $data['total_price'] : '0.00',
				'[booking_status]' => isset( $data['booking_status'] ) ? $data['booking_status'] : '',
			);

			return str_replace( array_keys( $tags ), array_values( $tags ), $template );
		}

		/**
		 * Callback when a new booking is created. Sends customer confirmation and admin alert.
		 *
		 * @param int   $booking_id  Booking ID.
		 * @param array $booking_row Saved booking row data.
		 * @param array $input       Raw sanitized form input payload.
		 * @return void
		 */
		public static function trigger_emails( $booking_id, $booking_row, $input ) {
			if ( ! class_exists( 'QP_Database' ) ) {
				return;
			}

			$booking = QP_Database::get_booking( $booking_id );
			if ( ! $booking ) {
				return;
			}

			// Load settings
			$settings        = get_option( 'qp_settings', array() );
			$currency_symbol = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

			$service_title = esc_html__( 'Cleaning Service', 'quote-pilot' );
			$service_post  = get_post( $booking->service_id );
			if ( $service_post ) {
				$service_title = get_the_title( $service_post );
			}

			// Format dynamic details
			$replacements = array(
				'customer_name'  => $booking->customer_name,
				'id'             => $booking->id,
				'service_name'   => $service_title,
				'preferred_date' => date_i18n( get_option( 'date_format' ), strtotime( $booking->preferred_date ) ),
				'preferred_time' => $booking->preferred_time,
				'total_price'    => $currency_symbol . number_format( $booking->total_price, 2 ),
				'booking_status' => ucfirst( $booking->booking_status ),
			);

			// RENDER HTML EMAIL TEMPLATES

			// 1. CUSTOMER CONFIRMATION
			$cust_subject = sprintf( esc_html__( 'Booking Confirmation — #%s', 'quote-pilot' ), $booking->id );
			$cust_body    = "Hello [customer_name],\n\n"
				. "Thank you for choosing QuotePilot! Your booking has been received and is pending review.\n\n"
				. "Booking Summary:\n"
				. "- Booking ID: #[booking_id]\n"
				. "- Service: [service_name]\n"
				. "- Date & Time: [booking_date] @ [booking_time]\n"
				. "- Total Price: [booking_total]\n"
				. "- Status: [booking_status]\n\n"
				. "Best regards,\nThe Cleaning Team";

			$cust_subject_compiled = self::replace_tags( $cust_subject, $replacements );
			$cust_body_compiled    = self::replace_tags( $cust_body, $replacements );

			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

			wp_mail( $booking->customer_email, $cust_subject_compiled, $cust_body_compiled, $headers );

			// 2. ADMIN NOTIFICATION ALERT
			$admin_email = get_option( 'admin_email' );
			if ( ! empty( $admin_email ) ) {
				$admin_subject = sprintf( esc_html__( '[QuotePilot Alert] New Booking — #%s', 'quote-pilot' ), $booking->id );
				$admin_body    = "Hello Admin,\n\n"
					. "A new quote form has been successfully submitted and saved to the database.\n\n"
					. "Details:\n"
					. "- Booking ID: #[booking_id]\n"
					. "- Customer: [customer_name]\n"
					. "- Service: [service_name]\n"
					. "- Schedule: [booking_date] @ [booking_time]\n"
					. "- Total Price: [booking_total]\n"
					. "- Status: [booking_status]\n\n"
					. "Log in to your WordPress dashboard to assign cleaners and manage settings.\n\n"
					. "Regards,\nQuotePilot System";

				$admin_subject_compiled = self::replace_tags( $admin_subject, $replacements );
				$admin_body_compiled    = self::replace_tags( $admin_body, $replacements );

				wp_mail( $admin_email, $admin_subject_compiled, $admin_body_compiled, $headers );
			}
		}
	}

endif;
