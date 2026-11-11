<?php
/**
 * Site checks for the pipeline (founder decisions of 9 Nov 2026), sent with each scan as
 * detailed.site_health: counts and settings only, never names. Pirkoo's scanner turns them into
 * pipeline jobs that cost no points (yellow when they find a problem).
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site checks.
 *
 * @return array<string, mixed>
 */
function pirkoo_site_health() {
	$tables = pirkoo_table_engines();
	$checks = array(
		'core_update_available'  => pirkoo_core_update_available(),
		'theme_updates'          => pirkoo_theme_updates(),
		'wp_debug'               => defined( 'WP_DEBUG' ) && WP_DEBUG,
		// WordPress shows errors when WP_DEBUG is on unless WP_DEBUG_DISPLAY is false.
		'debug_display'          => ! defined( 'WP_DEBUG_DISPLAY' ) || false !== WP_DEBUG_DISPLAY,
		'search_engines_blocked' => '0' === (string) get_option( 'blog_public', '1' ),
		// production, staging, development or local (WP_ENVIRONMENT_TYPE): blocked search engines and
		// debug output are expected on a non-production site, so Pirkoo doesn't count them there.
		'environment_type'       => wp_get_environment_type(),
		'myisam_tables'          => $tables['myisam'],
		'non_utf8mb4_tables'     => $tables['non_utf8mb4'],
		'expired_transients'     => pirkoo_expired_transients(),
		'administrators'         => pirkoo_administrators(),
		'file_editor_enabled'    => ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) && wp_is_file_mod_allowed( 'capability_edit_themes' ),
	);
	// The server, WordPress, database and security checks (includes/server-checks.php, wordpress-checks.php).
	return array_merge( $checks, pirkoo_server_checks(), pirkoo_wordpress_checks() );
}

/**
 * Whether WordPress's last update check found a newer version, from its own cached result (no
 * request is made); null before any check.
 *
 * @return bool|null
 */
function pirkoo_core_update_available() {
	$check = get_site_transient( 'update_core' );
	if ( ! is_object( $check ) || empty( $check->updates ) || ! is_array( $check->updates ) ) {
		return null;
	}
	foreach ( $check->updates as $update ) {
		if ( is_object( $update ) && isset( $update->response ) && 'upgrade' === $update->response ) {
			return true;
		}
	}
	return false;
}

/**
 * Themes with an update waiting, from WordPress's own cached check; null before any check.
 *
 * @return int|null
 */
function pirkoo_theme_updates() {
	$check = get_site_transient( 'update_themes' );
	if ( ! is_object( $check ) || ! isset( $check->response ) || ! is_array( $check->response ) ) {
		return null;
	}
	return count( $check->response );
}

/**
 * This site's tables on the MyISAM engine and not on a utf8mb4 collation, from the table sizes
 * MySQL already keeps.
 *
 * @return array{myisam: int|null, non_utf8mb4: int|null}
 */
function pirkoo_table_engines() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read of table metadata per scan.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(ENGINE = 'MyISAM') AS myisam, SUM(TABLE_COLLATION NOT LIKE %s) AS non_utf8mb4 FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s AND TABLE_TYPE = 'BASE TABLE'",
			'utf8mb4%',
			DB_NAME,
			$wpdb->esc_like( $wpdb->prefix ) . '%'
		)
	);
	if ( ! $row ) {
		return array(
			'myisam'      => null,
			'non_utf8mb4' => null,
		);
	}
	return array(
		'myisam'      => (int) $row->myisam,
		'non_utf8mb4' => (int) $row->non_utf8mb4,
	);
}

/**
 * Transients whose expiry has passed but which are still in the options table (single site and
 * network ones in this site's table).
 *
 * @return int
 */
function pirkoo_expired_transients() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one COUNT per scan; no API lists expired transients.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND CAST( option_value AS UNSIGNED ) < %d",
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
			time()
		)
	);
}

/**
 * User accounts with the Administrator role on this site (a count; no user is read).
 *
 * @return int
 */
function pirkoo_administrators() {
	$query = new WP_User_Query(
		array(
			'role'        => 'administrator',
			'number'      => 1,
			'fields'      => 'ID',
			'count_total' => true,
		)
	);
	return (int) $query->get_total();
}
