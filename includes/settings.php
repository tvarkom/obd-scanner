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
 * The stored settings (0.3.0):
 *
 * - secret:       the connection secret shared with app.pirkoo.com, "" when not connected. Only
 *                 requests signed with it are answered (includes/connect.php).
 * - connected_at: when the store was connected (Unix seconds), 0 when not.
 * - code_hash:    sha256 of the one-time connection code while a connection is being made, "".
 * - code_expires: when that code stops working (Unix seconds).
 *
 * @return array{secret: string, connected_at: int, code_hash: string, code_expires: int}
 */
function pirkoo_settings() {
	$saved    = get_option( PIRKOO_OPTION, array() );
	$settings = wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'secret'       => '',
			'connected_at' => 0,
			'code_hash'    => '',
			'code_expires' => 0,
		)
	);
	return array(
		'secret'       => (string) $settings['secret'],
		'connected_at' => (int) $settings['connected_at'],
		'code_hash'    => (string) $settings['code_hash'],
		'code_expires' => (int) $settings['code_expires'],
	);
}

/**
 * Merges $changes into the stored settings, dropping anything else (0.2.x's gateway key and last
 * result). Not autoloaded: only the plugin's own requests read it.
 *
 * @param array<string, mixed> $changes Settings to change.
 * @return void
 */
function pirkoo_update_settings( array $changes ) {
	update_option( PIRKOO_OPTION, array_merge( pirkoo_settings(), array_intersect_key( $changes, pirkoo_settings() ) ), false );
}

/**
 * Whether the store is connected to app.pirkoo.com.
 *
 * @return bool
 */
function pirkoo_is_connected() {
	return '' !== pirkoo_settings()['secret'];
}

/**
 * After an update from 0.2.x: the gateway key and its registration token are no longer used, so
 * they're removed. A 0.2.x connection doesn't carry over: the store is connected again from the
 * Plugins screen.
 *
 * @return void
 */
function pirkoo_upgrade() {
	$saved = get_option( PIRKOO_OPTION, array() );
	if ( is_array( $saved ) && ( isset( $saved['api_key'] ) || isset( $saved['last'] ) ) ) {
		update_option(
			PIRKOO_OPTION,
			array(
				'secret'       => isset( $saved['secret'] ) ? (string) $saved['secret'] : '',
				'connected_at' => isset( $saved['secret'] ) ? (int) $saved['connected_at'] : 0,
				'code_hash'    => '',
				'code_expires' => 0,
			),
			false
		);
		delete_transient( 'pirkoo_verify_token' );
	}
}
