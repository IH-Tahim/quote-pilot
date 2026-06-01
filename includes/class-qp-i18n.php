<?php
/**
 * Internationalisation handler.
 *
 * Loads the plugin text domain so all translatable strings can be
 * picked up by WordPress translation tools.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_i18n' ) ) :

	/**
	 * Class QP_i18n
	 *
	 * Handles loading of the quote-pilot text domain.
	 */
	class QP_i18n {

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public static function load() {
			load_plugin_textdomain(
				'quote-pilot',
				false,
				dirname( plugin_basename( QP_PLUGIN_FILE ) ) . '/languages'
			);
		}
	}

endif;
