<?php
/**
 * The pipeline (founder decisions of 9 Nov 2026): every check of the scan as a job in a stage, like a
 * CI/CD pipeline, for the agencies and developers who look after stores. Red: a scored check over
 * its critical threshold; orange: over its reference; yellow: a problem outside the score; green:
 * passes; grey: not measured. A job's panel says what was measured against which reference and what
 * it cost; never how to fix it or what causes it (the paid version). Built by tvarkom's scanner
 * (audit-tool scanner/src/reporters/pipeline.ts); checked and escaped here.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The job and stage colours, worst last.
 */
const TVARKOM_OBD_JOB_STATUSES = array( 'grey', 'green', 'yellow', 'orange', 'red' );

/**
 * Checks the analysis's pipeline: known colours, texts only, bounded sizes.
 *
 * @param mixed $raw The analysis's pipeline.
 * @return array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}>
 */
function tvarkom_obd_parse_pipeline( $raw ) {
	if ( ! is_array( $raw ) || ! isset( $raw['stages'] ) || ! is_array( $raw['stages'] ) ) {
		return array();
	}
	$status = function ( $value ) {
		return is_string( $value ) && in_array( $value, TVARKOM_OBD_JOB_STATUSES, true ) ? $value : 'grey';
	};
	$stages = array();
	foreach ( array_slice( $raw['stages'], 0, 12 ) as $stage ) {
		if ( ! is_array( $stage ) || ! isset( $stage['jobs'] ) || ! is_array( $stage['jobs'] ) ) {
			continue;
		}
		$jobs = array();
		foreach ( array_slice( $stage['jobs'], 0, 20 ) as $job ) {
			if ( ! is_array( $job ) ) {
				continue;
			}
			$name = tvarkom_obd_analysis_text( isset( $job['name'] ) ? $job['name'] : null );
			if ( '' === $name ) {
				continue;
			}
			$jobs[] = array(
				'id'        => sanitize_key( isset( $job['id'] ) && is_string( $job['id'] ) ? $job['id'] : '' ),
				'name'      => $name,
				'status'    => $status( isset( $job['status'] ) ? $job['status'] : null ),
				'about'     => tvarkom_obd_analysis_text( isset( $job['about'] ) ? $job['about'] : null ),
				'measured'  => tvarkom_obd_analysis_text( isset( $job['measured'] ) ? $job['measured'] : null ),
				'reference' => tvarkom_obd_analysis_text( isset( $job['reference'] ) ? $job['reference'] : null ),
				'critical'  => tvarkom_obd_analysis_text( isset( $job['critical'] ) ? $job['critical'] : null ),
				'points'    => isset( $job['points'] ) && is_int( $job['points'] ) && $job['points'] > 0 && $job['points'] <= 100 ? $job['points'] : null,
			);
		}
		$name = tvarkom_obd_analysis_text( isset( $stage['name'] ) ? $stage['name'] : null );
		if ( '' !== $name && ! empty( $jobs ) ) {
			$stages[] = array(
				'id'     => sanitize_key( isset( $stage['id'] ) && is_string( $stage['id'] ) ? $stage['id'] : '' ),
				'name'   => $name,
				'status' => $status( isset( $stage['status'] ) ? $stage['status'] : null ),
				'jobs'   => $jobs,
			);
		}
	}
	return $stages;
}

/**
 * A colour's label, so status never depends on colour alone.
 *
 * @param string $status red, orange, yellow, green or grey.
 * @return string
 */
