<?php
/**
 * WordPress, database and security checks for the pipeline (founder decisions of 9 Nov 2026):
 * paused plugins and themes, fatal errors in the last 7 days, automatic updates, permalinks,
 * database latency and clutter, core files against WordPress.org's checksums, wp-config.php
 * permissions, an "admin" username and open registration. Counts and settings only: never file,
 * plugin or user names, log lines or messages.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WordPress, database and security checks.
 *
 * @return array<string, mixed>
 */
function tvarkom_obd_wordpress_checks() {
	$clutter   = tvarkom_obd_database_clutter();
	$checksums = tvarkom_obd_core_file_changes();
	return array(
		'paused_plugins'      => tvarkom_obd_paused( 'plugins' ),
		'paused_themes'       => tvarkom_obd_paused( 'themes' ),
		'fatal_errors_7d'     => tvarkom_obd_fatal_errors_7d(),
		'core_auto_updates'   => tvarkom_obd_core_auto_updates(),
		'plugin_auto_updates' => count( (array) get_site_option( 'auto_update_plugins', array() ) ),
		'permalinks_plain'    => '' === (string) get_option( 'permalink_structure', '' ),
		'db_latency_ms'       => tvarkom_obd_db_latency_ms(),
		'spam_comments'       => $clutter['spam'],
		'auto_drafts'         => $clutter['auto_drafts'],
		'old_revisions'       => $clutter['revisions'],
		'core_files_modified' => $checksums['modified'],
		'core_files_missing'  => $checksums['missing'],
		'wp_config_mode'      => tvarkom_obd_wp_config_mode(),
		'admin_username'      => (bool) username_exists( 'admin' ),
		'registration_open'   => (bool) get_option( 'users_can_register' ),
		'registration_role'   => sanitize_key( (string) get_option( 'default_role', 'subscriber' ) ),
	);
}

/**
 * Plugins or themes WordPress paused after a fatal error (its recovery mode), as a count.
 *
 * @param string $type plugins or themes.
 * @return int
 */
function tvarkom_obd_paused( $type ) {
	$storage = 'plugins' === $type ? wp_paused_plugins() : wp_paused_themes();
	return count( (array) $storage->get_all() );
}

/**
 * Fatal errors logged in the last 7 days, read from the end (1 MB) of the PHP error log WordPress
 * writes to (WP_DEBUG_LOG) or PHP's own; null when there is no readable log. Only the count leaves
 * the store, never a log line.
 *
 * @return int|null
 */
function tvarkom_obd_fatal_errors_7d() {
	// WP_DEBUG_LOG is true (wp-content/debug.log) or a log path; else PHP's own log.
	$setting = defined( 'WP_DEBUG_LOG' ) ? constant( 'WP_DEBUG_LOG' ) : false;
	if ( is_string( $setting ) && '' !== $setting ) {
		$log = $setting;
	} elseif ( true === $setting ) {
		$log = WP_CONTENT_DIR . '/debug.log';
	} else {
		$log = (string) ini_get( 'error_log' );
	}
	if ( '' === $log || ! @is_readable( $log ) || ! is_file( $log ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir warns; null says "not measured".
		return null;
	}
	$size = (int) filesize( $log );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local log file, its last 1 MB only.
	$tail = file_get_contents( $log, false, null, max( 0, $size - 1048576 ) );
	if ( ! is_string( $tail ) ) {
		return null;
	}
	$since = time() - 7 * DAY_IN_SECONDS;
	$count = 0;
	if ( preg_match_all( '/^\[([^\]]+)\] PHP Fatal error/m', $tail, $matches ) ) {
		foreach ( $matches[1] as $when ) {
			$at = strtotime( $when );
			if ( $at && $at >= $since ) {
				++$count;
			}
		}
	}
	return $count;
}

/**
 * How WordPress updates its core on its own: "disabled", "minor" (security and maintenance
 * releases) or "all".
 *
 * @return string
 */
function tvarkom_obd_core_auto_updates() {
	if ( ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) || ! wp_is_file_mod_allowed( 'automatic_updater' ) ) {
		return 'disabled';
	}
	if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
		if ( false === WP_AUTO_UPDATE_CORE ) {
			return 'disabled';
		}
		if ( true === WP_AUTO_UPDATE_CORE || 'beta' === WP_AUTO_UPDATE_CORE || 'rc' === WP_AUTO_UPDATE_CORE ) {
			return 'all';
		}
	}
	return 'enabled' === get_site_option( 'auto_update_core_major' ) ? 'all' : 'minor';
}

