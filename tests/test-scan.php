<?php
/**
 * The weekly scan: the gateway's week decides the schedule, a replaced key registers again, errors
 * retry later, and one scan request runs at a time.
 *
 * @package OBDScanner
 */

/**
 * Scheduled scans.
 */
class Tvarkom_OBD_Scan_Test extends Tvarkom_OBD_Test_Case {

	public function test_too_soon_schedules_at_the_gateways_date() {
		$this->connect_store();
		$allowed = time() + 3 * DAY_IN_SECONDS;
		$this->respond(
			'POST',
			'/v1/audits',
			429,
			array(
				'error'            => 'too_soon',
				'next_eligible_at' => $allowed,
			)
		);

		$this->assertSame( 'too_soon', tvarkom_obd_scan() );
		$this->assertSame( $allowed + 5 * MINUTE_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ) );
		$this->assertSame( 'too_soon', tvarkom_obd_settings()['last']['code'] );
	}

	public function test_a_replaced_key_registers_again_and_scans() {
		$this->connect_store();
		$this->respond( 'POST', '/v1/audits', 401, array( 'error' => 'invalid_key' ) );
		$this->respond_registration( 'tvk_new' );
		$this->respond( 'POST', '/v1/audits', 202, array( 'status' => 'queued' ) );

		$this->assertSame( 'queued', tvarkom_obd_scan() );
		$this->assertSame( array( 'POST /v1/audits', 'POST /v1/register', 'POST /v1/register/verify', 'POST /v1/audits' ), $this->request_lines() );
		$this->assertSame( 'Bearer tvk_new', $this->requests[3]['headers']['Authorization'] );
		$this->assertSame( 'tvk_new', tvarkom_obd_settings()['api_key'] );
	}

	public function test_errors_retry_later() {
		$this->connect_store();
		$this->respond( 'POST', '/v1/audits', new WP_Error( 'http_request_failed', 'offline' ) );
		$this->assertSame( 'network', tvarkom_obd_scan() );
		$this->assertEqualsWithDelta( time() + 6 * HOUR_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5 );

		$this->respond( 'POST', '/v1/audits', 429, array( 'error' => 'rate_limited' ) );
		$this->assertSame( 'rate_limited', tvarkom_obd_scan() );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5 );

		$this->respond( 'POST', '/v1/audits', 422, array( 'error' => 'not_woocommerce' ) );
		$this->assertSame( 'not_woocommerce', tvarkom_obd_scan() );
		$this->assertEqualsWithDelta( time() + WEEK_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5 );
	}

	public function test_one_scan_request_at_a_time() {
		$this->connect_store();
		$this->assertTrue( tvarkom_obd_lock() );
		$this->assertSame( 'scan_running', tvarkom_obd_scan() );
		$this->assertSame( array(), $this->requests );
	}

	public function test_a_lost_schedule_comes_back_and_deactivation_clears_it() {
		$this->connect_store();
		tvarkom_obd_ensure_schedule();
		$this->assertEqualsWithDelta( time() + MINUTE_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5 );

		tvarkom_obd_deactivate();
		$this->assertFalse( wp_next_scheduled( TVARKOM_OBD_CRON ) );
		$this->assertSame( 'tvk_test', tvarkom_obd_settings()['api_key'], 'the settings stay' );
	}

	public function test_a_failed_scan_is_retried_within_the_hour() {
		tvarkom_obd_schedule( time() + WEEK_IN_SECONDS );
		tvarkom_obd_retry_failed_scan();
		$this->assertEqualsWithDelta( time() + HOUR_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5 );

		tvarkom_obd_schedule( time() + 10 * MINUTE_IN_SECONDS );
		tvarkom_obd_retry_failed_scan();
		$this->assertEqualsWithDelta( time() + 10 * MINUTE_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5, 'a sooner scan stays' );
	}
}
