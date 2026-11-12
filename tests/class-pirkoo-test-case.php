<?php
/**
 * Base test case: a fake app.pirkoo.com, and no other outgoing requests.
 *
 * @package Pirkoo
 */

/**
 * Every request to PIRKOO_APP_URL is recorded and answered from respond(); any other
 * request fails as if offline.
 */
abstract class Pirkoo_Test_Case extends WP_UnitTestCase {

	/**
	 * Requests the app received: method, path, headers and decoded JSON body.
	 *
	 * @var array<int, array{method: string, path: string, headers: array, body: mixed}>
	 */
	protected $requests = array();

	/**
	 * Answers still to give, by "METHOD /path", in order.
	 *
	 * @var array<string, array<int, array{0: int, 1: mixed}|WP_Error>>
	 */
	private $responses = array();

	/**
	 * Installs the fake app and starts from a store that never connected.
	 */
	public function set_up() {
		parent::set_up();
		$this->requests  = array();
		$this->responses = array();
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 1, 3 );
		delete_option( PIRKOO_OPTION );
		delete_option( PIRKOO_LOCK );
	}

	/**
	 * Runs this plugin's own admin_init callbacks, as a wp-admin page load would. WordPress's other
	 * admin_init work (update checks against wordpress.org, HTTP headers) doesn't belong in a test.
	 * They run on a wp-admin screen, during admin_init, as WordPress APIs meant for it check.
	 */
	protected function run_plugin_admin_init() {
		global $wp_filter, $wp_current_filter;
		set_current_screen( 'dashboard' );
		$wp_current_filter[] = 'admin_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- standing in for WordPress's own admin_init.
		foreach ( $wp_filter['admin_init']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_string( $callback['function'] ) && 0 === strpos( $callback['function'], 'pirkoo_' ) ) {
					call_user_func( $callback['function'] );
				}
			}
		}
		array_pop( $wp_current_filter );
		set_current_screen( 'front' );
	}

	/**
	 * Queues the app's answer to the next "$method $path".
	 *
	 * @param string            $method GET or POST.
	 * @param string            $path   Path, e.g. "/api/stores/disconnect".
	 * @param int|WP_Error      $status HTTP status, or a transport error.
	 * @param array|string|null $body   JSON body (array) or raw body (string).
	 */
	protected function respond( $method, $path, $status, $body = null ) {
		$this->responses[ $method . ' ' . $path ][] = is_int( $status ) ? array( $status, $body ) : $status;
	}

	/**
	 * The fake app (filter pre_http_request).
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Request arguments.
	 * @param string               $url  Request URL.
	 * @return array|WP_Error
	 */
	public function fake_http( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre; // Already answered by a test's own fake (e.g. wordpress.org).
		}
		if ( 0 !== strpos( $url, PIRKOO_APP_URL . '/' ) ) {
			return new WP_Error( 'http_request_failed', 'Tests are offline: ' . $url );
		}
		$request          = array(
			'method'  => $args['method'],
			'path'    => (string) wp_parse_url( $url, PHP_URL_PATH ),
			'headers' => $args['headers'],
			'body'    => isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null,
		);
		$this->requests[] = $request;

		$key = $request['method'] . ' ' . $request['path'];
		if ( empty( $this->responses[ $key ] ) ) {
			$this->fail( 'Unexpected app request: ' . $key );
		}
		$answer = array_shift( $this->responses[ $key ] );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}
		list( $status, $body ) = $answer;
		return array(
			'headers'  => array(),
			'body'     => is_array( $body ) ? wp_json_encode( $body ) : (string) $body,
			'response' => array(
				'code'    => $status,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * The app requests as "METHOD /path".
	 *
	 * @return string[]
	 */
	protected function request_lines() {
		return array_map(
			function ( $request ) {
				return $request['method'] . ' ' . $request['path'];
			},
			$this->requests
		);
	}

	/**
	 * Calls $callback, which must end in a redirect, and returns where it redirects.
	 *
	 * @param callable $callback Code that redirects (and exits otherwise).
	 * @return string
	 */
	protected function redirect_of( callable $callback ) {
		$stop = function ( $location ) {
			throw new Pirkoo_Redirect( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never printed.
		};
		add_filter( 'wp_redirect', $stop );
		try {
			$callback();
		} catch ( Pirkoo_Redirect $redirect ) {
			return $redirect->location;
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}
		$this->fail( 'No redirect.' );
	}

	/**
	 * Signs in a WooCommerce shop manager.
	 */
	protected function sign_in_shop_manager() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	/**
	 * A store connected with $secret.
	 *
	 * @param string $secret The connection secret.
	 */
	protected function connect_store( $secret = 'test-secret-0123456789-0123456789-abcd' ) {
		pirkoo_update_settings(
			array(
				'secret'       => $secret,
				'connected_at' => time() - DAY_IN_SECONDS,
			)
		);
	}

	/**
	 * Sends a POST to one of the plugin's REST routes, signed with $secret at $time unless $secret
	 * is null.
	 *
	 * @param string      $route  Route, e.g. /pirkoo/v1/collect.
	 * @param string      $body   Raw JSON body.
	 * @param string|null $secret The secret to sign with, or null for an unsigned request.
	 * @param int|null    $time   The signature's time (now when null).
	 * @return WP_REST_Response
	 */
	protected function rest_post( $route, $body = '{}', $secret = null, $time = null ) {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $body );
		if ( null !== $secret ) {
			foreach ( pirkoo_signature_headers( $secret, 'POST', '/wp-json' . $route, $body, $time ) as $name => $value ) {
				$request->set_header( $name, $value );
			}
		}
		return rest_get_server()->dispatch( $request );
	}
}
