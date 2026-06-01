<?php
/**
 * Database access layer.
 *
 * Every $wpdb call in the plugin goes through this class. Nothing else
 * touches the database directly. All reads use $wpdb->prepare() and all
 * writes pass an explicit format array, so callers never build SQL or
 * worry about escaping.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'QP_Database' ) ) :

	/**
	 * Class QP_Database
	 *
	 * Centralised CRUD for every custom table plus table-name helpers.
	 */
	class QP_Database {

		/*--------------------------------------------------------------
		 * Table-name helpers
		 *------------------------------------------------------------*/

		/**
		 * Return the bookings table name.
		 *
		 * @return string
		 */
		public static function bookings() {
			global $wpdb;
			return $wpdb->prefix . 'qp_bookings';
		}

		/**
		 * Return the booking items table name.
		 *
		 * @return string
		 */
		public static function booking_items() {
			global $wpdb;
			return $wpdb->prefix . 'qp_booking_items';
		}

		/**
		 * Return the leads table name.
		 *
		 * @return string
		 */
		public static function leads() {
			global $wpdb;
			return $wpdb->prefix . 'qp_leads';
		}

		/**
		 * Return the date rules table name.
		 *
		 * @return string
		 */
		public static function date_rules() {
			global $wpdb;
			return $wpdb->prefix . 'qp_date_rules';
		}

		/**
		 * Return the coupons table name.
		 *
		 * @return string
		 */
		public static function coupons() {
			global $wpdb;
			return $wpdb->prefix . 'qp_coupons';
		}

		/*--------------------------------------------------------------
		 * Column → format maps
		 *
		 * Used to whitelist writable columns and build the $wpdb format
		 * array in matching order. Any key not present here is dropped.
		 *------------------------------------------------------------*/

		/**
		 * Column formats for the bookings table.
		 *
		 * @return array<string,string>
		 */
		private static function bookings_formats() {
			return array(
				'user_id'          => '%d',
				'customer_email'   => '%s',
				'customer_name'    => '%s',
				'customer_phone'   => '%s',
				'customer_address' => '%s',
				'service_id'       => '%d',
				'preferred_date'   => '%s',
				'preferred_time'   => '%s',
				'base_price'       => '%f',
				'surcharge_total'  => '%f',
				'discount_total'   => '%f',
				'tax_rate'         => '%f',
				'tax_total'        => '%f',
				'total_price'      => '%f',
				'payment_mode'     => '%s',
				'payment_status'   => '%s',
				'amount_paid'      => '%f',
				'gateway'          => '%s',
				'gateway_txn_id'   => '%s',
				'booking_status'   => '%s',
				'assigned_cleaner' => '%s',
				'admin_notes'      => '%s',
				'consent_data'     => '%s',
				'created_at'       => '%s',
				'updated_at'       => '%s',
			);
		}

		/**
		 * Column formats for the booking_items table.
		 *
		 * @return array<string,string>
		 */
		private static function booking_items_formats() {
			return array(
				'booking_id'  => '%d',
				'item_label'  => '%s',
				'item_type'   => '%s',
				'quantity'    => '%f',
				'unit_amount' => '%f',
				'line_total'  => '%f',
			);
		}

		/**
		 * Column formats for the leads table.
		 *
		 * @return array<string,string>
		 */
		private static function leads_formats() {
			return array(
				'email'                => '%s',
				'phone'                => '%s',
				'partial_data'         => '%s',
				'consent_given'        => '%d',
				'consent_data'         => '%s',
				'lead_status'          => '%s',
				'converted_booking_id' => '%d',
				'created_at'           => '%s',
				'updated_at'           => '%s',
			);
		}

		/**
		 * Filter a data array against a format map.
		 *
		 * Drops any key not in the map (whitelist) and returns the
		 * filtered data alongside a parallel format array in the same
		 * key order — ready for $wpdb->insert()/update().
		 *
		 * @param array $data    Raw associative data.
		 * @param array $formats Column => format map.
		 * @return array{0:array,1:array} [ filtered_data, formats ]
		 */
		private static function prepare_write( array $data, array $formats ) {
			$clean        = array();
			$clean_format = array();

			foreach ( $formats as $column => $format ) {
				if ( array_key_exists( $column, $data ) ) {
					$clean[ $column ]  = $data[ $column ];
					$clean_format[]    = $format;
				}
			}

			return array( $clean, $clean_format );
		}

		/*--------------------------------------------------------------
		 * Bookings
		 *------------------------------------------------------------*/

		/**
		 * Insert a booking row.
		 *
		 * Auto-fills created_at / updated_at when the caller omits them.
		 *
		 * @param array $data Associative booking data.
		 * @return int|false New booking ID, or false on failure.
		 */
		public static function insert_booking( $data ) {
			global $wpdb;

			$now = current_time( 'mysql' );
			if ( ! isset( $data['created_at'] ) ) {
				$data['created_at'] = $now;
			}
			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = $now;
			}

			list( $clean, $format ) = self::prepare_write( $data, self::bookings_formats() );

			if ( empty( $clean ) ) {
				return false;
			}

			$result = $wpdb->insert( self::bookings(), $clean, $format );

			if ( false === $result ) {
				return false;
			}

			return (int) $wpdb->insert_id;
		}

		/**
		 * Fetch a single booking by ID.
		 *
		 * @param int $id Booking ID.
		 * @return object|null
		 */
		public static function get_booking( $id ) {
			global $wpdb;

			$table = self::bookings();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id )
			);
		}

		/**
		 * Update a booking row.
		 *
		 * Always refreshes updated_at unless the caller sets it.
		 *
		 * @param int   $id   Booking ID.
		 * @param array $data Columns to update.
		 * @return bool True on success, false on failure.
		 */
		public static function update_booking( $id, $data ) {
			global $wpdb;

			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = current_time( 'mysql' );
			}

			list( $clean, $format ) = self::prepare_write( $data, self::bookings_formats() );

			if ( empty( $clean ) ) {
				return false;
			}

			$result = $wpdb->update(
				self::bookings(),
				$clean,
				array( 'id' => (int) $id ),
				$format,
				array( '%d' )
			);

			return false !== $result;
		}

		/**
		 * Fetch all bookings for a given email (guest → account backfill).
		 *
		 * @param string $email Customer email.
		 * @return array Array of row objects.
		 */
		public static function get_bookings_by_email( $email ) {
			global $wpdb;

			$table = self::bookings();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE customer_email = %s ORDER BY created_at DESC",
					$email
				)
			);
		}

		/**
		 * Fetch all bookings for a given user (powers the dashboard).
		 *
		 * @param int $user_id WordPress user ID.
		 * @return array Array of row objects.
		 */
		public static function get_bookings_by_user( $user_id ) {
			global $wpdb;

			$table = self::bookings();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
					(int) $user_id
				)
			);
		}

		/*--------------------------------------------------------------
		 * Booking items
		 *------------------------------------------------------------*/

		/**
		 * Bulk-insert breakdown rows for a booking.
		 *
		 * Each item is whitelisted against the booking_items format map;
		 * booking_id is forced from the argument regardless of item input.
		 *
		 * @param int   $booking_id Parent booking ID.
		 * @param array $items      Array of associative item rows.
		 * @return void
		 */
		public static function insert_booking_items( $booking_id, $items ) {
			global $wpdb;

			if ( empty( $items ) || ! is_array( $items ) ) {
				return;
			}

			$table   = self::booking_items();
			$formats = self::booking_items_formats();

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$item['booking_id'] = (int) $booking_id;

				list( $clean, $format ) = self::prepare_write( $item, $formats );

				if ( empty( $clean ) ) {
					continue;
				}

				$wpdb->insert( $table, $clean, $format );
			}
		}

		/**
		 * Fetch all breakdown rows for a booking.
		 *
		 * @param int $booking_id Parent booking ID.
		 * @return array Array of row objects.
		 */
		public static function get_booking_items( $booking_id ) {
			global $wpdb;

			$table = self::booking_items();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id ASC",
					(int) $booking_id
				)
			);
		}

		/*--------------------------------------------------------------
		 * Leads
		 *------------------------------------------------------------*/

		/**
		 * Insert a lead row (only ever called when consent_given = 1).
		 *
		 * @param array $data Associative lead data.
		 * @return int|false New lead ID, or false on failure.
		 */
		public static function insert_lead( $data ) {
			global $wpdb;

			$now = current_time( 'mysql' );
			if ( ! isset( $data['created_at'] ) ) {
				$data['created_at'] = $now;
			}
			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = $now;
			}

			list( $clean, $format ) = self::prepare_write( $data, self::leads_formats() );

			if ( empty( $clean ) ) {
				return false;
			}

			$result = $wpdb->insert( self::leads(), $clean, $format );

			if ( false === $result ) {
				return false;
			}

			return (int) $wpdb->insert_id;
		}

		/**
		 * Update a lead row.
		 *
		 * @param int   $id   Lead ID.
		 * @param array $data Columns to update.
		 * @return bool True on success, false on failure.
		 */
		public static function update_lead( $id, $data ) {
			global $wpdb;

			if ( ! isset( $data['updated_at'] ) ) {
				$data['updated_at'] = current_time( 'mysql' );
			}

			list( $clean, $format ) = self::prepare_write( $data, self::leads_formats() );

			if ( empty( $clean ) ) {
				return false;
			}

			$result = $wpdb->update(
				self::leads(),
				$clean,
				array( 'id' => (int) $id ),
				$format,
				array( '%d' )
			);

			return false !== $result;
		}

		/**
		 * Fetch a lead by email.
		 *
		 * @param string $email Lead email.
		 * @return object|null
		 */
		public static function get_lead_by_email( $email ) {
			global $wpdb;

			$table = self::leads();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email )
			);
		}


		/*--------------------------------------------------------------
		 * Date rules
		 *------------------------------------------------------------*/

		/**
		 * Fetch the rule for a specific date, if any.
		 *
		 * @param string $date Date in Y-m-d format.
		 * @return object|null
		 */
		public static function get_date_rule( $date ) {
			global $wpdb;

			$table = self::date_rules();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE rule_date = %s LIMIT 1", $date )
			);
		}

		/**
		 * Fetch every date rule (feeds the calendar to JS).
		 *
		 * @return array Array of row objects.
		 */
		public static function get_all_date_rules() {
			global $wpdb;

			$table = self::date_rules();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, no user input.
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY rule_date ASC" );
		}

		/*--------------------------------------------------------------
		 * Coupons
		 *------------------------------------------------------------*/

		/**
		 * Fetch a coupon by its code.
		 *
		 * @param string $code Coupon code.
		 * @return object|null
		 */
		public static function get_coupon( $code ) {
			global $wpdb;

			$table = self::coupons();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			return $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s LIMIT 1", $code )
			);
		}

		/**
		 * Atomically increment a coupon's usage counter.
		 *
		 * @param int $id Coupon ID.
		 * @return void
		 */
		public static function increment_coupon_usage( $id ) {
			global $wpdb;

			$table = self::coupons();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal, value is prepared.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET usage_count = usage_count + 1 WHERE id = %d",
					(int) $id
				)
			);
		}

		/**
		 * Insert a date rule.
		 *
		 * @param array $data Associative date rule data.
		 * @return int|false ID of new row, or false.
		 */
		public static function insert_date_rule( $data ) {
			global $wpdb;

			$table = self::date_rules();
			$result = $wpdb->insert(
				$table,
				array(
					'rule_date'      => sanitize_text_field( $data['rule_date'] ),
					'rule_type'      => sanitize_text_field( $data['rule_type'] ),
					'surcharge_type' => sanitize_text_field( $data['surcharge_type'] ),
					'surcharge_value'=> (float) $data['surcharge_value'],
					'note'           => sanitize_text_field( $data['note'] ),
				),
				array( '%s', '%s', '%s', '%f', '%s' )
			);

			if ( false === $result ) {
				return false;
			}

			return (int) $wpdb->insert_id;
		}

		/**
		 * Delete a date rule.
		 *
		 * @param int $id Rule ID.
		 * @return bool
		 */
		public static function delete_date_rule( $id ) {
			global $wpdb;

			$table = self::date_rules();
			$result = $wpdb->delete(
				$table,
				array( 'id' => (int) $id ),
				array( '%d' )
			);

			return false !== $result;
		}

		/**
		 * Fetch all coupons from the database.
		 *
		 * @return array Array of row objects.
		 */
		public static function get_all_coupons() {
			global $wpdb;

			$table = self::coupons();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY code ASC" );
		}

		/**
		 * Insert a new coupon.
		 *
		 * @param array $data Coupon data.
		 * @return int|false ID of new row, or false.
		 */
		public static function insert_coupon( $data ) {
			global $wpdb;

			$table = self::coupons();
			$result = $wpdb->insert(
				$table,
				array(
					'code'           => strtoupper( sanitize_text_field( $data['code'] ) ),
					'discount_type'  => sanitize_text_field( $data['discount_type'] ),
					'discount_value' => (float) $data['discount_value'],
					'usage_limit'    => (int) $data['usage_limit'],
					'usage_count'    => 0,
					'expires_at'     => ! empty( $data['expires_at'] ) ? sanitize_text_field( $data['expires_at'] ) : null,
					'active'         => ! empty( $data['active'] ) ? 1 : 0,
				),
				array( '%s', '%s', '%f', '%d', '%d', '%s', '%d' )
			);

			if ( false === $result ) {
				return false;
			}

			return (int) $wpdb->insert_id;
		}

		/**
		 * Update an existing coupon.
		 *
		 * @param int   $id   Coupon ID.
		 * @param array $data Coupon data.
		 * @return bool
		 */
		public static function update_coupon( $id, $data ) {
			global $wpdb;

			$table = self::coupons();
			
			$update = array();
			$format = array();
			
			$map = array(
				'code'           => '%s',
				'discount_type'  => '%s',
				'discount_value' => '%f',
				'usage_limit'    => '%d',
				'usage_count'    => '%d',
				'expires_at'     => '%s',
				'active'         => '%d',
			);
			
			foreach ( $map as $col => $fmt ) {
				if ( array_key_exists( $col, $data ) ) {
					if ( 'code' === $col ) {
						$update[ $col ] = strtoupper( sanitize_text_field( $data[ $col ] ) );
					} elseif ( 'expires_at' === $col ) {
						$update[ $col ] = ! empty( $data[ $col ] ) ? sanitize_text_field( $data[ $col ] ) : null;
					} elseif ( 'discount_value' === $col ) {
						$update[ $col ] = (float) $data[ $col ];
					} elseif ( in_array( $col, array( 'usage_limit', 'usage_count', 'active' ), true ) ) {
						$update[ $col ] = (int) $data[ $col ];
					} else {
						$update[ $col ] = sanitize_text_field( $data[ $col ] );
					}
					$format[] = $fmt;
				}
			}

			$result = $wpdb->update(
				$table,
				$update,
				array( 'id' => (int) $id ),
				$format,
				array( '%d' )
			);

			return false !== $result;
		}

		/**
		 * Delete a coupon.
		 *
		 * @param int $id Coupon ID.
		 * @return bool
		 */
		public static function delete_coupon( $id ) {
			global $wpdb;

			$table = self::coupons();
			$result = $wpdb->delete(
				$table,
				array( 'id' => (int) $id ),
				array( '%d' )
			);

			return false !== $result;
		}
	}

endif;
