<?php
/**
 * REST route: GET /wp-json/pirkoo/v1/verify, the domain check for registration.
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain check for registration: the gateway fetches this URL and expects the token it handed
 * to this site a moment earlier (pirkoo_register()). Public on purpose: it only returns a
 * short-lived, single-use token that is useless to anyone else, and 404 when no registration is
 * in progress.
 *
 * @return void
 */
function pirkoo_register_routes() {
	register_rest_route(
		'pirkoo/v1',
		'/verify',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'pirkoo_rest_verify',
		)
	);
}

/**
 * Answers the domain check.
 *
 * @return array{token: string}|WP_Error
 */
function pirkoo_rest_verify() {
	$token = get_transient( PIRKOO_TOKEN );
	if ( ! is_string( $token ) || '' === $token ) {
		return new WP_Error( 'pirkoo_no_registration', 'No registration in progress.', array( 'status' => 404 ) );
	}
	return array( 'token' => $token );
}
