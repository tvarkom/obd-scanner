<?php
/**
 * Removes the plugin's data from this store: its settings, key, lock, profiler leftovers and the
 * table of daily shop totals. Records on
 * tvarkom's side follow the retention periods in tvarkom's Privacy Policy.
 *
 * @package OBDScanner
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tvarkom_obd' );
delete_option( 'tvarkom_obd_scan_lock' );
delete_transient( 'tvarkom_obd_verify_token' );
delete_option( 'tvarkom_obd_funnel_since' );
delete_transient( 'tvarkom_obd_profile_token' );
delete_transient( 'tvarkom_obd_profile_result' );
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the plugin's own counter table on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'tvarkom_obd_funnel' ) );
