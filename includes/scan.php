<?php
/**
 * Scans: one request to tvarkom (register first when the store has no key), the result stored for
 * the page, and the weekly schedule (WP-Cron). Only after an administrator connected the store.
 *
 * One scan a week per store: tvarkom's gateway refuses earlier ones (a scan that fails doesn't
 * count) with the date the next one may run, and the schedule follows that date.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Codes after which retrying soon can't help: the next try is a week later.
 */
const TVARKOM_OBD_LASTING_ERRORS = array( 'not_woocommerce', 'invalid_website', 'invalid_verify_url', 'client_disabled', 'verification_failed', 'invalid_email' );

/**
 * Requests one scan: registers the store when it has no key (or its key stopped working), sends
 * the scan request, stores the result and schedules the next scan.
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
 * One attempt: register if needed, then POST /v1/audits. Stores the result and the next scan.
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
	$code = tvarkom_obd_error_code( $response );
	$next = 'too_soon' === $code && isset( $response['body']['next_eligible_at'] ) ? (int) $response['body']['next_eligible_at'] : 0;
	return tvarkom_obd_finish_scan( $code, $next );
}

/**
 * Stores a scan request's result and schedules the next scan from it.
 *
 * @param string $code          The result code.
 * @param int    $next_eligible For too_soon: when the gateway allows the next scan (Unix seconds).
 * @return string $code.
 */
function tvarkom_obd_finish_scan( $code, $next_eligible = 0 ) {
	tvarkom_obd_update_settings(
		array(
			'last' => array(
				'code' => $code,
				'time' => time(),
			),
		)
	);
	tvarkom_obd_schedule( tvarkom_obd_next_attempt( $code, $next_eligible ) );
	return $code;
}

/**
 * When to scan next after a result (Unix seconds).
 *
 * @param string $code          The result code.
 * @param int    $next_eligible For too_soon: the gateway's date.
 * @return int
 */
function tvarkom_obd_next_attempt( $code, $next_eligible = 0 ) {
	$margin = 5 * MINUTE_IN_SECONDS; // So the gateway's week has surely passed.
	if ( 'queued' === $code ) {
		return time() + WEEK_IN_SECONDS + $margin;
	}
	if ( 'too_soon' === $code && $next_eligible > time() ) {
		return $next_eligible + $margin;
	}
	if ( 'rate_limited' === $code ) {
		return time() + DAY_IN_SECONDS;
	}
	if ( in_array( $code, TVARKOM_OBD_LASTING_ERRORS, true ) ) {
		return time() + WEEK_IN_SECONDS;
	}
	// Network, busy, server…: try again later the same day.
	return time() + 6 * HOUR_IN_SECONDS;
}

/**
 * Schedules the next scan at $timestamp, replacing any scheduled one.
 *
 * @param int $timestamp Unix seconds.
 * @return void
 */
function tvarkom_obd_schedule( $timestamp ) {
	wp_clear_scheduled_hook( TVARKOM_OBD_CRON );
	wp_schedule_single_event( $timestamp, TVARKOM_OBD_CRON );
}

/**
 * Keeps a scan scheduled while the store is connected, for example after the plugin was
 * deactivated and activated again: the scan then runs soon and the gateway's week decides.
 *
 * @return void
 */
function tvarkom_obd_ensure_schedule() {
	if ( tvarkom_obd_is_connected() && false === wp_next_scheduled( TVARKOM_OBD_CRON ) ) {
		tvarkom_obd_schedule( time() + MINUTE_IN_SECONDS );
	}
}

/**
 * After a scan failed on tvarkom's side, try again within the hour instead of next week (a failed
 * scan doesn't count toward the week; the gateway allows 3 a day).
 *
 * @return void
 */
function tvarkom_obd_retry_failed_scan() {
	$next = wp_next_scheduled( TVARKOM_OBD_CRON );
	if ( false === $next || $next > time() + HOUR_IN_SECONDS ) {
		tvarkom_obd_schedule( time() + HOUR_IN_SECONDS );
	}
}

/**
 * The scheduled scan (WP-Cron).
 *
 * @return void
 */
function tvarkom_obd_scheduled_scan() {
	if ( function_exists( 'wc_set_time_limit' ) ) {
		wc_set_time_limit( 120 );
	}
	tvarkom_obd_scan();
}

/**
 * On deactivation: no scan stays scheduled. The settings stay, so activating again resumes.
 *
 * @return void
 */
function tvarkom_obd_deactivate() {
	wp_clear_scheduled_hook( TVARKOM_OBD_CRON );
}

/**
 * Takes the lock that lets one scan request run at a time (a click and the scheduled scan alike),
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
