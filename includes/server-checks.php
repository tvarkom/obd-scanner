<?php
/**
 * Server checks for the pipeline (founder decisions of 9 Nov 2026): upload limit, PHP extensions,
 * disk space, load and memory. Read on the store's own server; numbers and extension names only.
 * Each value is null when the host doesn't let PHP read it (for example load and memory outside
 * Linux, or disk space under open_basedir).
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The PHP extensions WordPress and WooCommerce rely on, as in WordPress's Site Health; for images,
 * either imagick or gd is enough.
 */
const PIRKOO_PHP_EXTENSIONS = array( 'curl', 'dom', 'exif', 'fileinfo', 'hash', 'intl', 'json', 'mbstring', 'mysqli', 'openssl', 'pcre', 'xml', 'zip' );

/**
 * The server checks.
 *
 * @return array<string, mixed>
 */
function pirkoo_server_checks() {
	$memory = pirkoo_memory_kb();
	return array(
		'upload_max_bytes'       => (int) wp_max_upload_size(),
		'missing_php_extensions' => pirkoo_missing_php_extensions(),
		'disk_free_bytes'        => pirkoo_disk( 'free' ),
		'disk_total_bytes'       => pirkoo_disk( 'total' ),
		'load_average_1m'        => pirkoo_load_average(),
		'cpu_cores'              => pirkoo_cpu_cores(),
		'memory_total_kb'        => $memory['total'],
		'memory_available_kb'    => $memory['available'],
	);
}

/**
 * The recommended extensions that aren't loaded (an image library counts once, as "imagick or gd").
 *
 * @return string[]
 */
function pirkoo_missing_php_extensions() {
	$missing = array_values(
		array_filter(
			PIRKOO_PHP_EXTENSIONS,
			function ( $extension ) {
				return ! extension_loaded( $extension );
			}
		)
	);
	if ( ! extension_loaded( 'imagick' ) && ! extension_loaded( 'gd' ) ) {
		$missing[] = 'imagick or gd';
	}
	return $missing;
}

/**
 * Free or total bytes on the disk holding WordPress, or null when PHP may not read it.
 *
 * @param string $which free or total.
 * @return int|null
 */
function pirkoo_disk( $which ) {
	$function = 'free' === $which ? 'disk_free_space' : 'disk_total_space';
	if ( ! function_exists( $function ) ) {
		return null;
	}
	$bytes = @$function( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir or a disabled function warns; null says "not measured".
	return false === $bytes ? null : (int) $bytes;
}

/**
 * The 1-minute load average (Linux and other Unix systems), or null.
 *
 * @return float|null
 */
function pirkoo_load_average() {
	if ( ! function_exists( 'sys_getloadavg' ) ) {
		return null;
	}
	$load = sys_getloadavg();
	return is_array( $load ) ? round( (float) $load[0], 2 ) : null;
}

/**
 * CPU cores (Linux, from /proc/cpuinfo), or null.
 *
 * @return int|null
 */
function pirkoo_cpu_cores() {
	$info = pirkoo_read_proc( '/proc/cpuinfo' );
	if ( null === $info ) {
		return null;
	}
	$cores = preg_match_all( '/^processor\s*:/m', $info );
	return $cores > 0 ? $cores : null;
}

/**
 * Total and available memory in KB (Linux, from /proc/meminfo), or nulls.
 *
 * @return array{total: int|null, available: int|null}
 */
function pirkoo_memory_kb() {
	$info  = pirkoo_read_proc( '/proc/meminfo' );
	$value = function ( $key ) use ( $info ) {
		return null !== $info && preg_match( '/^' . $key . ':\s+(\d+)\s+kB/m', $info, $m ) ? (int) $m[1] : null;
	};
	return array(
		'total'     => $value( 'MemTotal' ),
		'available' => $value( 'MemAvailable' ),
	);
}

/**
 * A small /proc file's text, or null when it can't be read (not Linux, or open_basedir).
 *
 * @param string $path /proc/cpuinfo or /proc/meminfo.
 * @return string|null
 */
function pirkoo_read_proc( $path ) {
	if ( ! @is_readable( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir warns; null says "not measured".
		return null;
	}
	// A local /proc file, at most 64 KB; open_basedir may still refuse it.
	$text = @file_get_contents( $path, false, null, 0, 65536 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return is_string( $text ) ? $text : null;
}
