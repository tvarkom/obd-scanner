<?php
/**
 * Stored settings (option pirkoo).
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The stored settings:
 *
 * - api_key:      the store's key from Pirkoo, "" before registration or after it was revoked.
 * - connected_at: when an administrator clicked "Connect and scan" (Unix seconds), 0 before. The
 *                 plugin contacts Pirkoo only once this is set.
 * - last:         the latest scan request's result, {code, time}.
 *
 * @return array{api_key: string, connected_at: int, last: array<string, mixed>}
 */
function pirkoo_settings() {
	$saved    = get_option( PIRKOO_OPTION, array() );
	$settings = wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'api_key'      => '',
			'connected_at' => 0,
			'last'         => array(),
		)
	);
	return array(
		'api_key'      => (string) $settings['api_key'],
		'connected_at' => (int) $settings['connected_at'],
		'last'         => is_array( $settings['last'] ) ? $settings['last'] : array(),
	);
}

/**
 * Merges $changes into the stored settings. Not autoloaded: only the plugin's page and its
 * scan read it.
 *
 * @param array<string, mixed> $changes Settings to change.
 * @return void
 */
function pirkoo_update_settings( array $changes ) {
	update_option( PIRKOO_OPTION, array_merge( pirkoo_settings(), $changes ), false );
}

/**
 * Whether an administrator connected the store (clicked "Connect and scan").
 *
 * @return bool
 */
function pirkoo_is_connected() {
	return pirkoo_settings()['connected_at'] > 0;
}
