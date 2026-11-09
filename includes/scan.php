<?php
/**
 * Scans: one request to tvarkom when an administrator clicks the button (register first when the
 * store has no key), and the result stored for the page.
 *
 * One scan a week per store: tvarkom's gateway refuses earlier ones (a scan that fails doesn't
 * count) and says when the next one may run; the page shows that date and disables the button
 * until then. Scans run only on a click: there is no scheduled scan.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requests one scan: registers the store when it has no key (or its key stopped working), sends
 * the scan request and stores the result.
 *
 * @return string The result code: queued, or the reason it wasn't (see tvarkom_obd_messages()).
 */
function tvarkom_obd_scan() {
	if ( ! tvarkom_obd_is_connected() ) {
		return 'not_connected';
	}
	if ( ! tvarkom_obd_lock() ) {
		return 'scan_running';
	}
	$code = tvarkom_obd_send_scan();
	if ( 'invalid_key' === $code || 'website_not_allowed' === $code ) {
		// The key was replaced (the store registered again, for example from another of
		// tvarkom's plugins) or is for the address this site had before a move: register again.
		tvarkom_obd_update_settings( array( 'api_key' => '' ) );
		$code = tvarkom_obd_send_scan();
	}
	tvarkom_obd_unlock();
	return $code;
}

/**
 * One attempt: register if needed, then POST /v1/audits. Stores the result.
 *
 * @return string The result code.
 */
function tvarkom_obd_send_scan() {
	if ( '' === tvarkom_obd_settings()['api_key'] ) {
		$error = tvarkom_obd_register();
		if ( '' !== $error ) {
			return tvarkom_obd_finish_scan( $error );
		}
	}

	$response = tvarkom_obd_request(
		'POST',
		'/v1/audits',
		array(
			'product' => 'obd',
			'website' => home_url( '/' ),
			'email'   => (string) get_option( 'admin_email' ),
			'pages'   => array(
				'cart'     => wc_get_cart_url(),
				'checkout' => wc_get_checkout_url(),
			),
			'metrics' => tvarkom_obd_collect_metrics(),
		),
		tvarkom_obd_settings()['api_key']
	);
	if ( is_wp_error( $response ) ) {
		return tvarkom_obd_finish_scan( 'network' );
	}
	if ( 202 === $response['status'] ) {
		return tvarkom_obd_finish_scan( 'queued' );
	}
	return tvarkom_obd_finish_scan( tvarkom_obd_error_code( $response ) );
}

/**
 * Stores a scan request's result for the page.
 *
 * @param string $code The result code.
 * @return string $code.
 */
function tvarkom_obd_finish_scan( $code ) {
	tvarkom_obd_update_settings(
		array(
			'last' => array(
				'code' => $code,
				'time' => time(),
			),
		)
	);
	return $code;
}

/**
 * Takes the lock that lets one scan request run at a time (a second click while the first runs),
 * registration included. The lock is an option row inserted atomically, as in WordPress core's
 * WP_Upgrader::create_lock(); one older than five minutes was left by a request that died and is
 * taken over.
 *
 * @return bool Whether this request now holds the lock.
 */
function tvarkom_obd_lock() {
	global $wpdb;
	$now = (string) time();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The atomic insert is the lock; the options API can't refuse a row that exists.
	if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' )", TVARKOM_OBD_LOCK, $now ) ) ) {
		return true;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the row itself: the options cache doesn't know about the insert above.
	$since = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", TVARKOM_OBD_LOCK ) );
	if ( null === $since || (int) $since > time() - 5 * MINUTE_IN_SECONDS ) {
		return false;
	}
	// Take over a stale lock only if no other request took it over first.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-set on the lock row.
	return 1 === $wpdb->query( $wpdb->prepare( "UPDATE `$wpdb->options` SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, TVARKOM_OBD_LOCK, $since ) );
}

/**
 * Releases the lock taken by tvarkom_obd_lock().
 *
 * @return void
 */
function tvarkom_obd_unlock() {
	delete_option( TVARKOM_OBD_LOCK );
}
