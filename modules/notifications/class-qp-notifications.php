<?php
/**
 * Notifications & Integrations Bootstrap Coordinator.
 *
 * Bootstraps transactional email responders, pre-filled WhatsApp click-to-chat
 * links, outgoing JSON automation webhooks, and CRM newsletter lists.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Notifications' ) ) :

	/**
	 * Class QP_Notifications
	 */
	class QP_Notifications {

		/**
		 * Initialise the notification module.
		 *
		 * @return self
		 */
		public static function init() {
			return new self();
		}

		/**
		 * Constructor — load notification sub-controllers.
		 */
		public function __construct() {
			// Require the notification files
			require_once QP_PLUGIN_DIR . 'modules/notifications/class-qp-email.php';
			require_once QP_PLUGIN_DIR . 'modules/notifications/class-qp-whatsapp.php';
			require_once QP_PLUGIN_DIR . 'modules/notifications/class-qp-integrations.php';

			// Register actions
			QP_Email::register();
			QP_WhatsApp::register();
			QP_Integrations::register();
		}
	}

endif;
