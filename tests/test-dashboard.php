<?php
/**
 * The page once connected: the scan in progress, the dashboard, a key to renew, and the results'
 * shape and escaping.
 *
 * @package OBDScanner
 */

/**
 * Dashboard.
 */
class Tvarkom_OBD_Dashboard_Test extends Tvarkom_OBD_Test_Case {

	public function set_up() {
		parent::set_up();
		$this->sign_in_shop_manager();
		$this->connect_store();
	}

	public function test_the_first_scan_in_progress_refreshes_the_page() {
		$this->respond(
			'GET',
			'/v1/results',
			200,
			array(
				'status'       => 'scanning',
				'next_scan_at' => null,
				'latest'       => null,
				'history'      => array(),
			)
		);
		$html = $this->render_page();

		$this->assertSame( array( 'GET /v1/results' ), $this->request_lines() );
		$this->assertSame( 'Bearer tvk_test', $this->requests[0]['headers']['Authorization'] );
		$this->assertStringContainsString( 'Scanning your store', $html );
		$this->assertStringContainsString( 'data-tobd-refresh', $html );
	}

	public function test_results_show_the_score_lost_points_and_measurements() {
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results() );
		$html = $this->render_page();

		$this->assertStringContainsString( '<span class="tobd__score-value">40</span>', $html );
		$this->assertStringContainsString( 'High risk', $html );
		$this->assertStringContainsString( 'Slow cart or checkout', $html );
		$this->assertSame( 3, substr_count( $html, '<span class="tobd__points">−20</span>' ) );
		$this->assertStringContainsString( '1.03 s', $html, 'the cart time in seconds' );
		$this->assertStringContainsString( '8.3.14', $html );
		$this->assertStringContainsString( 'class="tobd__history"', $html );
		$this->assertStringNotContainsString( 'data-tobd-refresh', $html );
		$this->assertDoesNotMatchRegularExpression( '/\\b(fix plan|Pro|steps?|hours|price|upgrade)\\b/i', wp_strip_all_tags( $html ), 'no fix plan and no paid offer' );
		$this->assertStringContainsString( 'Next scan:', $html );
	}

	public function test_a_new_scan_keeps_the_latest_results_and_refreshes() {
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results( 'scanning' ) );
		$html = $this->render_page();

		$this->assertStringContainsString( 'A new scan is running', $html );
		$this->assertStringContainsString( 'data-tobd-refresh', $html );
		$this->assertStringContainsString( '<span class="tobd__score-value">40</span>', $html );
	}

	public function test_a_failed_scan_is_retried_within_the_hour() {
		tvarkom_obd_schedule( time() + WEEK_IN_SECONDS );
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results( 'failed' ) );
		$html = $this->render_page();

		$this->assertStringContainsString( 'couldn\'t be completed', $html );
		$this->assertEqualsWithDelta( time() + HOUR_IN_SECONDS, wp_next_scheduled( TVARKOM_OBD_CRON ), 5 );
	}

	public function test_a_replaced_key_offers_to_connect_again() {
		$this->respond( 'GET', '/v1/results', 401, array( 'error' => 'invalid_key' ) );
		$html = $this->render_page();

		$this->assertStringContainsString( 'Connect again', $html );
		$this->assertStringContainsString( 'tobd-connect-form', $html );
	}

	public function test_unreachable_results_say_so() {
		$this->respond( 'GET', '/v1/results', new WP_Error( 'http_request_failed', 'offline' ) );
		$this->assertStringContainsString( 'couldn\'t be loaded', $this->render_page() );
	}

	public function test_values_from_the_gateway_are_checked_and_escaped() {
		$body                                     = $this->ready_results();
		$body['status']                           = 'hacked';
		$body['latest']['score']                  = 400;
		$body['latest']['level']                  = '<b>PASS</b>';
		$body['latest']['metrics']['php_version'] = '<script>alert(1)</script>';
		$body['latest']['metrics']['nested']      = array( 'x' );
		$body['latest']['lost_points'][]          = array(
			'id'     => '<img src=x>',
			'points' => 'many',
		);
		$body['history'][]                        = array(
			'completed_at' => 'yesterday-ish',
			'score'        => 50,
		);

		$parsed = tvarkom_obd_parse_results( $body );
		$this->assertSame( 'none', $parsed['status'] );
		$this->assertNull( $parsed['latest']['score'] );
		$this->assertSame( '', $parsed['latest']['level'] );
		$this->assertArrayNotHasKey( 'nested', $parsed['latest']['metrics'] );
		$this->assertSame(
			array(
				'id'     => 'imgsrcx',
				'points' => null,
			),
			end( $parsed['latest']['lost_points'] )
		);
		$this->assertCount( 2, $parsed['history'] );

		ob_start();
		tvarkom_obd_render_dashboard( $parsed );
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( 'Other check', $html );
	}
}
