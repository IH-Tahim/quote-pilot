<?php
/**
 * Plugin activator.
 *
 * Creates custom database tables, registers the qp_customer role,
 * seeds default settings, and flushes rewrite rules.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Activator' ) ) :

	/**
	 * Class QP_Activator
	 *
	 * Handles everything that should happen on plugin activation.
	 */
	class QP_Activator {

		/**
		 * Run activation routines.
		 *
		 * @return void
		 */
		public static function activate() {
			self::create_tables();
			self::create_roles();
			self::create_default_settings();
			flush_rewrite_rules();
		}

		/**
		 * Create all custom database tables using dbDelta.
		 *
		 * @return void
		 */
		private static function create_tables() {
			global $wpdb;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $wpdb->get_charset_collate();

			/*--------------------------------------------------------------
			 * Table: qp_bookings
			 *------------------------------------------------------------*/
			$table_bookings = $wpdb->prefix . 'qp_bookings';

			$sql_bookings = "CREATE TABLE {$table_bookings} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_email varchar(190) NOT NULL DEFAULT '',
				customer_name varchar(190) NOT NULL DEFAULT '',
				customer_phone varchar(40) NOT NULL DEFAULT '',
				customer_address text NULL,
				service_id bigint(20) unsigned NOT NULL DEFAULT 0,
				preferred_date date DEFAULT NULL,
				preferred_time varchar(40) NOT NULL DEFAULT '',
				base_price decimal(10,2) NOT NULL DEFAULT '0.00',
				surcharge_total decimal(10,2) NOT NULL DEFAULT '0.00',
				discount_total decimal(10,2) NOT NULL DEFAULT '0.00',
				tax_rate decimal(5,2) NOT NULL DEFAULT '0.00',
				tax_total decimal(10,2) NOT NULL DEFAULT '0.00',
				total_price decimal(10,2) NOT NULL DEFAULT '0.00',
				payment_mode varchar(20) NOT NULL DEFAULT 'none',
				payment_status varchar(20) NOT NULL DEFAULT 'unpaid',
				amount_paid decimal(10,2) NOT NULL DEFAULT '0.00',
				gateway varchar(20) NOT NULL DEFAULT 'none',
				gateway_txn_id varchar(190) NOT NULL DEFAULT '',
				booking_status varchar(20) NOT NULL DEFAULT 'pending',
				assigned_cleaner varchar(190) NOT NULL DEFAULT '',
				admin_notes text NULL,
				consent_data text NULL,
				created_at datetime DEFAULT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY customer_email (customer_email),
				KEY booking_status (booking_status),
				KEY preferred_date (preferred_date),
				KEY created_at (created_at)
			) {$charset_collate};";

			dbDelta( $sql_bookings );

			/*--------------------------------------------------------------
			 * Table: qp_booking_items
			 *------------------------------------------------------------*/
			$table_booking_items = $wpdb->prefix . 'qp_booking_items';

			$sql_booking_items = "CREATE TABLE {$table_booking_items} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
				item_label varchar(190) NOT NULL DEFAULT '',
				item_type varchar(30) NOT NULL DEFAULT '',
				quantity decimal(10,2) NOT NULL DEFAULT '0.00',
				unit_amount decimal(10,2) NOT NULL DEFAULT '0.00',
				line_total decimal(10,2) NOT NULL DEFAULT '0.00',
				PRIMARY KEY  (id),
				KEY booking_id (booking_id)
			) {$charset_collate};";

			dbDelta( $sql_booking_items );

			/*--------------------------------------------------------------
			 * Table: qp_leads
			 *------------------------------------------------------------*/
			$table_leads = $wpdb->prefix . 'qp_leads';

			$sql_leads = "CREATE TABLE {$table_leads} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(40) NOT NULL DEFAULT '',
				partial_data text NULL,
				consent_given tinyint(1) NOT NULL DEFAULT 0,
				consent_data text NULL,
				lead_status varchar(20) NOT NULL DEFAULT 'new',
				converted_booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime DEFAULT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY email (email),
				KEY lead_status (lead_status)
			) {$charset_collate};";

			dbDelta( $sql_leads );

			/*--------------------------------------------------------------
			 * Table: qp_date_rules
			 *------------------------------------------------------------*/
			$table_date_rules = $wpdb->prefix . 'qp_date_rules';

			$sql_date_rules = "CREATE TABLE {$table_date_rules} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				rule_date date DEFAULT NULL,
				rule_type varchar(20) NOT NULL DEFAULT 'closed',
				surcharge_type varchar(10) NOT NULL DEFAULT 'flat',
				surcharge_value decimal(10,2) NOT NULL DEFAULT '0.00',
				note varchar(190) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY rule_date (rule_date)
			) {$charset_collate};";

			dbDelta( $sql_date_rules );

			/*--------------------------------------------------------------
			 * Table: qp_coupons
			 *------------------------------------------------------------*/
			$table_coupons = $wpdb->prefix . 'qp_coupons';

			$sql_coupons = "CREATE TABLE {$table_coupons} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code varchar(60) NOT NULL DEFAULT '',
				discount_type varchar(10) NOT NULL DEFAULT 'flat',
				discount_value decimal(10,2) NOT NULL DEFAULT '0.00',
				usage_limit int(11) NOT NULL DEFAULT 0,
				usage_count int(11) NOT NULL DEFAULT 0,
				expires_at date DEFAULT NULL,
				active tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY  (id),
				UNIQUE KEY code (code)
			) {$charset_collate};";

			dbDelta( $sql_coupons );

			/*--------------------------------------------------------------
			 * Store the DB version for future upgrade checks
			 *------------------------------------------------------------*/
			add_option( 'qp_db_version', QP_DB_VERSION );
		}

		/**
		 * Register custom user roles.
		 *
		 * @return void
		 */
		private static function create_roles() {
			add_role(
				'qp_customer',
				__( 'Customer', 'quote-pilot' ),
				array(
					'read' => true,
				)
			);
		}

		/**
		 * Seed default plugin settings.
		 *
		 * @return void
		 */
		private static function create_default_settings() {
			$defaults = array(
				'tax_rate'                   => 10,
				'tax_enabled'                => false,
				'currency_symbol'            => '$',
				'qp_delete_data_on_uninstall' => false,
				'enabled_modules'            => array(
					'services'         => true,
					'quote_calculator' => false,
					'booking_manager'  => false,
					'customer_dashboard' => false,
					'lead_capture'     => false,
					'coupons'          => false,
					'date_rules'       => false,
					'payments'         => false,
					'email_notifications' => false,
				),
			);

			$existing = get_option( 'qp_settings' );

			if ( false === $existing ) {
				add_option( 'qp_settings', $defaults );
			} else {
				$merged = array_replace_recursive( $defaults, (array) $existing );
				update_option( 'qp_settings', $merged );
			}
		}
	}

endif;