/**
 * The median time of three trivial queries, in milliseconds: the round trip to the database.
 *
 * @return float
 */
function tvarkom_obd_db_latency_ms() {
	global $wpdb;
	$times = array();
	for ( $i = 0; $i < 3; $i++ ) {
		$start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the round trip itself is what's measured.
		$wpdb->get_var( 'SELECT 1' );
		$times[] = ( microtime( true ) - $start ) * 1000;
	}
	sort( $times );
	return round( $times[1], 2 );
}

/**
 * Spam comments, auto-drafts and revisions older than 30 days, as counts.
 *
 * @return array{spam: int, auto_drafts: int, revisions: int}
 */
function tvarkom_obd_database_clutter() {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- three COUNTs per scan.
	$spam        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam' ) );
	$auto_drafts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s", 'auto-draft' ) );
	$revisions   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_modified_gmt < %s", 'revision', $cutoff ) );
	// phpcs:enable
	return array(
		'spam'        => $spam,
		'auto_drafts' => $auto_drafts,
		'revisions'   => $revisions,
	);
}

/**
 * Core files that differ from, or are missing against, WordPress.org's official checksums for this
 * version and locale. wp-content isn't checked. Nulls when the checksums couldn't be fetched.
 *
 * @return array{modified: int|null, missing: int|null}
 */
function tvarkom_obd_core_file_changes() {
	$checksums = tvarkom_obd_core_checksums( get_bloginfo( 'version' ), get_locale() );
	if ( null === $checksums && 'en_US' !== get_locale() ) {
		$checksums = tvarkom_obd_core_checksums( get_bloginfo( 'version' ), 'en_US' );
	}
	if ( null === $checksums ) {
		return array(
			'modified' => null,
			'missing'  => null,
		);
	}
	$modified = 0;
	$missing  = 0;
	foreach ( $checksums as $file => $md5 ) {
		if ( 0 === strpos( $file, 'wp-content/' ) ) {
			continue;
		}
		$path = ABSPATH . $file;
		if ( ! file_exists( $path ) ) {
			++$missing;
		} elseif ( md5_file( $path ) !== $md5 ) {
			++$modified;
		}
	}
	return array(
		'modified' => $modified,
		'missing'  => $missing,
	);
}

/**
 * WordPress.org's checksums of a release's files (file => md5), from the same API WordPress's
 * get_core_checksums() uses; null when they can't be fetched. Fetched here because
 * get_core_checksums() raises a PHP warning when wordpress.org is unreachable, which on a store
 * that blocks outgoing requests would land in the error log on every scan.
 *
 * @param string $version WordPress version.
 * @param string $locale  Locale, e.g. en_US.
 * @return array<string, string>|null
 */
function tvarkom_obd_core_checksums( $version, $locale ) {
	$url      = add_query_arg(
		array(
			'version' => $version,
			'locale'  => $locale,
		),
		'https://api.wordpress.org/core/checksums/1.0/'
	);
	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || empty( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
		return null;
	}
	$checksums = array();
	foreach ( $body['checksums'] as $file => $md5 ) {
		if ( is_string( $file ) && is_string( $md5 ) ) {
			$checksums[ $file ] = $md5;
		}
	}
	return empty( $checksums ) ? null : $checksums;
}

/**
 * The permissions of wp-config.php as an octal string (e.g. "0640"), from WordPress's folder or the one
 * above it; null when it isn't found.
 *
 * @return string|null
 */
function tvarkom_obd_wp_config_mode() {
	foreach ( array( ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php' ) as $path ) {
		if ( @file_exists( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir warns for the folder above.
			$perms = fileperms( $path );
			return false === $perms ? null : sprintf( '%04o', $perms & 0777 );
		}
	}
	return null;
}
