<?php
/**
 * Removes the plugin's data from this store: its settings, key, lock, profiler leftovers and the
 * table of daily shop totals. Records on
 * Pirkoo's side follow the retention periods in Pirkoo's Privacy Policy.
 *
 * @package Pirkoo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'pirkoo' );
delete_option( 'pirkoo_scan_lock' );
delete_transient( 'pirkoo_verify_token' );
delete_option( 'pirkoo_funnel_since' );
delete_transient( 'pirkoo_profile_token' );
delete_transient( 'pirkoo_profile_result' );
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the plugin's own counter table on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'pirkoo_funnel' ) );
