<?php
/**
 * The overview above the pipeline (founder decisions of 10 Nov 2026): the store summary, the
 * checks that need attention first, the risk bands and the score's trend. Everything is read from
 * the scan's pipeline, figures and history as they are; nothing here scores or estimates. Every
 * finding links to its job's panel in the pipeline (includes/pipeline.php), which stays unchanged.
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
 * The red and orange jobs, worst first: red before orange, then the most points lost, then the
 * pipeline's order. Yellow jobs (problems outside the score) stay in the pipeline.
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
				return in_array( $item['job']['status'], array( 'red', 'orange' ), true );
			}
		)
	);
	usort(
		$findings,
		function ( $a, $b ) use ( $rank ) {
			$by_status = $rank[ $b['job']['status'] ] - $rank[ $a['job']['status'] ];
			if ( 0 !== $by_status ) {
				return $by_status;
			}
			$by_points = (int) $b['job']['points'] - (int) $a['job']['points'];
			return 0 !== $by_points ? $by_points : $a['order'] - $b['order'];
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
		$facts    = array(
			array( __( 'Country', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['country'] ),
			array( __( 'Currency', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['currency'] ),
			array( __( 'Store size', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['size'] ),
			array( __( 'Paid orders a day', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $p['orders_per_day'] ? '' : number_format_i18n( $p['orders_per_day'], 1 ) ),
			array( __( 'Checkout', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $ops['checkout_type'] ? '' : $checkout[ $ops['checkout_type'] ] ),
			array( __( 'Order storage (HPOS)', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_on_off( $ops['hpos'] ) ),
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
		<?php endif; ?>
	</section>
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
 * What needs attention: how many checks have each colour, then the worst red and orange checks,
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
		<h2 class="tobd__h2" id="tobd-attention-title"><?php esc_html_e( 'What needs attention', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<ul class="tobd__tally">
			<?php foreach ( $counts as $status => $count ) : ?>
				<?php if ( $count > 0 ) : ?>
					<li><?php echo wp_kses( tvarkom_obd_job_icon( $status ), $allowed ); ?><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong> <?php echo esc_html( tvarkom_obd_job_status_label( $status ) ); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<?php if ( empty( $shown ) ) : ?>
			<p class="tobd__empty"><?php esc_html_e( 'No check is critical or over its reference.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
		<?php else : ?>
			<p class="tobd__attention-intro"><?php esc_html_e( 'Critical checks first, then those over their reference, by the points they cost.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
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
						? sprintf( _n( '%s more critical or over-reference check, and every other check, in the Scanner pipeline', '%s more critical or over-reference checks, and every other check, in the Scanner pipeline', $rest, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $rest ) )
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
 * The change since the previous day with a scan, from the score history; nothing before a second day.
 *
 * @param array<int, array{completed_at: int, score: int}> $daily From tvarkom_obd_daily_history(), oldest first.
 * @return void
 */
function tvarkom_obd_render_trend( array $daily ) {
	$n = count( $daily );
	if ( $n < 2 ) {
		return;
	}
	$change = $daily[ $n - 1 ]['score'] - $daily[ $n - 2 ]['score'];
	$date   = tvarkom_obd_date( $daily[ $n - 2 ]['completed_at'] );
	if ( $change > 0 ) {
		$tone = 'good';
		/* translators: 1: points, 2: date. */
		$text = sprintf( _n( 'Up %1$s point since the scan on %2$s', 'Up %1$s points since the scan on %2$s', $change, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $change ), $date );
	} elseif ( $change < 0 ) {
		$tone = 'bad';
		/* translators: 1: points, 2: date. */
		$text = sprintf( _n( 'Down %1$s point since the scan on %2$s', 'Down %1$s points since the scan on %2$s', -$change, 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( -$change ), $date );
	} else {
		$tone = 'none';
		/* translators: %s: date. */
		$text = sprintf( __( 'No change since the scan on %s', 'on-board-diagnostics-scanner-for-woocommerce' ), $date );
	}
	echo '<p class="tobd__trend tobd--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</p>';
}
