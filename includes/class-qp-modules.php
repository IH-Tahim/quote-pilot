<?php
/**
 * Module loader.
 *
 * Reads the enabled_modules setting and conditionally loads each
 * module's bootstrap file. Acts as the central wiring point so
 * features can be toggled from the admin settings screen.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Modules' ) ) :

	/**
	 * Class QP_Modules
	 *
	 * Boots enabled feature modules.
	 */
	class QP_Modules {

		/**
		 * Boot all enabled modules.
		 *
		 * Reads the 'enabled_modules' key from the qp_settings option
		 * and requires each module's loader file when its flag is true.
		 *
		 * @return void
		 */
		public static function boot() {
			$settings        = get_option( 'qp_settings', array() );
			$enabled_modules = isset( $settings['enabled_modules'] ) ? (array) $settings['enabled_modules'] : array();

			/*--------------------------------------------------------------
			 * Services module (CPT + pricing meta).
			 *------------------------------------------------------------*/
			if ( ! empty( $enabled_modules['services'] ) ) {
				require_once QP_PLUGIN_DIR . 'modules/services/class-qp-services.php';
				QP_Services::init();
			}

			/*--------------------------------------------------------------
			 * Quote Calculator module (form + price engine).
			 *------------------------------------------------------------*/
			if ( ! empty( $enabled_modules['quote_calculator'] ) ) {
				require_once QP_PLUGIN_DIR . 'modules/quote-calculator/class-qp-quote.php';
				QP_Quote::init();
			}

			/*--------------------------------------------------------------
			 * Customer Accounts & Dashboard module.
			 *------------------------------------------------------------*/
			if ( ! empty( $enabled_modules['customer_dashboard'] ) ) {
				require_once QP_PLUGIN_DIR . 'modules/accounts/class-qp-accounts.php';
				QP_Accounts::init();
			}

			/*--------------------------------------------------------------
			 * Notifications & Webhooks module.
			 *------------------------------------------------------------*/
			if ( ! empty( $enabled_modules['email_notifications'] ) ) {
				require_once QP_PLUGIN_DIR . 'modules/notifications/class-qp-notifications.php';
				QP_Notifications::init();
			}

			/*
			 * Future module loading:
			 *
			 * if ( ! empty( $enabled_modules['booking_manager'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/booking-manager/class-qp-booking-manager.php';
			 * }
			 *
			 * if ( ! empty( $enabled_modules['customer_dashboard'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/customer-dashboard/class-qp-customer-dashboard.php';
			 * }
			 *
			 * if ( ! empty( $enabled_modules['lead_capture'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/lead-capture/class-qp-lead-capture.php';
			 * }
			 *
			 * if ( ! empty( $enabled_modules['coupons'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/coupons/class-qp-coupons.php';
			 * }
			 *
			 * if ( ! empty( $enabled_modules['date_rules'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/date-rules/class-qp-date-rules.php';
			 * }
			 *
			 * if ( ! empty( $enabled_modules['payments'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/payments/class-qp-payments.php';
			 * }
			 *
			 * if ( ! empty( $enabled_modules['email_notifications'] ) ) {
			 *     require_once QP_PLUGIN_DIR . 'modules/email-notifications/class-qp-email-notifications.php';
			 * }
			 */
		}
	}

endif;
