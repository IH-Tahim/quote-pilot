<?php
/**
 * Shortcode handler for the QuotePilot form.
 *
 * Registers the `[quotepilot_form]` shortcode and returns its HTML
 * by loading the corresponding view file using output buffering.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Shortcode' ) ) :

	/**
	 * Class QP_Shortcode
	 */
	class QP_Shortcode {

		/**
		 * Register the shortcode with WordPress.
		 *
		 * @return void
		 */
		public static function register() {
			add_shortcode( 'quotepilot_form', array( __CLASS__, 'render' ) );
		}

		/**
		 * Render the shortcode form HTML.
		 *
		 * @param array $atts Shortcode attributes.
		 * @return string HTML form output.
		 */
		public static function render( $atts = array() ) {
			// Extract optional attribute to pre-select a service by slug.
			$atts = shortcode_atts(
				array(
					'service' => '',
				),
				$atts,
				'quotepilot_form'
			);

			// Pre-selected service slug.
			$preselected_slug = isset( $atts['service'] ) ? sanitize_title( $atts['service'] ) : '';

			// Load the form view using output buffering.
			ob_start();
			include QP_PLUGIN_DIR . 'modules/quote-calculator/views/form.php';
			return ob_get_clean();
		}
	}

endif;
