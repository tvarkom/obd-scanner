<?php
/**
 * The store data sent with each scan: software and database figures the dashboard and its score
 * use, as aggregate counts and sizes. No customer data, no orders, no sales.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects the metrics sent with a scan. tvarkom's scanner needs every field below to score the
 * store; the autoloaded options and background task figures each cost points when they're
 * unhealthy, and the dashboard shows them.
 *
 * @return array<string, mixed>
 */
function tvarkom_obd_collect_metrics() {
	$scheduler = tvarkom_obd_scheduler_counts();
	return array(
		'php_version'                 => PHP_VERSION,
		'autoloaded_kb'               => tvarkom_obd_autoloaded_kb(),
		'active_plugin_count'         => tvarkom_obd_active_plugin_count(),
		'object_cache_enabled'        => (bool) wp_using_ext_object_cache(),
		'object_cache_type'           => tvarkom_obd_object_cache_type(),
		'action_scheduler_pending'    => $scheduler['pending'],
		'action_scheduler_past_due'   => $scheduler['past_due'],
		'action_scheduler_failed'     => $scheduler['failed'],
		'action_scheduler_failed_7d'  => $scheduler['failed_7d'],
		'cron_disabled'               => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		'cron_oldest_overdue_seconds' => tvarkom_obd_cron_overdue_seconds(),
	);
}

/**
 * Size of the options WordPress loads on every request, in KB.
 *
 * @return float
 */
function tvarkom_obd_autoloaded_kb() {
	global $wpdb;
	// WordPress 6.6+ stores several "autoload" values; older versions use "yes".
	$values       = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
	$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- one SUM per scan; no API gives the total.
	$bytes = $wpdb->get_var(
		$wpdb->prepare( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ($placeholders)", $values )
	);
	// phpcs:enable
	return round( (int) $bytes / 1024, 1 );
}

/**
 * Active plugins, including network-activated ones.
 *
 * @return int
 */
function tvarkom_obd_active_plugin_count() {
	$count = count( (array) get_option( 'active_plugins', array() ) );
	if ( is_multisite() ) {
		$count += count( (array) get_site_option( 'active_sitewide_plugins', array() ) );
	}
	return $count;
}

/**
 * Persistent object cache backend, from the object-cache.php drop-in's code.
 *
 * @return string None | Redis | Memcached | Unknown
 */
function tvarkom_obd_object_cache_type() {
	if ( ! wp_using_ext_object_cache() ) {
		return 'None';
	}
	$dropin = WP_CONTENT_DIR . '/object-cache.php';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, first 8 KB only.
	$head = is_readable( $dropin ) ? (string) file_get_contents( $dropin, false, null, 0, 8192 ) : '';
	if ( false !== stripos( $head, 'redis' ) ) {
		return 'Redis';
	}
	if ( false !== stripos( $head, 'memcache' ) ) {
		return 'Memcached';
	}
	return 'Unknown';
}

/**
 * Action Scheduler task counts, through its own store API (works with any data store).
 *
 * @return array{pending: int, past_due: int, failed: int, failed_7d: int}
 */
function tvarkom_obd_scheduler_counts() {
	$counts = array(
		'pending'   => 0,
		'past_due'  => 0,
		'failed'    => 0,
		'failed_7d' => 0,
	);
	if ( ! class_exists( 'ActionScheduler_Store' ) || ! function_exists( 'as_get_datetime_object' ) ) {
		return $counts;
	}
	$store = ActionScheduler_Store::instance();
	$count = function ( array $query ) use ( $store ) {
		return (int) $store->query_actions( $query, 'count' );
	};

	$counts['pending']   = $count( array( 'status' => ActionScheduler_Store::STATUS_PENDING ) );
	$counts['past_due']  = $count(
		array(
			'status'       => ActionScheduler_Store::STATUS_PENDING,
			'date'         => as_get_datetime_object( '-15 minutes' ),
			'date_compare' => '<',
		)
	);
	$counts['failed']    = $count( array( 'status' => ActionScheduler_Store::STATUS_FAILED ) );
	$counts['failed_7d'] = $count(
		array(
			'status'           => ActionScheduler_Store::STATUS_FAILED,
			'modified'         => as_get_datetime_object( '-7 days' ),
			'modified_compare' => '>=',
		)
	);
	return $counts;
}

/**
 * How late the oldest due WP-Cron event is, in seconds (0 if none is late).
 *
 * @return int
 */
function tvarkom_obd_cron_overdue_seconds() {
	$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
	if ( empty( $crons ) ) {
		return 0;
	}
	return max( 0, time() - (int) min( array_keys( $crons ) ) );
}
