<?php
/**
 * The overview above the pipeline (founder decisions of 10 Nov 2026): the store summary, the
 * store health score with the risk bands and how many checks it is based on, the checks that need
 * attention first, and the store's health trends. Everything is read from the scan's pipeline,
 * figures, scoring and history as they are; nothing here scores or estimates. Every finding links
 * to its job's panel in the pipeline (includes/pipeline.php).
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many priority findings the overview lists at most.
 */
const TVARKOM_OBD_PRIORITY_FINDINGS = 5;

/**
 * The tags a status icon (tvarkom_obd_job_icon()) may contain.
 *
 * @return array<string, array<string, bool>>
 */
function tvarkom_obd_icon_allowed_html() {
	return array(
		'svg'    => array(
			'class'       => true,
			'viewbox'     => true,
			'aria-hidden' => true,
			'focusable'   => true,
		),
		'circle' => array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		),
		'path'   => array( 'd' => true ),
	);
}

/**
 * Every job of the pipeline in its order, with its stage's name and its panel's id.
 *
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages From tvarkom_obd_parse_pipeline().
 * @return array<int, array{stage: string, panel: string, order: int, job: array<string, mixed>}>
 */
function tvarkom_obd_pipeline_jobs( array $stages ) {
	$jobs = array();
	foreach ( $stages as $s => $stage ) {
		foreach ( $stage['jobs'] as $j => $job ) {
			$jobs[] = array(
				'stage' => $stage['name'],
				'panel' => tvarkom_obd_job_panel_id( $s, $j ),
				'order' => count( $jobs ),
				'job'   => $job,
			);
		}
	}
	return $jobs;
}

/**
 * How many jobs have each colour, worst first.
 *
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages From tvarkom_obd_parse_pipeline().
 * @return array<string, int> red, orange, yellow, green and grey.
 */
function tvarkom_obd_pipeline_counts( array $stages ) {
	$counts = array_fill_keys( array_reverse( TVARKOM_OBD_JOB_STATUSES ), 0 );
	foreach ( tvarkom_obd_pipeline_jobs( $stages ) as $item ) {
		++$counts[ $item['job']['status'] ];
	}
	return $counts;
}

/**
 * The jobs that found a problem (red, orange and yellow: every problem costs points since score
 * model 2), the most points lost first, then the worst colour, then the pipeline's order.
 *
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages From tvarkom_obd_parse_pipeline().
 * @return array<int, array{stage: string, panel: string, order: int, job: array<string, mixed>}>
 */
function tvarkom_obd_priority_findings( array $stages ) {
	$rank     = array_flip( TVARKOM_OBD_JOB_STATUSES );
	$findings = array_values(
		array_filter(
			tvarkom_obd_pipeline_jobs( $stages ),
			function ( $item ) {
				return in_array( $item['job']['status'], array( 'red', 'orange', 'yellow' ), true );
			}
		)
	);
	usort(
		$findings,
		function ( $a, $b ) use ( $rank ) {
			$by_points = (int) $b['job']['points'] - (int) $a['job']['points'];
			if ( 0 !== $by_points ) {
				return $by_points;
			}
			$by_status = $rank[ $b['job']['status'] ] - $rank[ $a['job']['status'] ];
			return 0 !== $by_status ? $by_status : $a['order'] - $b['order'];
		}
	);
	return $findings;
}

/**
 * The jobs with the given ids, in the pipeline's order.
 *
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages From tvarkom_obd_parse_pipeline().
 * @param string[]                                                                                            $ids    Job ids.
 * @return array<int, array{stage: string, panel: string, order: int, job: array<string, mixed>}>
 */
function tvarkom_obd_pipeline_find( array $stages, array $ids ) {
	return array_values(
		array_filter(
			tvarkom_obd_pipeline_jobs( $stages ),
			function ( $item ) use ( $ids ) {
				return in_array( $item['job']['id'], $ids, true );
			}
		)
	);
}

/**
 * A link to a job's panel in the pipeline. Without JavaScript every panel shows, so the anchor
 * alone reaches it; assets/admin.js also opens the panel, as a click on the job does.
 *
 * @param array{stage: string, panel: string, order: int, job: array<string, mixed>} $item      The job.
 * @param string                                                                     $css_class The link's class.
 * @param string                                                                     $inner The link's content, already escaped.
 * @return string
 */
