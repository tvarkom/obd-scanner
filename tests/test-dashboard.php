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
		$this->assertStringContainsString( 'Up 10 points since the scan on', $html, 'the change since the previous day with a scan' );
		$this->assertStringContainsString( 'class="tobd__scale-mark" style="left: 40%"', $html, 'the score on the risk bands' );
		$this->assertStringContainsString( 'id="tobd-summary-title"', $html, 'the store summary, also before the analysis' );
		$this->assertStringNotContainsString( 'data-tobd-refresh', $html );
		$this->assertDoesNotMatchRegularExpression( '/\\b(fix plan|Pro|steps?|hours|price|upgrade)\\b/i', wp_strip_all_tags( $html ), 'no fix plan and no paid offer' );
	}

	public function test_the_score_history_keeps_each_days_last_scan() {
		$day = strtotime( '2026-11-02 12:00:00 UTC' );
		$this->assertSame(
			array(
				array(
					'completed_at' => $day - DAY_IN_SECONDS,
					'score'        => 30,
				),
				array(
					'completed_at' => $day + 3600,
					'score'        => 70,
				),
			),
			tvarkom_obd_daily_history(
				array(
					array(
						'completed_at' => $day - DAY_IN_SECONDS,
						'score'        => 30,
					),
					array(
						'completed_at' => $day,
						'score'        => 50,
					),
					array(
						'completed_at' => $day + 3600,
						'score'        => 70,
					),
				)
			)
		);
	}

	public function test_a_new_scan_keeps_the_latest_results_and_refreshes() {
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results( 'scanning' ) );
		$html = $this->render_page();

		$this->assertSame( 1, substr_count( $html, 'class="tobd__progress"' ), 'one status bar' );
		$this->assertStringContainsString( 'Scanning your store…', $html );
		$this->assertStringContainsString( 'tobd__spinner', $html );
		$this->assertStringContainsString( 'you&#039;re seeing your scan from', $html );
		$this->assertStringNotContainsString( 'tobd__notice', $html, 'no second notice' );
		$this->assertStringContainsString( 'data-tobd-refresh', $html );
		$this->assertStringContainsString( '<span class="tobd__score-value">40</span>', $html );
	}

	public function test_a_requested_scan_shows_one_status_bar_not_two_notices() {
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results() );
		$_GET['obd_result'] = 'queued';
		try {
			$html = $this->render_page();
		} finally {
			unset( $_GET['obd_result'] );
		}

		$this->assertSame( 1, substr_count( $html, 'class="tobd__progress"' ) );
		$this->assertStringNotContainsString( 'Scan requested', $html );
		$this->assertStringNotContainsString( 'tobd__scanbar', $html, 'no scan button while scanning' );
	}

	public function test_the_page_lives_at_obd_scanner() {
		$this->assertSame( admin_url( 'admin.php?page=obd-scanner' ), tvarkom_obd_page_url() );
	}

	public function test_assets_change_url_when_their_files_change() {
		$version = tvarkom_obd_asset_version( 'assets/admin.css' );
		$this->assertSame( TVARKOM_OBD_VERSION . '.' . filemtime( dirname( __DIR__ ) . '/assets/admin.css' ), $version );
		$this->assertSame( TVARKOM_OBD_VERSION, tvarkom_obd_asset_version( 'assets/missing.css' ) );
	}

	public function test_a_failed_scan_can_be_scanned_again() {
		$body                 = $this->ready_results( 'failed' );
		$body['next_scan_at'] = null;
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();

		$this->assertStringContainsString( 'couldn&#039;t be completed', $html );
		$this->assertMatchesRegularExpression( '/<button type="submit" class="tobd__btn"\s*>Scan now/', $html, 'enabled' );
	}

	public function test_the_scan_button_waits_for_the_week() {
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results() );
		$html = $this->render_page();

		$this->assertMatchesRegularExpression( '/<button type="submit" class="tobd__btn"\s*disabled=\'disabled\'\s*>Scan now/', $html );
		$this->assertStringContainsString( 'Your next scan is available on', $html );
		$this->assertStringContainsString( 'name="action" value="tvarkom_obd_scan"', $html );
	}

	public function test_a_replaced_key_offers_to_connect_again() {
		$this->respond( 'GET', '/v1/results', 401, array( 'error' => 'invalid_key' ) );
		$html = $this->render_page();

		$this->assertStringContainsString( 'Connect again', $html );
		$this->assertStringContainsString( 'tobd-connect-form', $html );
	}

	public function test_unreachable_results_say_so() {
		$this->respond( 'GET', '/v1/results', new WP_Error( 'http_request_failed', 'offline' ) );
		$this->assertStringContainsString( 'couldn&#039;t be loaded', $this->render_page() );
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

	public function test_the_full_analysis_is_shown_without_markup() {
		$body                       = $this->ready_results();
		$body['latest']['analysis'] = array(
			'schema'   => 1,
			'summary'  => array(
				'headline' => 'Store performance risk: High',
				'text'     => 'Your store has serious speed problems.',
				'impact'   => 'Modelled sales at risk: about 450 € – 6 300 € a month.',
			),
			'sections' => array(
				array(
					'id'      => 'marketing',
					'heading' => 'Technical findings',
					'blocks'  => array(
						array(
							'type'   => 'heading',
							'text'   => 'Server & checkout speed',
							'status' => 'Needs attention',
						),
						array(
							'type'  => 'list',
							'items' => array( 'Homepage: <b>0.95 s</b>', 42 ),
						),
						array(
							'type'  => 'pairs',
							'items' => array(
								array(
									'term' => 'Slow server',
									'text' => 'Every page waits for it.',
								),
							),
						),
						array(
							'type' => 'table',
							'head' => array( 'What', 'Yours' ),
							'rows' => array( array( 'Files', '90' ) ),
						),
						array( 'type' => 'script' ),
					),
				),
			),
		);
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();

		$this->assertStringContainsString( 'Your store has serious speed problems.', $html );
		$this->assertStringContainsString( '<h2 class="tobd__h2" id="tobd-score-title">Store performance score</h2>', $html, 'the risk shows once, as the badge' );
		$this->assertStringNotContainsString( 'Store performance risk: High', $html );
		$this->assertStringNotContainsString( 'Full analysis', $html );
		$this->assertStringContainsString( '<span class="tobd__status">Needs attention</span>', $html );
		$this->assertStringContainsString( 'Homepage: &lt;b&gt;0.95 s&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>0.95', $html );
		$this->assertStringContainsString( '<dt>Slow server</dt>', $html );
		$this->assertStringContainsString( '<td>90</td>', $html );
	}

	public function test_scans_before_0_2_0_have_no_analysis() {
		$this->respond( 'GET', '/v1/results', 200, $this->ready_results() );
		$this->assertStringNotContainsString( 'Full analysis', $this->render_page() );
		$this->assertNull( tvarkom_obd_parse_analysis( array( 'schema' => 2 ) ) );
	}

	public function test_free_sections_are_charts_and_paid_ones_never_show() {
		$body                       = $this->ready_results();
		$body['latest']['analysis'] = array(
			'schema'   => 1,
			'figures'  => array(
				'orders'     => array(
					'paid'      => 312,
					'failed'    => 9,
					'pending'   => 2,
					'cancelled' => 4,
				),
				'money'      => array(
					'sales' => '25 000 € – 100 000 €',
					'aov'   => '<b>50 €</b>',
				),
				'customers'  => array(
					'new'       => 200,
					'returning' => 112,
				),
				'catalog'    => array(
					'products'         => 420,
					'variations'       => 1300,
					'stock_management' => true,
				),
				'operations' => array(
					'payment_gateways' => array( 'Stripe', 'PayPal' ),
					'shipping_methods' => array( 'Flat rate' ),
					'checkout_type'    => 'block',
					'hpos'             => true,
				),
			),
			'summary'  => array( 'text' => 'Serious speed problems.' ),
			'sections' => array(
				array(
					'id'      => 'commercial',
					'tier'    => 'free',
					'heading' => 'Sales & checkout',
					'blocks'  => array(
						array(
							'type'  => 'list',
							'items' => array( 'Paid and on-hold orders in the last 30 days: 312' ),
						),
						array(
							'type' => 'heading',
							'text' => 'Potential sales impact — modelled estimate',
						),
						array(
							'type'  => 'list',
							'items' => array( 'Modelled sales at risk: about 450 €.' ),
						),
					),
				),
				array(
					'id'      => 'catalog',
					'tier'    => 'free',
					'heading' => 'Products & inventory',
					'blocks'  => array(
						array(
							'type'  => 'list',
							'items' => array( '420 products' ),
						),
					),
				),
				array(
					'id'      => 'technical',
					'tier'    => 'paid',
					'heading' => 'Technical findings',
					'blocks'  => array(
						array(
							'type' => 'text',
							'text' => 'PAID-ONLY',
						),
					),
				),
				array(
					'id'      => 'developer',
					'heading' => 'Developer reference',
					'blocks'  => array(
						array(
							'type' => 'text',
							'text' => 'PAID-ONLY',
						),
					),
				),
			),
		);
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();

		$this->assertStringNotContainsString( 'PAID-ONLY', $html, 'paid-version sections never show, even without a tier' );
		$this->assertStringNotContainsString( 'Full analysis', $html );
		$this->assertStringContainsString( 'class="tobd__stack"', $html, 'the order outcomes bar' );
		$this->assertStringContainsString( 'id="tobd-store-title">Store profile', $html, 'the store profile widget' );
		$this->assertStringContainsString( 'id="tobd-impact-title">Estimated revenue at risk', $html, 'the impact widget' );
		$this->assertStringContainsString( 'Modelled sales at risk: about 450 €.', $html, 'without impact figures, the estimate as text' );
		$this->assertStringNotContainsString( 'tobd-lost-title', $html, 'the impact widget replaces the points lost' );
		$this->assertLessThan( strpos( $html, 'tobd-score-title' ), strpos( $html, 'tobd-summary-title' ), 'the store summary first' );
		$this->assertLessThan( strpos( $html, 'tobd-impact-title' ), strpos( $html, 'tobd-score-title' ), 'then the score and the sales impact' );
		$this->assertLessThan( strpos( $html, 'tobd-store-title' ), strpos( $html, 'tobd-impact-title' ), 'then the store profile in detail' );
		$this->assertStringContainsString( '<dt>Checkout</dt><dd>Checkout block</dd>', $html, 'the checkout in the summary' );
		$this->assertSame( 1, substr_count( $html, 'Order storage (HPOS)' ), 'shown once' );
		$this->assertStringContainsString( '<li>Stripe</li>', $html );
		$this->assertStringContainsString( '&lt;b&gt;50 €&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( 'Paid and on-hold orders in the last 30 days: 312', $html, 'the sales sentences become the figures' );
	}

	public function test_the_sales_impact_is_drawn_from_its_figures() {
		$body                       = $this->ready_results();
		$body['latest']['analysis'] = array(
			'schema'   => 1,
			'figures'  => array(
				'money'  => array(
					'sales' => '20 000 €',
					'aov'   => '50 €',
				),
				'orders' => array( 'failed' => 20 ),
				'impact' => array(
					'status'        => 'estimated',
					'sales'         => array(
						'low'   => 20000,
						'high'  => 20000,
						'label' => '20 000 €',
					),
					'slow_checkout' => array(
						'low'            => 400,
						'high'           => 1400,
						'label'          => '<i>400 €</i> – 1 400 €',
						'excess_seconds' => 1,
					),
					'failed_orders' => array(
						'low'   => 1000,
						'high'  => 1000,
						'label' => '1 000 €',
						'count' => 20,
					),
					'total'         => array(
						'low'   => 1400,
						'high'  => 2400,
						'label' => '1 400 € – 2 400 €',
					),
				),
			),
			'sections' => array(
				array(
					'id'      => 'commercial',
					'heading' => 'Sales & checkout',
					'blocks'  => array(
						array(
							'type' => 'heading',
							'text' => 'Potential sales impact — modelled estimate',
						),
						array(
							'type'  => 'list',
							'items' => array( 'SENTENCE' ),
						),
						array(
							'type' => 'note',
							'text' => 'How the model works.',
						),
					),
				),
			),
		);
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();

		$this->assertStringContainsString( '<span class="tobd__impact-value">1 400 € – 2 400 €</span>', $html );
		$this->assertStringContainsString( 'modelled from this scan: 7%–12% of your gross sales', $html, 'share of the exact sales' );
		$this->assertStringContainsString( 'A modelled scenario, not measured lost sales', $html );
		$this->assertSame( 2, substr_count( $html, 'class="tobd__range-track"' ), 'one bar per cause' );
		$this->assertStringContainsString( 'style="left: 28.6%; width: 71.4%"', $html, 'slow checkout: 400 to 1 400, the largest high estimate' );
		$this->assertStringContainsString( 'style="left: 71.4%; width: 0.0%"', $html, 'failed orders: 1 000 exactly' );
		$this->assertStringContainsString( '<dt>Gross sales, last 30 days</dt><dd>20 000 €</dd>', $html, 'the store figures the estimate starts from' );
		$this->assertStringContainsString( '<dt>Orders failed at payment</dt><dd>20</dd>', $html );
		$this->assertStringNotContainsString( 'Paid orders, last 30 days', $html, 'only the figures the model uses' );
		$this->assertStringContainsString( 'Assumptions and sources', $html );
		$this->assertStringContainsString( '&lt;i&gt;400 €&lt;/i&gt;', $html, 'labels are escaped' );
		$this->assertStringContainsString( '20 orders failed at payment', $html );
		$this->assertStringContainsString( 'How the model works.', $html, 'the note under How this is estimated' );
		$this->assertStringNotContainsString( 'SENTENCE', $html, 'the chart replaces the sentences' );
	}

	public function test_no_estimate_outside_the_modelled_range_shows_the_reason() {
		$this->assertNull( tvarkom_obd_parse_impact( array( 'status' => 'guess' ) ) );
		$this->assertNull( tvarkom_obd_parse_impact( array( 'status' => 'estimated' ) ), 'an estimate needs its total' );
		$this->assertSame( 'below', tvarkom_obd_parse_impact( array( 'status' => 'below' ) )['status'] );
		$this->assertNull(
			tvarkom_obd_parse_impact(
				array(
					'status' => 'below',
					'total'  => array(
						'low'   => 5,
						'high'  => 1,
						'label' => 'x',
					),
				)
			)['total'],
			'a range that runs backwards is dropped'
		);
	}

	public function test_the_pipeline_shows_every_job_with_its_colour_and_panel() {
		$body                       = $this->ready_results();
		$body['latest']['analysis'] = array(
			'schema'   => 1,
			'summary'  => array(),
			'sections' => array(),
			'pipeline' => array(
				'stages' => array(
					array(
						'id'     => 'server',
						'name'   => 'Server',
						'status' => 'red',
						'jobs'   => array(
							array(
								'id'        => 'homepage_response',
								'name'      => 'Homepage response',
								'status'    => 'red',
								'about'     => 'How long the server took.',
								'measured'  => '0.95 s',
								'reference' => '0.30 s or less',
								'critical'  => 'Over 0.80 s',
								'points'    => 40,
							),
							array(
								'id'       => 'compression',
								'name'     => '<script>x</script>',
								'status'   => 'purple',
								'measured' => 'none',
							),
						),
					),
				),
			),
		);
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();

		$this->assertStringContainsString( 'Scanner pipeline', $html );
		$this->assertStringNotContainsString( 'tobd__pipeline-legend', $html, 'no captions' );
		$this->assertStringContainsString( '<h3 class="tobd__stage-name">', $html, 'each stage is a card with its name as the title' );
		$this->assertStringContainsString( 'tobd__job-action', $html, 'each job has its round button, as in GitLab' );
		$this->assertStringContainsString( 'tobd__job--red', $html );
		$this->assertStringContainsString( 'aria-controls="tobd-job-0-0"', $html );
		$this->assertStringContainsString( 'id="tobd-job-0-0"', $html );
		$this->assertStringContainsString( '<dd>0.95 s</dd>', $html );
		$this->assertStringContainsString( '<dd>−40</dd>', $html );
		$this->assertStringContainsString( 'tobd__job--grey', $html, 'an unknown colour is grey' );
		$this->assertStringNotContainsString( '<script>x', $html );
		$this->assertStringNotContainsString( 'tobd-speed-title', $html, 'the pipeline replaces the metric panels' );
	}

	/**
	 * A pipeline for the overview's tests: one job per given status, points and id.
	 *
	 * @param array<int, array{0: string, 1: int|null, 2?: string}> $jobs [status, points, id].
	 * @return array<string, mixed>
	 */
	private function pipeline_of( array $jobs ) {
		$out = array();
		foreach ( $jobs as $i => $job ) {
			$out[] = array(
				'id'        => isset( $job[2] ) ? $job[2] : 'job_' . $i,
				'name'      => 'Job ' . $i,
				'status'    => $job[0],
				'measured'  => $i . ' s',
				'reference' => '0.30 s or less',
				'points'    => $job[1],
			);
		}
		return array(
			'stages' => array(
				array(
					'id'     => 'first',
					'name'   => 'First',
					'status' => 'red',
					'jobs'   => array_slice( $out, 0, 4 ),
				),
				array(
					'id'     => 'second',
					'name'   => 'Second',
					'status' => 'red',
					'jobs'   => array_slice( $out, 4 ),
				),
			),
		);
	}

	public function test_priority_findings_are_red_then_orange_by_points_lost() {
		$stages   = tvarkom_obd_parse_pipeline(
			$this->pipeline_of(
				array(
					array( 'orange', 20 ),  // Job 0, tobd-job-0-0.
					array( 'red', 20 ),     // Job 1, tobd-job-0-1.
					array( 'yellow', null ),
					array( 'red', 40 ),     // Job 3, tobd-job-0-3.
					array( 'orange', null ), // Job 4, tobd-job-1-0.
					array( 'green', null ),
					array( 'red', 20 ),     // Job 6, tobd-job-1-2.
					array( 'grey', null ),
				)
			)
		);
		$findings = tvarkom_obd_priority_findings( $stages );

		$this->assertSame( array( 'Job 3', 'Job 1', 'Job 6', 'Job 0', 'Job 4' ), wp_list_pluck( wp_list_pluck( $findings, 'job' ), 'name' ), 'red first, most points first, then the pipeline order; no yellow' );
		$this->assertSame( array( 'tobd-job-0-3', 'tobd-job-0-1', 'tobd-job-1-2', 'tobd-job-0-0', 'tobd-job-1-0' ), wp_list_pluck( $findings, 'panel' ), 'the panel ids the pipeline gives its jobs' );
		$this->assertSame(
			array(
				'red'    => 3,
				'orange' => 2,
				'yellow' => 1,
				'green'  => 1,
				'grey'   => 1,
			),
			tvarkom_obd_pipeline_counts( $stages )
		);
	}

	public function test_what_needs_attention_lists_at_most_five_and_links_each_to_its_panel() {
		$jobs = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$jobs[] = array( 'red', 10 + $i );
		}
		$jobs[]                     = array( 'yellow', null );
		$body                       = $this->ready_results();
		$body['latest']['analysis'] = array(
			'schema'   => 1,
			'summary'  => array(),
			'sections' => array(),
			'pipeline' => $this->pipeline_of( $jobs ),
		);
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();

		$this->assertStringContainsString( 'id="tobd-attention-title">What needs attention', $html );
		$this->assertSame( 5, substr_count( $html, 'class="tobd__finding"' ), 'five at most' );
		$this->assertStringContainsString( 'href="#tobd-job-1-2" data-tobd-job="tobd-job-1-2"', $html, 'the worst (16 points) links to its panel' );
		$this->assertStringContainsString( 'aria-controls="tobd-job-1-2"', $html, 'and that panel is the job\'s in the pipeline' );
		$this->assertStringContainsString( '6 s (reference 0.30 s or less)', $html, 'what was measured against the reference' );
		$this->assertStringContainsString( '−16', $html );
		$this->assertStringContainsString( '2 more critical or over-reference checks', $html );
		$this->assertStringContainsString( '<strong>7</strong> Critical', $html );
		$this->assertStringContainsString( '8 checks in 2 stages', $html, 'the summary says how many checks ran' );
		$this->assertLessThan( strpos( $html, 'tobd-pipeline-title' ), strpos( $html, 'tobd-attention-title' ), 'the overview comes before the pipeline' );
	}

	public function test_no_finding_says_so_and_scans_without_a_pipeline_have_no_attention_panel() {
		$body                       = $this->ready_results();
		$body['latest']['analysis'] = array(
			'schema'   => 1,
			'summary'  => array(),
			'sections' => array(),
			'pipeline' => $this->pipeline_of( array( array( 'green', null ), array( 'yellow', null ) ) ),
		);
		$this->respond( 'GET', '/v1/results', 200, $body );
		$html = $this->render_page();
		$this->assertStringContainsString( 'No check is critical or over its reference.', $html );
		$this->assertStringContainsString( 'tobd__overview--3', $html );

		$this->respond( 'GET', '/v1/results', 200, $this->ready_results() );
		$html = $this->render_page();
		$this->assertStringNotContainsString( 'tobd-attention-title', $html );
		$this->assertStringContainsString( 'tobd__overview--2', $html );
	}

	public function test_the_trend_compares_with_the_previous_day_with_a_scan() {
		$day = strtotime( '2026-11-02 12:00:00 UTC' );
		$run = function ( array $scores ) use ( $day ) {
			$daily = array();
			foreach ( $scores as $i => $score ) {
				$daily[] = array(
					'completed_at' => $day + $i * DAY_IN_SECONDS,
					'score'        => $score,
				);
			}
			ob_start();
			tvarkom_obd_render_trend( $daily );
			return (string) ob_get_clean();
		};
		$this->assertSame( '', $run( array( 50 ) ), 'nothing before a second day' );
		$this->assertStringContainsString( 'Down 20 points since the scan on', $run( array( 80, 60 ) ) );
		$this->assertStringContainsString( 'tobd--bad', $run( array( 80, 60 ) ) );
		$this->assertStringContainsString( 'Up 1 point since the scan on', $run( array( 10, 60, 61 ) ) );
		$this->assertStringContainsString( 'No change since the scan on', $run( array( 40, 40 ) ) );
	}
}
