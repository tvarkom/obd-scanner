<?php
/**
 * Removes the plugin's data from this store: its settings, key and lock. Records on
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
