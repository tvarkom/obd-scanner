<?php
/**
 * Scan requests: a replaced key registers again, results are stored for the page, one request runs
 * at a time, and nothing is ever scheduled.
 *
 * @package Pirkoo
 */

/**
 * Scans.
 */
class Pirkoo_Scan_Test extends Pirkoo_Test_Case {

	public function test_too_soon_is_stored_for_the_page() {
		$this->connect_store();
		$this->respond(
			'POST',
			'/v1/audits',
			429,
			array(
				'error'            => 'too_soon',
				'next_eligible_at' => time() + 3 * DAY_IN_SECONDS,
			)
		);

		$this->assertSame( 'too_soon', pirkoo_scan() );
		$this->assertSame( 'too_soon', pirkoo_settings()['last']['code'] );
		$this->assertFalse( wp_next_scheduled( 'pirkoo_scan' ) );
	}

	public function test_a_replaced_key_registers_again_and_scans() {
		$this->connect_store();
		$this->respond( 'POST', '/v1/audits', 401, array( 'error' => 'invalid_key' ) );
		$this->respond_registration( 'tvk_new' );
		$this->respond( 'POST', '/v1/audits', 202, array( 'status' => 'queued' ) );

		$this->assertSame( 'queued', pirkoo_scan() );
		$this->assertSame( array( 'POST /v1/audits', 'POST /v1/register', 'POST /v1/register/verify', 'POST /v1/audits' ), $this->request_lines() );
		$this->assertSame( 'Bearer tvk_new', $this->requests[3]['headers']['Authorization'] );
		$this->assertSame( 'tvk_new', pirkoo_settings()['api_key'] );
	}

	public function test_errors_are_reported() {
		$this->connect_store();
		$this->respond( 'POST', '/v1/audits', new WP_Error( 'http_request_failed', 'offline' ) );
		$this->assertSame( 'network', pirkoo_scan() );

		$this->respond( 'POST', '/v1/audits', 422, array( 'error' => 'not_woocommerce' ) );
		$this->assertSame( 'not_woocommerce', pirkoo_scan() );
		$this->assertFalse( wp_next_scheduled( 'pirkoo_scan' ) );
	}

	public function test_one_scan_request_at_a_time() {
		$this->connect_store();
		$this->assertTrue( pirkoo_lock() );
		$this->assertSame( 'scan_running', pirkoo_scan() );
		$this->assertSame( array(), $this->requests );
	}
}
