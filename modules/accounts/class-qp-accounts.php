<?php
/**
 * Customer Accounts & Dashboard module bootstrap.
 *
 * Coordinates custom user registration, authentication hooks, email-keyed
 * guest bookings backfilling, and front-end shortcode dashboards.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Accounts' ) ) :

	/**
	 * Class QP_Accounts
	 */
	class QP_Accounts {

		/**
		 * Initialise the module.
		 *
		 * @return self
		 */
		public static function init() {
			return new self();
		}

		/**
		 * Constructor — load sub-components.
		 */
		public function __construct() {
			// Require the sub-component files
			require_once QP_PLUGIN_DIR . 'modules/accounts/class-qp-email-link.php';
			require_once QP_PLUGIN_DIR . 'modules/accounts/class-qp-dashboard.php';

			// Register hooks
			QP_Email_Link::register();
			QP_Dashboard::register();
		}
	}

endif;
