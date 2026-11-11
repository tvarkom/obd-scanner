<?php
/**
 * The Pirkoo API gateway client: requests, registration (this domain's own key) and the
 * dashboard's results (GET /v1/results).
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a request to the gateway.
 *
 * @param string                    $method  GET or POST.
 * @param string                    $path    Path, e.g. "/v1/audits".
 * @param array<string, mixed>|null $body    JSON body for POST.
 * @param string                    $api_key Bearer key, or "" for none.
 * @param int                       $timeout Seconds to wait.
 * @return array{status: int, body: array<string, mixed>}|WP_Error
 */
function pirkoo_request( $method, $path, $body = null, $api_key = '', $timeout = 45 ) {
	$headers = array();
	if ( '' !== $api_key ) {
		$headers['Authorization'] = 'Bearer ' . $api_key;
	}
	$args = array(
		'method'  => $method,
		'timeout' => $timeout,
		'headers' => $headers,
	);
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}
	$response = wp_remote_request( PIRKOO_API_URL . $path, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $response ),
		'body'   => is_array( $decoded ) ? $decoded : array(),
	);
}

/**
 * The gateway's error code in a response, or $fallback.
 *
 * @param array{status: int, body: array<string, mixed>} $response From pirkoo_request().
 * @param string                                         $fallback Code when the body has none.
 * @return string
 */
function pirkoo_error_code( array $response, $fallback = 'server' ) {
	return isset( $response['body']['error'] ) && is_string( $response['body']['error'] ) ? sanitize_key( $response['body']['error'] ) : $fallback;
}

/**
 * Registers this store with Pirkoo and stores its key. The gateway hands out a token, then
 * fetches it back from this site's REST API (includes/rest.php) to check the request came from
 * the store's own domain. The site's admin email is the account's contact address; no report
 * is ever emailed to it.
 *
 * @return string "" on success, otherwise an error code.
 */
function pirkoo_register() {
	$start = pirkoo_request(
		'POST',
		'/v1/register',
		array(
			'site_url'   => home_url( '/' ),
			'email'      => (string) get_option( 'admin_email' ),
			'verify_url' => rest_url( 'pirkoo/v1/verify' ),
		)
	);
	if ( is_wp_error( $start ) ) {
		return 'network';
	}
	if ( 201 !== $start['status'] || empty( $start['body']['registration_id'] ) || empty( $start['body']['token'] ) ) {
		return pirkoo_error_code( $start );
	}

	set_transient( PIRKOO_TOKEN, (string) $start['body']['token'], 10 * MINUTE_IN_SECONDS );
	$verify = pirkoo_request( 'POST', '/v1/register/verify', array( 'registration_id' => (string) $start['body']['registration_id'] ) );
	delete_transient( PIRKOO_TOKEN );

	if ( is_wp_error( $verify ) ) {
		return 'network';
	}
	if ( 200 !== $verify['status'] || empty( $verify['body']['api_key'] ) || ! is_string( $verify['body']['api_key'] ) ) {
		return pirkoo_error_code( $verify );
	}
	pirkoo_update_settings( array( 'api_key' => $verify['body']['api_key'] ) );
	return '';
}

/**
 * The store's dashboard data from GET /v1/results, checked for shape:
 *
 * - status:       none | scanning | failed | ready
 * - next_scan_at: Unix seconds, 0 when a scan may run now
 * - latest:       null, or {completed_at (Unix seconds), score (int|null), level, metrics, lost_points,
 *                 analysis (the full analysis, pirkoo_parse_analysis(), or null before 0.2.0's scans)}
 * - history:      list of {completed_at, score}, oldest first
 *
 * @param int $timeout Seconds to wait for the gateway.
 * @return array{status: string, next_scan_at: int, latest: array<string, mixed>|null, history: array<int, array{completed_at: int, score: int, model: int}>}|string The data, or an error code.
 */
