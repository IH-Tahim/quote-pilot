<?php
/**
 * Admin coordinator.
 *
 * Boots all admin-only controllers and registers the primary top-level
 * navigation menu and subpages.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Admin' ) ) :

	/**
	 * Class QP_Admin
	 */
	class QP_Admin {

		/**
		 * Initialise the admin controllers.
		 *
		 * @return self
		 */
		public static function init() {
			return new self();
		}

		/**
		 * Constructor — load admin components.
		 */
		public function __construct() {
			// Require the admin files
			require_once QP_PLUGIN_DIR . 'admin/class-qp-settings.php';
			require_once QP_PLUGIN_DIR . 'admin/class-qp-bookings-list.php';
			require_once QP_PLUGIN_DIR . 'admin/class-qp-branding.php';

			// C3 components (loaded here for administration simplicity)
			require_once QP_PLUGIN_DIR . 'admin/class-qp-date-rules-admin.php';
			require_once QP_PLUGIN_DIR . 'admin/class-qp-coupons-admin.php';

			// Init settings and branding options
			QP_Settings::register();
			QP_Branding::register();
			QP_Bookings_List::register();
			QP_Date_Rules_Admin::register();
			QP_Coupons_Admin::register();

			// Add main menu and submenus
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		}

		/**
		 * Add QuotePilot main menu and its submenus to the admin sidebar.
		 *
		 * @return void
		 */
		public function add_admin_menu() {
			add_menu_page(
				esc_html__( 'QuotePilot Bookings', 'quote-pilot' ),
				esc_html__( 'QuotePilot', 'quote-pilot' ),
				'manage_options',
				'quote-pilot',
				array( 'QP_Bookings_List', 'render_page' ),
				'dashicons-clipboard',
				25
			);

			add_submenu_page(
				'quote-pilot',
				esc_html__( 'Bookings List', 'quote-pilot' ),
				esc_html__( 'Bookings', 'quote-pilot' ),
				'manage_options',
				'quote-pilot',
				array( 'QP_Bookings_List', 'render_page' )
			);

			add_submenu_page(
				'quote-pilot',
				esc_html__( 'Date Rules', 'quote-pilot' ),
				esc_html__( 'Date Rules', 'quote-pilot' ),
				'manage_options',
				'qp-date-rules',
				array( 'QP_Date_Rules_Admin', 'render_page' )
			);

			add_submenu_page(
				'quote-pilot',
				esc_html__( 'Coupon Management', 'quote-pilot' ),
				esc_html__( 'Coupons', 'quote-pilot' ),
				'manage_options',
				'qp-coupons',
				array( 'QP_Coupons_Admin', 'render_page' )
			);

			add_submenu_page(
				'quote-pilot',
				esc_html__( 'Branding Colors & Fonts', 'quote-pilot' ),
				esc_html__( 'Branding', 'quote-pilot' ),
				'manage_options',
				'qp-branding',
				array( 'QP_Branding', 'render_page' )
			);

			add_submenu_page(
				'quote-pilot',
				esc_html__( 'QuotePilot Settings', 'quote-pilot' ),
				esc_html__( 'Settings', 'quote-pilot' ),
				'manage_options',
				'qp-settings',
				array( 'QP_Settings', 'render_page' )
			);
		}
	}

endif;