function tvarkom_obd_job_status_label( $status ) {
	$labels = array(
		'red'    => __( 'Critical', 'on-board-diagnostics-scanner-for-woocommerce' ),
		'orange' => __( 'Over the reference', 'on-board-diagnostics-scanner-for-woocommerce' ),
		'yellow' => __( 'Needs attention', 'on-board-diagnostics-scanner-for-woocommerce' ),
		'green'  => __( 'Passed', 'on-board-diagnostics-scanner-for-woocommerce' ),
		'grey'   => __( 'Not measured', 'on-board-diagnostics-scanner-for-woocommerce' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['grey'];
}

/**
 * A status icon (decorative: the label is always next to it or in the button's name).
 *
 * @param string $status red, orange, yellow, green or grey.
 * @return string SVG markup.
 */
function tvarkom_obd_job_icon( $status ) {
	$marks = array(
		'green'  => '<path d="M5 8.2l2 2 4-4.4" />',
		'yellow' => '<path d="M8 4.5v4M8 11v.5" />',
		'orange' => '<path d="M8 4.5v4M8 11v.5" />',
		'red'    => '<path d="M5.5 5.5l5 5M10.5 5.5l-5 5" />',
		'grey'   => '<path d="M5 8h6" />',
	);
	$mark  = isset( $marks[ $status ] ) ? $marks[ $status ] : $marks['grey'];
	return '<svg class="tobd__icon tobd__icon--' . esc_attr( $status ) . '" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="7" />' . $mark . '</svg>';
}

/**
 * A job's panel id (its button's aria-controls), from its stage's and its own position; the
 * overview's findings link to it too.
 *
 * @param int $stage The stage's index.
 * @param int $job   The job's index in its stage.
 * @return string
 */
function tvarkom_obd_job_panel_id( $stage, $job ) {
	return 'tobd-job-' . $stage . '-' . $job;
}

/**
 * The pipeline, laid out like GitLab's: each stage a card listing its jobs, the cards joined by a
 * line, and one panel per job (shown on click).
 *
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $stages From tvarkom_obd_parse_pipeline().
 * @return void
 */
function tvarkom_obd_render_pipeline( array $stages ) {
	if ( empty( $stages ) ) {
		return;
	}
	$icon_kses = array(
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
	?>
	<section class="tobd__panel tobd__pipeline" aria-labelledby="tobd-pipeline-title">
		<h2 class="tobd__h2" id="tobd-pipeline-title"><?php esc_html_e( 'Scanner pipeline', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<div class="tobd__stages">
			<?php foreach ( $stages as $s => $stage ) : ?>
				<div class="tobd__stage">
					<h3 class="tobd__stage-name"><?php echo esc_html( $stage['name'] ); ?></h3>
					<ul class="tobd__jobs">
						<?php foreach ( $stage['jobs'] as $j => $job ) : ?>
							<?php $panel = tvarkom_obd_job_panel_id( $s, $j ); ?>
							<li>
								<button type="button" class="tobd__job tobd__job--<?php echo esc_attr( $job['status'] ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel ); ?>">
									<?php echo wp_kses( tvarkom_obd_job_icon( $job['status'] ), $icon_kses ); ?>
									<span class="tobd__job-name"><?php echo esc_html( $job['name'] ); ?></span>
									<span class="screen-reader-text"><?php echo esc_html( tvarkom_obd_job_status_label( $job['status'] ) ); ?></span>
									<svg class="tobd__job-action" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6.5 4.5l3.5 3.5-3.5 3.5" /></svg>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="tobd__job-panels">
			<?php
			foreach ( $stages as $s => $stage ) {
				foreach ( $stage['jobs'] as $j => $job ) {
					tvarkom_obd_render_job_panel( tvarkom_obd_job_panel_id( $s, $j ), $stage['name'], $job );
				}
			}
			?>
		</div>
	</section>
	<?php
}

/**
 * One job's panel, like a CI job log: the check, its status, what was measured and the reference.
 *
 * @param string               $id    The panel's id.
 * @param string               $stage The stage's name.
 * @param array<string, mixed> $job   The job.
 * @return void
 */
function tvarkom_obd_render_job_panel( $id, $stage, array $job ) {
	$rows = array(
		array( __( 'Status', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_job_status_label( $job['status'] ) ),
		array( __( 'Measured', 'on-board-diagnostics-scanner-for-woocommerce' ), $job['measured'] ),
		array( __( 'Reference', 'on-board-diagnostics-scanner-for-woocommerce' ), $job['reference'] ),
		array( __( 'Critical', 'on-board-diagnostics-scanner-for-woocommerce' ), $job['critical'] ),
		/* translators: %d: points. */
		array( __( 'Points lost', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $job['points'] ? '' : sprintf( __( '−%d', 'on-board-diagnostics-scanner-for-woocommerce' ), $job['points'] ) ),
	);
	?>
	<section class="tobd__job-panel tobd__job-panel--<?php echo esc_attr( $job['status'] ); ?>" id="<?php echo esc_attr( $id ); ?>" aria-label="<?php echo esc_attr( $stage . ' › ' . $job['name'] ); ?>">
		<p class="tobd__job-title"><?php echo esc_html( $stage . ' › ' . $job['name'] ); ?></p>
		<?php if ( '' !== $job['about'] ) : ?>
			<p class="tobd__job-about"><?php echo esc_html( $job['about'] ); ?></p>
		<?php endif; ?>
		<dl class="tobd__job-log">
			<?php foreach ( $rows as $row ) : ?>
				<?php if ( '' !== $row[1] ) : ?>
					<div><dt><?php echo esc_html( $row[0] ); ?></dt><dd><?php echo esc_html( $row[1] ); ?></dd></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</dl>
	</section>
	<?php
}
