<?php
/**
 * Connecting: nothing is sent before "Connect and scan"; the click registers the store, sends the
 * first scan with only the listed data. Nothing is scheduled: every scan is a click.
 *
 * @package OBDScanner
 */

/**
 * Connect and scan.
 */
class Tvarkom_OBD_Connect_Test extends Tvarkom_OBD_Test_Case {

	public function test_nothing_is_sent_before_connecting() {
		$this->sign_in_shop_manager();
		do_action( 'admin_init' );
		$html = $this->render_page();
		$this->assertSame( 'not_connected', tvarkom_obd_scan() );

		$this->assertSame( array(), $this->requests );
		$this->assertStringContainsString( 'Connect and scan', $html );
		$this->assertStringContainsString( 'obd-scanner/legal/privacy-policy.txt', $html );
		$this->assertStringContainsString( 'obd-scanner/legal/terms-of-use.txt', $html );
	}

	public function test_connect_registers_and_scans_without_scheduling_anything() {
		$this->sign_in_shop_manager();
		$this->respond_registration();
		$this->respond( 'POST', '/v1/audits', 202, array( 'status' => 'queued' ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'tvarkom_obd_scan' );

		$this->assertSame( 'queued', $this->result_of_redirect( 'tvarkom_obd_handle_scan' ) );

		$this->assertSame( array( 'POST /v1/register', 'POST /v1/register/verify', 'POST /v1/audits' ), $this->request_lines() );
		$register = $this->requests[0]['body'];
		$this->assertSame( get_option( 'admin_email' ), $register['email'] );
		$this->assertSame( rest_url( 'tvarkom-obd/v1/verify' ), $register['verify_url'] );

		$audit = $this->requests[2];
		$this->assertSame( 'Bearer tvk_test', $audit['headers']['Authorization'] );
		$this->assertSame( array( 'product', 'website', 'email', 'pages', 'metrics', 'detailed' ), array_keys( $audit['body'] ) );
		$this->assertSame( 'obd', $audit['body']['product'] );
		$this->assertSame( get_option( 'admin_email' ), $audit['body']['email'] );
		// 0.2.0: the same data as the Checkout Speed Audit (metrics schema 3 and the detailed data).
		$this->assertSame( 3, $audit['body']['metrics']['schema'] );
		$this->assertArrayHasKey( 'order_tier', $audit['body']['metrics'] );
		$this->assertArrayHasKey( 'currency', $audit['body']['metrics'] );
		$this->assertArrayHasKey( 'top_autoloaded_keys', $audit['body']['detailed'] );
		$this->assertArrayHasKey( 'checkout_profile', $audit['body']['detailed'] );
		// The pipeline's site checks: counts and flags only.
		$health = $audit['body']['detailed']['site_health'];
		$this->assertSame(
			array( 'core_update_available', 'theme_updates', 'wp_debug', 'debug_display', 'search_engines_blocked', 'myisam_tables', 'non_utf8mb4_tables', 'expired_transients', 'administrators', 'file_editor_enabled' ),
			array_keys( $health )
		);
		foreach ( $health as $key => $value ) {
			$this->assertTrue( null === $value || is_bool( $value ) || is_int( $value ), $key );
		}

		$settings = tvarkom_obd_settings();
		$this->assertSame( 'tvk_test', $settings['api_key'] );
		$this->assertGreaterThan( 0, $settings['connected_at'] );
		$this->assertFalse( wp_next_scheduled( 'tvarkom_obd_scan' ), 'no scheduled scan' );
		$this->assertFalse( get_transient( TVARKOM_OBD_TOKEN ), 'the registration token is gone' );
		$this->assertFalse( get_option( TVARKOM_OBD_LOCK ), 'the lock is released' );
	}

	public function test_a_failed_registration_leaves_the_store_unconnected() {
		$this->sign_in_shop_manager();
		$this->respond(
			'POST',
			'/v1/register',
			201,
			array(
				'registration_id' => 'reg_1',
				'token'           => 'tok_1',
			)
		);
		$this->respond( 'POST', '/v1/register/verify', 422, array( 'error' => 'verification_failed' ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'tvarkom_obd_scan' );

		$this->assertSame( 'verification_failed', $this->result_of_redirect( 'tvarkom_obd_handle_scan' ) );
		$this->assertFalse( tvarkom_obd_is_connected() );
		$this->assertStringContainsString( 'tvarkom-obd/v1/verify', $this->render_page_with_result( 'verification_failed' ) );
	}

	public function test_connecting_needs_the_capability_and_the_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'tvarkom_obd_scan' );
		try {
			tvarkom_obd_handle_scan();
			$this->fail( 'A subscriber connected the store.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( array(), $this->requests );
		}

		$this->sign_in_shop_manager();
		$_REQUEST['_wpnonce'] = 'wrong';
		try {
			tvarkom_obd_handle_scan();
			$this->fail( 'Connected without a valid nonce.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( array(), $this->requests );
			$this->assertFalse( tvarkom_obd_is_connected() );
		}
	}

	public function test_scan_now_keeps_a_connected_store_connected() {
		$this->sign_in_shop_manager();
		$this->connect_store();
		$this->respond(
			'POST',
			'/v1/audits',
			429,
			array(
				'error'            => 'too_soon',
				'next_eligible_at' => time() + DAY_IN_SECONDS,
			)
		);
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'tvarkom_obd_scan' );

		$this->assertSame( 'too_soon', $this->result_of_redirect( 'tvarkom_obd_handle_scan' ) );
		$this->assertSame( array( 'POST /v1/audits' ), $this->request_lines() );
		$this->assertTrue( tvarkom_obd_is_connected() );
	}

	public function test_the_verify_route_serves_only_a_pending_token() {
		$this->assertSame( 404, tvarkom_obd_rest_verify()->get_error_data()['status'] );
		set_transient( TVARKOM_OBD_TOKEN, 'tok_1', MINUTE_IN_SECONDS );
		$this->assertSame( array( 'token' => 'tok_1' ), tvarkom_obd_rest_verify() );
	}

	/**
	 * The page as shown after a redirect with $code.
	 *
	 * @param string $code The obd_result code.
	 * @return string
	 */
	private function render_page_with_result( $code ) {
		$_GET['obd_result'] = $code;
		try {
			return $this->render_page();
		} finally {
			unset( $_GET['obd_result'] );
		}
	}

	public function tear_down() {
		unset( $_REQUEST['_wpnonce'] );
		parent::tear_down();
	}
}