function pirkoo_fetch_results( $timeout = 10 ) {
	$key = pirkoo_settings()['api_key'];
	if ( '' === $key ) {
		return 'invalid_key';
	}
	$response = pirkoo_request( 'GET', '/v1/results', null, $key, $timeout );
	if ( is_wp_error( $response ) ) {
		return 'network';
	}
	if ( 200 !== $response['status'] ) {
		return pirkoo_error_code( $response );
	}
	return pirkoo_parse_results( $response['body'] );
}

/**
 * Checks GET /v1/results' body (see pirkoo_fetch_results()). Values the plugin doesn't
 * know are dropped; everything is escaped again when shown.
 *
 * @param array<string, mixed> $body Decoded JSON.
 * @return array{status: string, next_scan_at: int, latest: array<string, mixed>|null, history: array<int, array{completed_at: int, score: int, model: int}>}
 */
function pirkoo_parse_results( array $body ) {
	$status = isset( $body['status'] ) && in_array( $body['status'], array( 'none', 'scanning', 'failed', 'ready' ), true ) ? $body['status'] : 'none';
	$time   = function ( $value ) {
		$ts = is_string( $value ) ? strtotime( $value ) : false;
		return $ts ? $ts : 0;
	};
	$score  = function ( $value ) {
		return is_numeric( $value ) && $value >= 0 && $value <= 100 ? (int) $value : null;
	};
	// The scoring model a score came from (score model 2 since 10 Nov 2026); 1 when not said.
	$model = function ( $value ) {
		return is_int( $value ) && $value >= 1 && $value <= 99 ? $value : 1;
	};

	$latest = null;
	if ( isset( $body['latest'] ) && is_array( $body['latest'] ) ) {
		$l       = $body['latest'];
		$metrics = array();
		foreach ( isset( $l['metrics'] ) && is_array( $l['metrics'] ) ? $l['metrics'] : array() as $id => $value ) {
			if ( is_string( $id ) && ( is_int( $value ) || is_float( $value ) || is_string( $value ) ) ) {
				$metrics[ sanitize_key( $id ) ] = $value;
			}
		}
		$lost = array();
		foreach ( isset( $l['lost_points'] ) && is_array( $l['lost_points'] ) ? $l['lost_points'] : array() as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) && is_string( $item['id'] ) ) {
				$lost[] = array(
					'id'     => sanitize_key( $item['id'] ),
					'points' => isset( $item['points'] ) && is_numeric( $item['points'] ) ? (int) $item['points'] : null,
				);
			}
		}
		$latest = array(
			'completed_at' => $time( isset( $l['completed_at'] ) ? $l['completed_at'] : null ),
			'score'        => $score( isset( $l['score'] ) ? $l['score'] : null ),
			'model'        => $model( isset( $l['score_model'] ) ? $l['score_model'] : null ),
			'level'        => isset( $l['level'] ) && in_array( $l['level'], array( 'PASS', 'WARNING', 'CRITICAL_RISK' ), true ) ? $l['level'] : '',
			'metrics'      => $metrics,
			'lost_points'  => $lost,
			'analysis'     => pirkoo_parse_analysis( isset( $l['analysis'] ) ? $l['analysis'] : null ),
		);
	}

	$history = array();
	foreach ( isset( $body['history'] ) && is_array( $body['history'] ) ? $body['history'] : array() as $point ) {
		if ( ! is_array( $point ) ) {
			continue;
		}
		$at = $time( isset( $point['completed_at'] ) ? $point['completed_at'] : null );
		$s  = $score( isset( $point['score'] ) ? $point['score'] : null );
		if ( $at > 0 && null !== $s ) {
			$history[] = array(
				'completed_at' => $at,
				'score'        => $s,
				'model'        => $model( isset( $point['score_model'] ) ? $point['score_model'] : null ),
			);
		}
	}

	return array(
		'status'       => $status,
		'next_scan_at' => $time( isset( $body['next_scan_at'] ) ? $body['next_scan_at'] : null ),
		'latest'       => $latest,
		'history'      => $history,
	);
}
