<?php
/**
 * Services module loader.
 *
 * Bootstraps the Services CPT and its pricing meta box by
 * requiring and instantiating the relevant classes.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Services' ) ) :

	/**
	 * Class QP_Services
	 *
	 * Entry point for the Services module.
	 */
	class QP_Services {

		/**
		 * Initialise the module.
		 *
		 * Loads the CPT registration class and the pricing meta-box
		 * class, then instantiates both so they hook into WordPress.
		 *
		 * @return void
		 */
		public static function init() {
			require_once QP_PLUGIN_DIR . 'modules/services/class-qp-services-cpt.php';
			require_once QP_PLUGIN_DIR . 'modules/services/class-qp-services-meta.php';

			new QP_Services_CPT();
			new QP_Services_Meta();
		}
	}

endif;
