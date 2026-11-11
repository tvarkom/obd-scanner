<?php
/**
 * Scans: one request to Pirkoo when an administrator clicks the button (register first when the
 * store has no key), and the result stored for the page.
 *
 * One scan a week per store: Pirkoo's gateway refuses earlier ones (a scan that fails doesn't
 * count) and says when the next one may run; the page shows that date and disables the button
 * until then. Scans run only on a click: there is no scheduled scan.
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requests one scan: registers the store when it has no key (or its key stopped working), sends
 * the scan request and stores the result.
 *
 * @return string The result code: queued, or the reason it wasn't (see pirkoo_messages()).
 */
function pirkoo_scan() {
	if ( ! pirkoo_is_connected() ) {
		return 'not_connected';
	}
	if ( ! pirkoo_lock() ) {
		return 'scan_running';
	}
	$code = pirkoo_send_scan();
	if ( 'invalid_key' === $code || 'website_not_allowed' === $code ) {
		// The key was replaced (the store registered again, for example from another copy of the
		// site) or is for the address this site had before a move: register again.
		pirkoo_update_settings( array( 'api_key' => '' ) );
		$code = pirkoo_send_scan();
	}
	pirkoo_unlock();
	return $code;
}

/**
 * One attempt: register if needed, then POST /v1/audits. Stores the result.
 *
 * @return string The result code.
 */
function pirkoo_send_scan() {
	if ( '' === pirkoo_settings()['api_key'] ) {
		$error = pirkoo_register();
		if ( '' !== $error ) {
			return pirkoo_finish_scan( $error );
		}
	}

	$response = pirkoo_request(
		'POST',
		'/v1/audits',
		array(
			'product'  => 'obd',
			'website'  => home_url( '/' ),
			'email'    => (string) get_option( 'admin_email' ),
			'pages'    => array(
				'cart'     => wc_get_cart_url(),
				'checkout' => wc_get_checkout_url(),
			),
			'metrics'  => pirkoo_collect_metrics(),
			'detailed' => pirkoo_collect_detailed(),
		),
		pirkoo_settings()['api_key']
	);
	if ( is_wp_error( $response ) ) {
		return pirkoo_finish_scan( 'network' );
	}
	if ( 202 === $response['status'] ) {
		return pirkoo_finish_scan( 'queued' );
	}
	return pirkoo_finish_scan( pirkoo_error_code( $response ) );
}

/**
 * Stores a scan request's result for the page.
 *
 * @param string $code The result code.
 * @return string $code.
 */
function pirkoo_finish_scan( $code ) {
	pirkoo_update_settings(
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
function pirkoo_lock() {
	global $wpdb;
	$now = (string) time();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The atomic insert is the lock; the options API can't refuse a row that exists.
	if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' )", PIRKOO_LOCK, $now ) ) ) {
		return true;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the row itself: the options cache doesn't know about the insert above.
	$since = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", PIRKOO_LOCK ) );
	if ( null === $since || (int) $since > time() - 5 * MINUTE_IN_SECONDS ) {
		return false;
	}
	// Take over a stale lock only if no other request took it over first.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-set on the lock row.
	return 1 === $wpdb->query( $wpdb->prepare( "UPDATE `$wpdb->options` SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, PIRKOO_LOCK, $since ) );
}

/**
 * Releases the lock taken by pirkoo_lock().
 *
 * @return void
 */
function pirkoo_unlock() {
	delete_option( PIRKOO_LOCK );
}
