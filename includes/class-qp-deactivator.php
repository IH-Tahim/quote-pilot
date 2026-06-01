<?php
/**
 * Plugin deactivator.
 *
 * Runs cleanup tasks when the plugin is deactivated.
 * Does NOT remove any data — that is handled by uninstall.php.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Deactivator' ) ) :

	/**
	 * Class QP_Deactivator
	 *
	 * Handles plugin deactivation logic.
	 */
	class QP_Deactivator {

		/**
		 * Run deactivation routines.
		 *
		 * Flushes rewrite rules so any custom rules registered by
		 * the plugin are removed cleanly.
		 *
		 * @return void
		 */
		public static function deactivate() {
			flush_rewrite_rules();
		}
	}

endif;
