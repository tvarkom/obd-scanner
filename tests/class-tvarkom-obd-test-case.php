<?php
/**
 * Base test case: a fake tvarkom gateway, and no other outgoing requests.
 *
 * @package OBDScanner
 */

/**
 * Every request to TVARKOM_OBD_API_URL is recorded and answered from respond(); any other
 * request fails as if offline.
 */
abstract class Tvarkom_OBD_Test_Case extends WP_UnitTestCase {

	/**
	 * Requests the gateway received: method, path, headers and decoded JSON body.
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
	 * Installs the fake gateway and starts from a store that never connected.
	 */
	public function set_up() {
		parent::set_up();
		$this->requests  = array();
		$this->responses = array();
		add_filter( 'pre_http_request', array( $this, 'fake_http' ), 1, 3 );
		// WordPress's own update checks run on admin_init and would ask wordpress.org; tests are offline.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		delete_option( TVARKOM_OBD_OPTION );
		delete_option( TVARKOM_OBD_LOCK );
	}

	/**
	 * Queues the gateway's answer to the next "$method $path".
	 *
	 * @param string            $method GET or POST.
	 * @param string            $path   Path, e.g. "/v1/audits".
	 * @param int|WP_Error      $status HTTP status, or a transport error.
	 * @param array|string|null $body   JSON body (array) or raw body (string).
	 */
	protected function respond( $method, $path, $status, $body = null ) {
		$this->responses[ $method . ' ' . $path ][] = is_int( $status ) ? array( $status, $body ) : $status;
	}

	/**
	 * The fake gateway (filter pre_http_request).
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Request arguments.
	 * @param string               $url  Request URL.
	 * @return array|WP_Error
	 */
	public function fake_http( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, TVARKOM_OBD_API_URL . '/' ) ) {
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
			$this->fail( 'Unexpected gateway request: ' . $key );
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
	 * The gateway requests as "METHOD /path".
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
	 * Queues a successful registration: the gateway fetches the store's verify route (checked
	 * here) and issues $key.
	 *
	 * @param string $key The key the gateway issues.
	 */
	protected function respond_registration( $key = 'tvk_test' ) {
		$this->respond(
			'POST',
			'/v1/register',
			201,
			array(
				'registration_id' => 'reg_1',
				'token'           => 'tok_1',
				'expires_in'      => 600,
			)
		);
		$this->respond(
			'POST',
			'/v1/register/verify',
			200,
			array(
				'api_key' => $key,
				'plan'    => 'free',
				'email'   => 'admin@example.org',
			)
		);
	}

	/**
	 * Calls $callback, which must end in a redirect, and returns the redirect's obd_result.
	 *
	 * @param callable $callback Code that redirects (and exits otherwise).
	 * @return string
	 */
	protected function result_of_redirect( callable $callback ) {
		$stop = function ( $location ) {
			throw new Tvarkom_OBD_Redirect( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never printed.
		};
		add_filter( 'wp_redirect', $stop );
		try {
			$callback();
		} catch ( Tvarkom_OBD_Redirect $redirect ) {
			parse_str( (string) wp_parse_url( $redirect->location, PHP_URL_QUERY ), $query );
			return isset( $query['obd_result'] ) ? (string) $query['obd_result'] : '';
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
	 * A connected store with a key.
	 */
	protected function connect_store() {
		tvarkom_obd_update_settings(
			array(
				'api_key'      => 'tvk_test',
				'connected_at' => time() - DAY_IN_SECONDS,
			)
		);
	}

	/**
	 * The page's HTML.
	 *
	 * @return string
	 */
	protected function render_page() {
		ob_start();
		tvarkom_obd_render_page();
		return (string) ob_get_clean();
	}

	/**
	 * GET /v1/results' body for a store with results.
	 *
	 * @param string $status The latest audit's status.
	 * @return array
	 */
	protected function ready_results( $status = 'ready' ) {
		return array(
			'status'       => $status,
			'next_scan_at' => gmdate( 'c', time() + 6 * DAY_IN_SECONDS ),
			'latest'       => array(
				'completed_at' => gmdate( 'c', time() - HOUR_IN_SECONDS ),
				'score'        => 40,
				'level'        => 'CRITICAL_RISK',
				'metrics'      => array(
					'ttfb_ms'        => 517,
					'cart_ms'        => 1025,
					'checkout_ms'    => 625,
					'page_weight_kb' => 1800,
					'requests'       => 72,
					'autoload_kb'    => 950,
					'php_version'    => '8.3.14',
					'object_cache'   => 'None',
					'cert_days_left' => 60,
					'spf'            => 'missing',
					'dmarc'          => 'present',
				),
				'lost_points'  => array(
					array(
						'id'     => 'slow_checkout',
						'points' => 20,
					),
					array(
						'id'     => 'slow_server',
						'points' => 20,
					),
					array(
						'id'     => 'too_many_files',
						'points' => 20,
					),
				),
			),
			'history'      => array(
				array(
					'completed_at' => gmdate( 'c', time() - 8 * DAY_IN_SECONDS ),
					'score'        => 30,
				),
				array(
					'completed_at' => gmdate( 'c', time() - HOUR_IN_SECONDS ),
					'score'        => 40,
				),
			),
		);
	}
}
