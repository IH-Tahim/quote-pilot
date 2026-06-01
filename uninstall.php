<?php
/**
 * QuotePilot – Uninstall handler.
 *
 * Fired when the plugin is deleted through the WordPress admin.
 * Respects the qp_delete_data_on_uninstall setting; if false, all
 * tables, options, and roles are left intact.
 *
 * @package QuotePilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$qp_settings = get_option( 'qp_settings', array() );

$qp_delete_data = isset( $qp_settings['qp_delete_data_on_uninstall'] )
	? (bool) $qp_settings['qp_delete_data_on_uninstall']
	: false;

if ( ! $qp_delete_data ) {
	return;
}

global $wpdb;

/*--------------------------------------------------------------
 * Drop custom tables
 *------------------------------------------------------------*/
$qp_tables = array(
	$wpdb->prefix . 'qp_bookings',
	$wpdb->prefix . 'qp_booking_items',
	$wpdb->prefix . 'qp_leads',
	$wpdb->prefix . 'qp_date_rules',
	$wpdb->prefix . 'qp_coupons',
);

foreach ( $qp_tables as $qp_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$qp_table}" );
}

/*--------------------------------------------------------------
 * Delete options
 *------------------------------------------------------------*/
delete_option( 'qp_db_version' );
delete_option( 'qp_settings' );

/*--------------------------------------------------------------
 * Remove custom role
 *------------------------------------------------------------*/
remove_role( 'qp_customer' );