function tvarkom_obd_job_link( array $item, $css_class, $inner ) {
	return '<a class="' . esc_attr( $css_class ) . '" href="#' . esc_attr( $item['panel'] ) . '" data-tobd-job="' . esc_attr( $item['panel'] ) . '">' . $inner . '</a>';
}

/**
 * The store summary: the store's name, when it was scanned and how many checks ran, and the facts
 * that say what kind of store it is.
 *
 * @param array<string, array<string, mixed>>|null                                                            $figures      The scan's figures, or null (scans before them).
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages       The pipeline.
 * @param int                                                                                                 $completed_at When the scan completed (Unix seconds).
 * @return void
 */
function tvarkom_obd_render_store_summary( $figures, array $stages, $completed_at ) {
	$name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	$meta = array(
		/* translators: %s: date and time of the scan. */
		sprintf( __( 'Scanned on %s', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_datetime( $completed_at ) ),
	);
	if ( ! empty( $stages ) ) {
		$checks = count( tvarkom_obd_pipeline_jobs( $stages ) );
		$meta[] = sprintf(
			/* translators: 1: number of checks, 2: number of stages. */
			_n( '%1$s check in %2$s stages', '%1$s checks in %2$s stages', $checks, 'on-board-diagnostics-scanner-for-woocommerce' ),
			number_format_i18n( $checks ),
			number_format_i18n( count( $stages ) )
		);
	}
	$facts = array();
	if ( null !== $figures ) {
		$p        = $figures['profile'];
		$ops      = $figures['operations'];
		$checkout = array(
			'block'   => __( 'Checkout block', 'on-board-diagnostics-scanner-for-woocommerce' ),
			'classic' => __( 'Classic checkout', 'on-board-diagnostics-scanner-for-woocommerce' ),
		);
		$storage  = null === $ops['hpos'] ? '' : ( $ops['hpos']
			? __( 'Order tables (HPOS)', 'on-board-diagnostics-scanner-for-woocommerce' )
			: __( 'WordPress posts (legacy)', 'on-board-diagnostics-scanner-for-woocommerce' ) );
		$facts    = array(
			array( __( 'Country', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['country'] ),
			array( __( 'Currency', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['currency'] ),
			array( __( 'Store size', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['size'] ),
			array( __( 'Paid orders a day', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $p['orders_per_day'] ? '' : number_format_i18n( $p['orders_per_day'], 1 ) ),
			array( __( 'Checkout', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $ops['checkout_type'] ? '' : $checkout[ $ops['checkout_type'] ] ),
			array( __( 'Order storage', 'on-board-diagnostics-scanner-for-woocommerce' ), $storage ),
		);
		$facts    = array_filter(
			$facts,
			function ( $fact ) {
				return '' !== $fact[1];
			}
		);
	}
	?>
	<section class="tobd__panel tobd__summary-strip" aria-labelledby="tobd-summary-title">
		<div class="tobd__summary-head">
			<h2 class="tobd__h2 tobd__summary-name" id="tobd-summary-title"><?php echo esc_html( '' !== $name ? $name : __( 'Your store', 'on-board-diagnostics-scanner-for-woocommerce' ) ); ?></h2>
			<p class="tobd__summary-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
		</div>
		<?php if ( ! empty( $facts ) ) : ?>
			<dl class="tobd__keyfacts">
				<?php foreach ( $facts as $fact ) : ?>
					<div><dt><?php echo esc_html( $fact[0] ); ?></dt><dd><?php echo esc_html( $fact[1] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<?php tvarkom_obd_render_fact_definitions(); ?>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * What the store summary's terms mean: the store sizes (tvarkom's scanner's size categories,
 * audit-tool scanner/src/analyzers/business.ts sizeCategory) and order storage. Closed by default.
 *
 * @return void
 */
function tvarkom_obd_render_fact_definitions() {
	$sizes = array(
		array( __( 'Starter', 'on-board-diagnostics-scanner-for-woocommerce' ), __( 'fewer than 60 (under 2 a day)', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( __( 'Micro', 'on-board-diagnostics-scanner-for-woocommerce' ), __( '60 to 299 (2 to 9 a day)', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( __( 'Small', 'on-board-diagnostics-scanner-for-woocommerce' ), __( '300 to 899 (10 to 29 a day)', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( __( 'Growing', 'on-board-diagnostics-scanner-for-woocommerce' ), __( '900 to 3,029 (30 to 100 a day)', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( __( 'Large', 'on-board-diagnostics-scanner-for-woocommerce' ), __( '3,030 to 15,029 (101 to 500 a day)', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( __( 'Enterprise', 'on-board-diagnostics-scanner-for-woocommerce' ), __( '15,030 or more (over 500 a day)', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
	);
	?>
	<details class="tobd__definitions">
		<summary><?php esc_html_e( 'What these terms mean', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></summary>
		<dl>
			<dt><?php esc_html_e( 'Store size', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt>
			<dd>
				<?php esc_html_e( 'Paid and on-hold orders in the last 30 days:', 'on-board-diagnostics-scanner-for-woocommerce' ); ?>
				<ul>
					<?php foreach ( $sizes as $size ) : ?>
						<li><strong><?php echo esc_html( $size[0] ); ?></strong> <?php echo esc_html( $size[1] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</dd>
			<dt><?php esc_html_e( 'Order storage', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt>
			<dd><?php esc_html_e( 'Where WooCommerce keeps orders. With High-Performance Order Storage (HPOS), orders have their own database tables, the default for new WooCommerce stores since version 8.2. Without it, orders are kept as WordPress posts, the older way.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dd>
			<dt><?php esc_html_e( 'Checkout', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt>
			<dd><?php esc_html_e( 'Whether the checkout page uses the Checkout block or the classic checkout shortcode.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dd>
		</dl>
	</details>
	<?php
}

/**
 * "On", "Off", or "" when unknown.
 *
 * @param bool|null $value The setting.
 * @return string
 */
function tvarkom_obd_on_off( $value ) {
	return null === $value ? '' : ( $value ? __( 'On', 'on-board-diagnostics-scanner-for-woocommerce' ) : __( 'Off', 'on-board-diagnostics-scanner-for-woocommerce' ) );
}

/**
 * Needs attention: how many checks have each colour, then the checks that cost the most points,
 * each with what was measured and the points it cost, linked to its panel in the pipeline.
 *
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages The pipeline.
 * @return void
 */
function tvarkom_obd_render_attention( array $stages ) {
	$counts   = tvarkom_obd_pipeline_counts( $stages );
	$findings = tvarkom_obd_priority_findings( $stages );
	$shown    = array_slice( $findings, 0, TVARKOM_OBD_PRIORITY_FINDINGS );
	$allowed  = tvarkom_obd_icon_allowed_html();
	?>
	<section class="tobd__panel tobd__attention" aria-labelledby="tobd-attention-title">
		<h2 class="tobd__h2" id="tobd-attention-title"><?php esc_html_e( 'Needs attention', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<ul class="tobd__tally">
			<?php foreach ( $counts as $status => $count ) : ?>
				<?php if ( $count > 0 ) : ?>
					<li><?php echo wp_kses( tvarkom_obd_job_icon( $status ), $allowed ); ?><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong> <?php echo esc_html( tvarkom_obd_job_status_label( $status ) ); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<?php if ( empty( $shown ) ) : ?>
			<p class="tobd__empty"><?php esc_html_e( 'No check found a problem in this scan.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
		<?php else : ?>
			<p class="tobd__attention-intro"><?php esc_html_e( 'The problems that cost the most points first.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
			<ol class="tobd__findings">
				<?php foreach ( $shown as $item ) : ?>
					<?php
					$job    = $item['job'];
					$detail = array( $item['stage'], tvarkom_obd_job_status_label( $job['status'] ) );
					if ( '' !== $job['measured'] ) {
						$detail[] = '' !== $job['reference']
							/* translators: 1: measured value, 2: reference value. */
							? sprintf( __( '%1$s (reference %2$s)', 'on-board-diagnostics-scanner-for-woocommerce' ), $job['measured'], $job['reference'] )
							: $job['measured'];
					}
					$inner = wp_kses( tvarkom_obd_job_icon( $job['status'] ), $allowed )
						. '<span class="tobd__finding-body"><span class="tobd__finding-name">' . esc_html( $job['name'] ) . '</span>'
						. '<span class="tobd__finding-meta">' . esc_html( implode( ' · ', $detail ) ) . '</span></span>';
					if ( null !== $job['points'] ) {
						$inner .= '<span class="tobd__finding-points">' . esc_html(
							/* translators: %d: points. */
							sprintf( __( '−%d', 'on-board-diagnostics-scanner-for-woocommerce' ), $job['points'] )
						) . '<span class="screen-reader-text"> ' . esc_html__( 'points', 'on-board-diagnostics-scanner-for-woocommerce' ) . '</span></span>';
					}
					?>
					<li class="tobd__finding--<?php echo esc_attr( $job['status'] ); ?>"><?php echo tvarkom_obd_job_link( $item, 'tobd__finding', $inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<p class="tobd__attention-more">
			<a href="#tobd-pipeline-title">
				<?php
				$rest = count( $findings ) - count( $shown );
				echo esc_html(
					$rest > 0
						/* translators: %s: number of checks. */
						? sprintf( _n( '%s more check with a problem, and every other check, in the Scanner pipeline', '%s more checks with a problem, and every other check, in the Scanner pipeline', $rest, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $rest ) )
						: __( 'Every check is in the Scanner pipeline', 'on-board-diagnostics-scanner-for-woocommerce' )
				);
				?>
			</a>
		</p>
	</section>
	<?php
}

/**
 * The risk bands as a scale (0–49 high, 50–79 moderate, 80–100 low) with the score marked on it.
 *
 * @param int|null $score 0–100, or null when unknown.
 * @return void
 */
function tvarkom_obd_render_bands( $score ) {
	$bands = array(
		array( 'bad', 50, __( '0–49 high', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( 'warn', 30, __( '50–79 moderate', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
		array( 'good', 20, __( '80–100 low', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
	);
	?>
	<div class="tobd__scale">
		<div class="tobd__scale-bar" aria-hidden="true">
			<?php foreach ( $bands as $band ) : ?>
				<span class="tobd--<?php echo esc_attr( $band[0] ); ?>" style="width: <?php echo (int) $band[1]; ?>%"></span>
			<?php endforeach; ?>
			<?php if ( null !== $score ) : ?>
				<span class="tobd__scale-mark" style="left: <?php echo (int) max( 0, min( 100, $score ) ); ?>%"></span>
			<?php endif; ?>
		</div>
		<ul class="tobd__scale-labels">
			<?php foreach ( $bands as $band ) : ?>
				<li style="width: <?php echo (int) $band[1]; ?>%"><?php echo esc_html( $band[2] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="screen-reader-text"><?php esc_html_e( 'Risk bands: 80–100 low risk, 50–79 moderate, 0–49 high.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
	</div>
	<?php
}

/**
 * The store health score card: the gauge with the risk level, the risk bands, the scan's summary
 * sentence, and how many of the scanner's checks the score is based on (a score without some checks
 * says so, never implying a complete diagnosis).
 *
 * @param array<string, mixed>                                                                                $latest   The latest results.
 * @param string                                                                                              $text     The scan's summary sentence ("" when none).
 * @param array<string, mixed>|null                                                                           $scoring  From tvarkom_obd_parse_scoring(), or null.
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages The pipeline.
 * @return void
 */
function tvarkom_obd_render_score_card( array $latest, $text, $scoring, array $stages ) {
	?>
	<section class="tobd__panel tobd__score" aria-labelledby="tobd-score-title">
		<h2 class="tobd__h2" id="tobd-score-title"><?php esc_html_e( 'Store health score', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<?php tvarkom_obd_render_gauge( $latest['score'], $latest['level'] ); ?>
		<?php tvarkom_obd_render_bands( $latest['score'] ); ?>
		<?php if ( '' !== $text ) : ?>
			<p class="tobd__score-text"><?php echo esc_html( $text ); ?></p>
		<?php endif; ?>
		<?php tvarkom_obd_render_coverage( $scoring, $stages ); ?>
	</section>
	<?php
}

/**
 * "Based on 41 of 44 checks; 3 couldn't be measured", and which of the most important checks had
 * no result, from the scan's scoring. Nothing for scans before score model 2.
 *
 * @param array<string, mixed>|null                                                                           $scoring From tvarkom_obd_parse_scoring(), or null.
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages The pipeline, for the checks' names.
 * @return void
 */
function tvarkom_obd_render_coverage( $scoring, array $stages ) {
	if ( null === $scoring ) {
		return;
	}
	$parts = array(
		sprintf(
			/* translators: 1: checks with a result, 2: all checks. */
			__( 'Based on %1$s of %2$s checks', 'on-board-diagnostics-scanner-for-woocommerce' ),
			number_format_i18n( $scoring['measured'] ),
			number_format_i18n( $scoring['total'] )
		),
	);
	if ( $scoring['unavailable'] > 0 ) {
		/* translators: %s: number of checks. */
		$parts[] = sprintf( _n( '%s couldn\'t be measured', '%s couldn\'t be measured', $scoring['unavailable'], 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $scoring['unavailable'] ) );
	}
	if ( $scoring['not_applicable'] > 0 ) {
		/* translators: %s: number of checks. */
		$parts[] = sprintf( _n( '%s doesn\'t apply to this site', '%s don\'t apply to this site', $scoring['not_applicable'], 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $scoring['not_applicable'] ) );
	}
	$not_run = $scoring['total'] - $scoring['performed'];
	if ( $not_run > 0 ) {
		/* translators: %s: number of checks. */
		$parts[] = sprintf( _n( '%s wasn\'t part of this scan', '%s weren\'t part of this scan', $not_run, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $not_run ) );
	}
	echo '<p class="tobd__coverage">' . esc_html( implode( '; ', $parts ) . '.' ) . '</p>';

	$missing = array_map(
		function ( $item ) {
			return $item['job']['name'];
		},
		tvarkom_obd_pipeline_find( $stages, $scoring['missing'] )
	);
	if ( ! empty( $missing ) ) {
		echo '<p class="tobd__coverage tobd--warn">' . esc_html(
			sprintf(
				/* translators: %s: list of checks, e.g. "Cart response, Checkout response". */
				_n( 'Incomplete: %s couldn\'t be measured, so the score doesn\'t include it.', 'Incomplete: %s couldn\'t be measured, so the score doesn\'t include them.', count( $missing ), 'on-board-diagnostics-scanner-for-woocommerce' ),
				wp_sprintf_l( '%l', $missing )
			)
		) . '</p>';
	}
}

/**
 * The history points that can be compared with the latest score: those of its scoring model.
 * Scores of another model stay as they were stored and are counted, never redrawn or recalculated.
 *
 * @param array<int, array{completed_at: int, score: int, model: int}> $history Oldest first.
 * @param int                                                          $model   The latest score's model.
 * @return array{points: array<int, array{completed_at: int, score: int, model: int}>, other: int, other_until: int}
 */
function tvarkom_obd_comparable_history( array $history, $model ) {
	$points = array();
	$other  = 0;
	$until  = 0;
	foreach ( $history as $point ) {
		if ( $point['model'] === $model ) {
			$points[] = $point;
		} else {
			++$other;
			$until = max( $until, $point['completed_at'] );
		}
	}
	return array(
		'points'      => $points,
		'other'       => $other,
		'other_until' => $until,
	);
}

/**
 * A scan's date as a label: the date, with the time when another scan in the list has the same date.
 *
 * @param array<int, array{completed_at: int}> $points All points.
 * @param int                                  $i      The point's index.
 * @return string
 */
function tvarkom_obd_point_label( array $points, $i ) {
	$day = wp_date( 'Y-m-d', $points[ $i ]['completed_at'] );
	foreach ( $points as $j => $other ) {
		if ( $j !== $i && wp_date( 'Y-m-d', $other['completed_at'] ) === $day ) {
			return tvarkom_obd_datetime( $points[ $i ]['completed_at'] );
		}
	}
	return tvarkom_obd_date( $points[ $i ]['completed_at'] );
}

/**
 * Store health trends: how the score changed since the previous scan and since the first, the
 * scores over time, from the store's actual scans (one a week on the free plan). Before a second
 * comparable scan, when the trend starts. Scores of an earlier scoring model are counted, not
 * compared.
 *
 * @param array<int, array{completed_at: int, score: int, model: int}> $history      Oldest first.
 * @param int                                                          $model        The latest score's model.
 * @param int                                                          $next_scan_at When the next scan is allowed (Unix seconds), 0 when now or unknown.
 * @return void
 */
function tvarkom_obd_render_trends( array $history, $model, $next_scan_at ) {
	$comparable = tvarkom_obd_comparable_history( $history, $model );
	$points     = $comparable['points'];
	$n          = count( $points );
	?>
	<section class="tobd__panel tobd__trends" aria-labelledby="tobd-trends-title">
		<h2 class="tobd__h2" id="tobd-trends-title"><?php esc_html_e( 'Store health trends', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<?php if ( $n < 2 ) : ?>
			<div class="tobd__trend-empty">
				<p class="tobd__trend-lead"><?php esc_html_e( 'Your trend starts with your next scan.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
				<p class="tobd__note">
					<?php
					echo esc_html(
						$next_scan_at > time()
							/* translators: %s: date. */
							? sprintf( __( 'Each scan adds a point here, so you can see whether fixes raise your score. Your next scan is available from %s.', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_date( $next_scan_at ) )
							: __( 'Each scan adds a point here, so you can see whether fixes raise your score. You can scan once a week.', 'on-board-diagnostics-scanner-for-woocommerce' )
					);
					?>
				</p>
			</div>
		<?php else : ?>
			<?php
			$last   = $points[ $n - 1 ]['score'];
			$change = $last - $points[ $n - 2 ]['score'];
			$since  = $last - $points[0]['score'];
			$scores = wp_list_pluck( $points, 'score' );
			$best   = max( $scores );
			$lowest = min( $scores );
			tvarkom_obd_render_change( $change, tvarkom_obd_point_label( $points, $n - 2 ) );
			?>
			<dl class="tobd__trend-facts">
				<div>
					<dt>
						<?php
						/* translators: %s: date of the first scan. */
						echo esc_html( sprintf( __( 'Since %s', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_point_label( $points, 0 ) ) );
						?>
					</dt>
					<dd><?php echo esc_html( tvarkom_obd_signed( $since ) ); ?></dd>
				</div>
				<div><dt><?php esc_html_e( 'Scans', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $n ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Highest', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $best ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Lowest', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $lowest ) ); ?></dd></div>
			</dl>
			<h3 class="tobd__h3" id="tobd-history-title"><?php esc_html_e( 'Score over time', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
			<?php tvarkom_obd_render_history( $points ); ?>
		<?php endif; ?>
		<?php if ( $comparable['other'] > 0 ) : ?>
			<p class="tobd__note tobd__model-note">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: number of scores, 2: date. */
						_n(
							'%1$s earlier score, until %2$s, came from the previous scoring model, so it isn\'t compared or charted here.',
							'%1$s earlier scores, until %2$s, came from the previous scoring model, so they aren\'t compared or charted here.',
							$comparable['other'],
							'on-board-diagnostics-scanner-for-woocommerce'
						),
						number_format_i18n( $comparable['other'] ),
						tvarkom_obd_date( $comparable['other_until'] )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * "+6", "−3" or "0".
 *
 * @param int $points A change in points.
 * @return string
 */
function tvarkom_obd_signed( $points ) {
	if ( $points > 0 ) {
		return '+' . number_format_i18n( $points );
	}
	return $points < 0 ? '−' . number_format_i18n( -$points ) : '0';
}

/**
 * The change since the previous scan, as a sentence with its tone.
 *
 * @param int    $change Points since the previous scan.
 * @param string $when   The previous scan's date (and time when needed).
 * @return void
 */
function tvarkom_obd_render_change( $change, $when ) {
	if ( $change > 0 ) {
		$tone = 'good';
		/* translators: 1: points, 2: date. */
		$text = sprintf( _n( 'Up %1$s point since the previous scan, on %2$s', 'Up %1$s points since the previous scan, on %2$s', $change, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $change ), $when );
	} elseif ( $change < 0 ) {
		$tone = 'bad';
		/* translators: 1: points, 2: date. */
		$text = sprintf( _n( 'Down %1$s point since the previous scan, on %2$s', 'Down %1$s points since the previous scan, on %2$s', -$change, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( -$change ), $when );
	} else {
		$tone = 'none';
		/* translators: %s: date. */
		$text = sprintf( __( 'No change since the previous scan, on %s', 'on-board-diagnostics-scanner-for-woocommerce' ), $when );
	}
	echo '<p class="tobd__trend tobd--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</p>';
}
