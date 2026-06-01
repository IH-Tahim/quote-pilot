<?php
/**
 * Plugin Name: QuotePilot
 * Description: Instant quote calculator, booking, and customer dashboard for cleaning service businesses.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Imam Hasan (Tahim)
 * Author URI:  https://ihtahim.com
 * License:     GPL-2.0-or-later
 * Text Domain: quote-pilot
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*--------------------------------------------------------------
 * Constants
 *------------------------------------------------------------*/
define( 'QP_VERSION', '1.0.0' );
define( 'QP_DB_VERSION', '1.0.0' );
define( 'QP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QP_PLUGIN_FILE', __FILE__ );

/*--------------------------------------------------------------
 * Include core class files
 *------------------------------------------------------------*/
require_once QP_PLUGIN_DIR . 'includes/class-qp-activator.php';
require_once QP_PLUGIN_DIR . 'includes/class-qp-deactivator.php';
require_once QP_PLUGIN_DIR . 'includes/class-qp-database.php';
require_once QP_PLUGIN_DIR . 'includes/qp-field-keys.php';
require_once QP_PLUGIN_DIR . 'includes/class-qp-modules.php';
require_once QP_PLUGIN_DIR . 'includes/class-qp-helpers.php';
require_once QP_PLUGIN_DIR . 'includes/class-qp-i18n.php';

/*--------------------------------------------------------------
 * Activation & Deactivation hooks
 *------------------------------------------------------------*/
register_activation_hook( __FILE__, array( 'QP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'QP_Deactivator', 'deactivate' ) );

/*--------------------------------------------------------------
 * Bootstrap the plugin after all plugins are loaded
 *------------------------------------------------------------*/
add_action( 'plugins_loaded', 'qp_bootstrap_plugin' );

/**
 * Load text domain and boot the module system.
 *
 * @return void
 */
function qp_bootstrap_plugin() {
	QP_i18n::load();
	QP_Modules::boot();
}
