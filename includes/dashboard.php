<?php
/**
 * The dashboard: the latest results from tvarkom (GET /v1/results) as a score gauge, the checks
 * that cost points, measured values against their reference, and the score over time. Charts are
 * inline SVG and CSS drawn here, with every number and text escaped; no script and no outside
 * resource.
 *
 * The references are tvarkom's scanner's (audit-tool scanner/src/analyzers/riskEngine.ts
 * THRESHOLDS; the score in scanner/src/analyzers/score.ts): keep them in line.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The scoring rules, by the id the gateway sends in lost_points: a title and what was measured.
 * Ids this version doesn't know are shown with a generic title.
 *
 * @return array<string, array{0: string, 1: string}> Id => [title, explanation].
 */
function tvarkom_obd_rules() {
	return array(
		'slow_server'      => array(
			__( 'Slow server response', 'pirkoo' ),
			__( 'Your homepage took more than 0.3 seconds to start answering (more than 0.8 seconds costs the most points). Every page of your store waits for this.', 'pirkoo' ),
		),
		'slow_checkout'    => array(
			__( 'Slow cart or checkout', 'pirkoo' ),
			__( 'Your cart or checkout page took more than 0.3 seconds to start answering. These pages can\'t be served from a page cache, so shoppers wait for your server every time.', 'pirkoo' ),
		),
		'too_many_files'   => array(
			__( 'Too many files on the homepage', 'pirkoo' ),
			__( 'Your homepage asks the browser to load more than 60 scripts, stylesheets, images and other files.', 'pirkoo' ),
		),
		'too_heavy'        => array(
			__( 'Heavy homepage', 'pirkoo' ),
			__( 'Your homepage and the files it loads weigh more than 3.5 MB.', 'pirkoo' ),
		),
		'uncached'         => array(
			__( 'Files without browser caching', 'pirkoo' ),
			__( 'More than half of the scripts, stylesheets and images on your homepage don\'t tell browsers to keep a copy, so returning shoppers download them again.', 'pirkoo' ),
		),
		'autoload'         => array(
			__( 'Large autoloaded options', 'pirkoo' ),
			__( 'WordPress loads more than 800 KB of settings from your database on every request (more than 2 MB costs the most points).', 'pirkoo' ),
		),
		'background_tasks' => array(
			__( 'Background task problems', 'pirkoo' ),
			__( 'WooCommerce\'s scheduled background tasks are failing or piling up: 10 or more failed in the last 7 days, or 100 or more are overdue (50 or more failures cost the most points).', 'pirkoo' ),
		),
	);
}

/**
 * The risk level's label.
 *
 * @param string $level PASS, WARNING, CRITICAL_RISK or "".
 * @return string
 */
function tvarkom_obd_level_label( $level ) {
	$labels = array(
		'PASS'          => __( 'Low risk', 'pirkoo' ),
		'WARNING'       => __( 'Moderate risk', 'pirkoo' ),
		'CRITICAL_RISK' => __( 'High risk', 'pirkoo' ),
	);
	return isset( $labels[ $level ] ) ? $labels[ $level ] : __( 'Not rated', 'pirkoo' );
}

/**
 * The tone (good, warn, bad, none) of a risk level, for colors.
 *
 * @param string $level PASS, WARNING, CRITICAL_RISK or "".
 * @return string
 */
function tvarkom_obd_level_tone( $level ) {
	$tones = array(
		'PASS'          => 'good',
		'WARNING'       => 'warn',
		'CRITICAL_RISK' => 'bad',
	);
	return isset( $tones[ $level ] ) ? $tones[ $level ] : 'none';
}

/**
 * A tone's label, so status never depends on color alone.
 *
 * @param string $tone good, warn, bad or none.
 * @return string
 */
function tvarkom_obd_tone_label( $tone ) {
	$labels = array(
		'good' => __( 'Good', 'pirkoo' ),
		'warn' => __( 'Needs attention', 'pirkoo' ),
		'bad'  => __( 'Critical', 'pirkoo' ),
	);
	return isset( $labels[ $tone ] ) ? $labels[ $tone ] : __( 'Not measured', 'pirkoo' );
}

/**
 * "9 November 2026" in the site's date format.
 *
 * @param int $timestamp Unix seconds.
 * @return string
 */
function tvarkom_obd_date( $timestamp ) {
	return (string) wp_date( (string) get_option( 'date_format' ), $timestamp );
}

/**
 * "9 November 2026 20:07" in the site's date and time formats.
 *
 * @param int $timestamp Unix seconds.
 * @return string
 */
function tvarkom_obd_datetime( $timestamp ) {
	return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * A number from the metrics, or null when it's missing or not a number.
 *
 * @param array<string, mixed> $metrics Metric id => value.
 * @param string               $id      Metric id.
 * @return float|null
 */
function tvarkom_obd_number( array $metrics, $id ) {
	return isset( $metrics[ $id ] ) && is_numeric( $metrics[ $id ] ) ? (float) $metrics[ $id ] : null;
}

/**
 * A size in KB as "950 KB" or "1.8 MB".
 *
 * @param float $kb Kilobytes.
 * @return string
 */
function tvarkom_obd_size( $kb ) {
	return (string) size_format( (int) round( $kb * 1024 ), $kb >= 1024 ? 1 : 0 );
}

/**
 * Renders the dashboard for results with a latest audit, top to bottom: the store summary; the
 * store health score, what needs attention and the store's health trends; the store profile and
 * the other free sections (traffic and marketing); then the pipeline. Scans before the pipeline
 * show the points lost instead of what needs attention, and their measured values as panels.
 *
 * @param array{status: string, next_scan_at: int, latest: array<string, mixed>|null, history: array<int, array{completed_at: int, score: int, model: int}>} $results From tvarkom_obd_fetch_results().
 * @return void
 */
function tvarkom_obd_render_dashboard( array $results ) {
	$latest = $results['latest'];
	if ( null === $latest ) {
		return;
	}
	$analysis = isset( $latest['analysis'] ) ? $latest['analysis'] : null;
	$sections = null !== $analysis ? $analysis['sections'] : array();
	$pipeline = null !== $analysis ? $analysis['pipeline'] : array();
	$figures  = null !== $analysis ? $analysis['figures'] : null;
	$scoring  = null !== $analysis && isset( $analysis['scoring'] ) ? $analysis['scoring'] : null;
	$text     = null !== $analysis ? $analysis['summary']['text'] : '';

	// The store summary (includes/overview.php).
	tvarkom_obd_render_store_summary( $figures, $pipeline, (int) $latest['completed_at'] );
	?>
	<div class="tobd__overview">
		<?php
		tvarkom_obd_render_score_card( $latest, $text, $scoring, $pipeline );
		if ( ! empty( $pipeline ) ) {
			tvarkom_obd_render_attention( $pipeline ); // Needs attention (includes/overview.php).
		} else {
			// Scans before the pipeline: where the score lost its points instead.
			?>
			<section class="tobd__panel tobd__lost" aria-labelledby="tobd-lost-title">
				<h2 class="tobd__h2" id="tobd-lost-title"><?php esc_html_e( 'Where your store lost points', 'pirkoo' ); ?></h2>
				<?php tvarkom_obd_render_lost_points( $latest['lost_points'] ); ?>
			</section>
			<?php
		}
		tvarkom_obd_render_trends( $results['history'], (int) $latest['model'], (int) $results['next_scan_at'] );
		?>
	</div>
	<?php
	if ( null !== $analysis ) {
		tvarkom_obd_render_store_widget( $sections, $figures ); // Store profile (includes/charts.php).
	}
	// The other free sections (traffic and marketing), as their texts, full width.
	tvarkom_obd_render_analysis_sections(
		array_values(
			array_filter(
				$sections,
				function ( $section ) {
					return ! in_array( $section['id'], array( 'store_profile', 'commercial', 'catalog', 'operations' ), true );
				}
			)
		)
	);
	if ( empty( $pipeline ) ) {
		// Scans before the pipeline: the measured values as their own panels.
		tvarkom_obd_render_metric_panels( $latest['metrics'] );
	}
	// Every check, stage by stage (includes/pipeline.php).
	tvarkom_obd_render_pipeline( $pipeline );
}

/**
 * The measured values as panels (server response, homepage weight, database and server, security
 * and email), for scans without a pipeline (before plugin 0.2.0's).
 *
 * @param array<string, mixed> $metrics Metric id => value.
 * @return void
 */
function tvarkom_obd_render_metric_panels( array $metrics ) {
	?>
	<section class="tobd__panel" aria-labelledby="tobd-speed-title">
		<h2 class="tobd__h2" id="tobd-speed-title"><?php esc_html_e( 'Server response', 'pirkoo' ); ?></h2>
		<p class="tobd__intro"><?php esc_html_e( 'How long your server took to start answering each page, the median of several requests from our scanner. Reference: 0.3 seconds or less; above 0.8 seconds is critical.', 'pirkoo' ); ?></p>
		<?php
		$speed = array(
			'ttfb_ms'     => __( 'Homepage', 'pirkoo' ),
			'cart_ms'     => __( 'Cart', 'pirkoo' ),
			'checkout_ms' => __( 'Checkout', 'pirkoo' ),
		);
		foreach ( $speed as $id => $label ) {
			$ms = tvarkom_obd_number( $metrics, $id );
			tvarkom_obd_render_bar(
				$label,
				$ms,
				null === $ms ? '' : sprintf(
					/* translators: %s: seconds, e.g. 0.52. */
					__( '%s s', 'pirkoo' ),
					number_format_i18n( $ms / 1000, 2 )
				),
				300,
				800,
				1200
			);
		}
		?>
	</section>

	<div class="tobd__grid">
		<section class="tobd__panel" aria-labelledby="tobd-page-title">
			<h2 class="tobd__h2" id="tobd-page-title"><?php esc_html_e( 'Homepage weight', 'pirkoo' ); ?></h2>
			<?php
			$kb = tvarkom_obd_number( $metrics, 'page_weight_kb' );
			tvarkom_obd_render_bar( __( 'Size, with every file it loads', 'pirkoo' ), $kb, null === $kb ? '' : tvarkom_obd_size( $kb ), 3584, 3584, 5376 );
			$files = tvarkom_obd_number( $metrics, 'requests' );
			tvarkom_obd_render_bar( __( 'Files', 'pirkoo' ), $files, null === $files ? '' : number_format_i18n( $files ), 60, 60, 90 );
			?>
			<p class="tobd__note"><?php esc_html_e( 'Reference: 3.5 MB or less, and 60 files or fewer.', 'pirkoo' ); ?></p>
		</section>

		<section class="tobd__panel" aria-labelledby="tobd-db-title">
			<h2 class="tobd__h2" id="tobd-db-title"><?php esc_html_e( 'Database and server', 'pirkoo' ); ?></h2>
			<?php
			$autoload = tvarkom_obd_number( $metrics, 'autoload_kb' );
			tvarkom_obd_render_bar( __( 'Autoloaded options', 'pirkoo' ), $autoload, null === $autoload ? '' : tvarkom_obd_size( $autoload ), 800, 2048, 3072, true );
			?>
			<p class="tobd__note"><?php esc_html_e( 'Settings WordPress loads on every request. Reference: under 800 KB; above 2 MB is critical.', 'pirkoo' ); ?></p>
			<dl class="tobd__facts">
				<?php
				tvarkom_obd_render_fact( __( 'PHP version', 'pirkoo' ), isset( $metrics['php_version'] ) ? (string) $metrics['php_version'] : '', 'none' );
				$cache = isset( $metrics['object_cache'] ) ? (string) $metrics['object_cache'] : '';
				tvarkom_obd_render_fact(
					__( 'Persistent object cache', 'pirkoo' ),
					'None' === $cache ? __( 'None', 'pirkoo' ) : $cache,
					'' === $cache ? 'none' : ( 'None' === $cache ? 'warn' : 'good' )
				);
				?>
			</dl>
		</section>
	</div>

	<section class="tobd__panel" aria-labelledby="tobd-security-title">
		<h2 class="tobd__h2" id="tobd-security-title"><?php esc_html_e( 'Security and email', 'pirkoo' ); ?></h2>
		<dl class="tobd__facts tobd__facts--wide">
			<?php tvarkom_obd_render_security( $metrics ); ?>
		</dl>
	</section>

	<?php
}

/**
 * The score as a half-circle gauge, with the number and the risk level as text (readable with
 * images and color off).
 *
 * @param int|null $score 0–100, or null when unknown.
 * @param string   $level PASS, WARNING, CRITICAL_RISK or "".
 * @return void
 */
function tvarkom_obd_render_gauge( $score, $level ) {
	$tone = tvarkom_obd_level_tone( $level );
	$arc  = '';
	if ( null !== $score && $score > 0 ) {
		$angle = M_PI * $score / 100;
		$x     = 120 - 100 * cos( $angle );
		$y     = 120 - 100 * sin( $angle );
		$arc   = sprintf( 'M20 120 A100 100 0 0 1 %.2f %.2f', $x, $y );
	}
	?>
	<div class="tobd__gauge tobd--<?php echo esc_attr( $tone ); ?>">
		<div class="tobd__dial">
		<svg viewBox="0 0 240 132" role="img" aria-labelledby="tobd-gauge-label" focusable="false">
			<title id="tobd-gauge-label">
				<?php
				echo esc_html(
					null === $score
						? __( 'Score not available', 'pirkoo' )
						/* translators: 1: score from 0 to 100, 2: risk level, e.g. "High risk". */
						: sprintf( __( 'Score %1$d out of 100, %2$s', 'pirkoo' ), $score, tvarkom_obd_level_label( $level ) )
				);
				?>
			</title>
			<path class="tobd__gauge-track" d="M20 120 A100 100 0 0 1 220 120" />
			<?php if ( '' !== $arc ) : ?>
				<path class="tobd__gauge-value" d="<?php echo esc_attr( $arc ); ?>" />
			<?php endif; ?>
		</svg>
		<p class="tobd__gauge-number"><span class="tobd__score-value"><?php echo null === $score ? '—' : esc_html( (string) $score ); ?></span><span class="tobd__score-of">/100</span></p>
		</div>
		<p class="tobd__badge"><?php echo esc_html( tvarkom_obd_level_label( $level ) ); ?></p>
	</div>
	<?php
}

/**
 * The scoring rules that cost points, most first, each with what was measured.
 *
 * @param array<int, array{id: string, points: int|null}> $lost From the latest results.
 * @return void
 */
function tvarkom_obd_render_lost_points( array $lost ) {
	if ( empty( $lost ) ) {
		echo '<p class="tobd__empty">' . esc_html__( 'No check cost points in this scan.', 'pirkoo' ) . '</p>';
		return;
	}
	$rules = tvarkom_obd_rules();
	echo '<p class="tobd__intro">' . esc_html__( 'Every store starts at 100. Each problem below took points away.', 'pirkoo' ) . '</p>';
	echo '<ul class="tobd__rules">';
	foreach ( $lost as $item ) {
		$rule   = isset( $rules[ $item['id'] ] ) ? $rules[ $item['id'] ] : array( __( 'Other check', 'pirkoo' ), '' );
		$points = null === $item['points'] ? '—' : '−' . number_format_i18n( $item['points'] );
		echo '<li><span class="tobd__points">' . esc_html( $points ) . '</span><div><strong>' . esc_html( $rule[0] ) . '</strong>';
		if ( '' !== $rule[1] ) {
			echo '<p>' . esc_html( $rule[1] ) . '</p>';
		}
		echo '</div></li>';
	}
	echo '</ul>';
}

/**
 * One measured value as a horizontal bar against its reference: green up to $good, amber up to
 * $bad, red above, with the reference marked.
 *
 * @param string     $label     What was measured.
 * @param float|null $value     The value, or null when it wasn't measured.
 * @param string     $display   The value as text.
 * @param float      $good      Highest good value.
 * @param float      $bad       Highest value before critical (same as $good when there's no amber band).
 * @param float      $scale     Bar length's minimum full scale.
 * @param bool       $exclusive Whether $good itself is already over (autoload: under 800 KB is good).
 * @return void
 */
function tvarkom_obd_render_bar( $label, $value, $display, $good, $bad, $scale, $exclusive = false ) {
	if ( null === $value ) {
		$tone = 'none';
	} elseif ( $exclusive ? $value < $good : $value <= $good ) {
		$tone = 'good';
	} elseif ( $value <= $bad ) {
		$tone = 'warn';
	} else {
		$tone = 'bad';
	}
	$max   = max( $scale, null === $value ? 0 : $value * 1.05 );
	$width = null === $value ? 0 : min( 100, $value / $max * 100 );
	$mark  = $good / $max * 100;
	?>
	<div class="tobd__bar tobd--<?php echo esc_attr( $tone ); ?>">
		<div class="tobd__bar-head">
			<span class="tobd__bar-label"><?php echo esc_html( $label ); ?></span>
			<span class="tobd__bar-value"><?php echo esc_html( '' === $display ? __( 'Not measured', 'pirkoo' ) : $display ); ?> <span class="tobd__tone"><?php echo esc_html( tvarkom_obd_tone_label( $tone ) ); ?></span></span>
		</div>
		<div class="tobd__track" aria-hidden="true">
			<span class="tobd__fill" style="width: <?php echo esc_attr( number_format( $width, 1, '.', '' ) ); ?>%"></span>
			<span class="tobd__mark" style="left: <?php echo esc_attr( number_format( $mark, 1, '.', '' ) ); ?>%"></span>
		</div>
	</div>
	<?php
}

/**
 * One fact: a label, its value and a status.
 *
 * @param string $label The label.
 * @param string $value The value ("" when unknown).
 * @param string $tone  good, warn, bad or none (no status shown).
 * @return void
 */
function tvarkom_obd_render_fact( $label, $value, $tone ) {
	?>
	<div class="tobd__fact tobd--<?php echo esc_attr( $tone ); ?>">
		<dt><?php echo esc_html( $label ); ?></dt>
		<dd>
			<?php echo esc_html( '' === $value ? __( 'Not measured', 'pirkoo' ) : $value ); ?>
			<?php if ( 'none' !== $tone ) : ?>
				<span class="tobd__tone"><?php echo esc_html( tvarkom_obd_tone_label( $tone ) ); ?></span>
			<?php endif; ?>
		</dd>
	</div>
	<?php
}

/**
 * The HTTPS certificate and the email records of the store's domain.
 *
 * @param array<string, mixed> $metrics Metric id => value.
 * @return void
 */
function tvarkom_obd_render_security( array $metrics ) {
	$days = tvarkom_obd_number( $metrics, 'cert_days_left' );
	if ( null === $days ) {
		tvarkom_obd_render_fact( __( 'HTTPS certificate', 'pirkoo' ), '', 'none' );
	} elseif ( $days < 0 ) {
		tvarkom_obd_render_fact( __( 'HTTPS certificate', 'pirkoo' ), __( 'Expired', 'pirkoo' ), 'bad' );
	} else {
		tvarkom_obd_render_fact(
			__( 'HTTPS certificate', 'pirkoo' ),
			/* translators: %s: number of days. */
			sprintf( _n( '%s day left', '%s days left', (int) $days, 'pirkoo' ), number_format_i18n( $days ) ),
			$days > 21 ? 'good' : 'warn'
		);
	}

	$spf    = isset( $metrics['spf'] ) ? (string) $metrics['spf'] : '';
	$spf_as = array(
		'valid'    => array( __( 'Found', 'pirkoo' ), 'good' ),
		'missing'  => array( __( 'None found', 'pirkoo' ), 'warn' ),
		'multiple' => array( __( 'More than one found', 'pirkoo' ), 'warn' ),
	);
	$spf_is = isset( $spf_as[ $spf ] ) ? $spf_as[ $spf ] : array( '', 'none' );
	tvarkom_obd_render_fact( __( 'SPF email record', 'pirkoo' ), $spf_is[0], $spf_is[1] );

	$dmarc    = isset( $metrics['dmarc'] ) ? (string) $metrics['dmarc'] : '';
	$dmarc_as = array(
		'present' => array( __( 'Found', 'pirkoo' ), 'good' ),
		'missing' => array( __( 'None found', 'pirkoo' ), 'warn' ),
	);
	$dmarc_is = isset( $dmarc_as[ $dmarc ] ) ? $dmarc_as[ $dmarc ] : array( '', 'none' );
	tvarkom_obd_render_fact( __( 'DMARC email record', 'pirkoo' ), $dmarc_is[0], $dmarc_is[1] );
}

/**
 * The score over time as a line chart on the risk bands, every scan's score written over its point
 * (up to 12 points; more would overlap) and the first and last dates under the chart. Scans on the
 * same day are labelled with their time. Drawn at about the size it shows, so its text stays readable.
 *
 * @param array<int, array{completed_at: int, score: int}> $history Oldest first, at least 2 points of one scoring model.
 * @return void
 */
function tvarkom_obd_render_history( array $history ) {
	if ( count( $history ) < 2 ) {
		return;
	}
	$width  = 360;
	$height = 150;
	$left   = 30;
	$right  = 14;
	$top    = 20;
	$bottom = 24;
	$n      = count( $history );
	$values = $n <= 12;
	$x      = function ( $i ) use ( $width, $left, $right, $n ) {
		return $left + $i * ( $width - $left - $right ) / ( $n - 1 );
	};
	$y      = function ( $score ) use ( $height, $top, $bottom ) {
		return $top + ( 100 - $score ) * ( $height - $top - $bottom ) / 100;
	};
	$points = array();
	$labels = array();
	foreach ( $history as $i => $point ) {
		$points[] = sprintf( '%.1f,%.1f', $x( $i ), $y( $point['score'] ) );
		$labels[] = sprintf(
			/* translators: 1: score, 2: date. */
			__( '%1$d on %2$s', 'pirkoo' ),
			$point['score'],
			tvarkom_obd_point_label( $history, $i )
		);
	}
	/* translators: %s: list of scores with their dates. */
	$summary = sprintf( __( 'Score history: %s', 'pirkoo' ), implode( ', ', $labels ) );
	$plot    = $width - $left - $right;
	?>
	<svg class="tobd__history" viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" role="img" aria-label="<?php echo esc_attr( $summary ); ?>" focusable="false">
		<rect class="tobd__band tobd--good" x="<?php echo (int) $left; ?>" y="<?php echo esc_attr( (string) $y( 100 ) ); ?>" width="<?php echo (int) $plot; ?>" height="<?php echo esc_attr( (string) ( $y( 80 ) - $y( 100 ) ) ); ?>" />
		<rect class="tobd__band tobd--warn" x="<?php echo (int) $left; ?>" y="<?php echo esc_attr( (string) $y( 80 ) ); ?>" width="<?php echo (int) $plot; ?>" height="<?php echo esc_attr( (string) ( $y( 50 ) - $y( 80 ) ) ); ?>" />
		<rect class="tobd__band tobd--bad" x="<?php echo (int) $left; ?>" y="<?php echo esc_attr( (string) $y( 50 ) ); ?>" width="<?php echo (int) $plot; ?>" height="<?php echo esc_attr( (string) ( $y( 0 ) - $y( 50 ) ) ); ?>" />
		<?php foreach ( array( 100, 80, 50, 0 ) as $tick ) : ?>
			<text class="tobd__axis" x="<?php echo (int) ( $left - 6 ); ?>" y="<?php echo esc_attr( (string) ( $y( $tick ) + 4 ) ); ?>" text-anchor="end"><?php echo (int) $tick; ?></text>
		<?php endforeach; ?>
		<polyline class="tobd__line" points="<?php echo esc_attr( implode( ' ', $points ) ); ?>" />
		<?php foreach ( $history as $i => $point ) : ?>
			<circle class="tobd__dot<?php echo $i === $n - 1 ? ' tobd__dot--last' : ''; ?>" cx="<?php echo esc_attr( sprintf( '%.1f', $x( $i ) ) ); ?>" cy="<?php echo esc_attr( sprintf( '%.1f', $y( $point['score'] ) ) ); ?>" r="4"><title><?php echo esc_html( $labels[ $i ] ); ?></title></circle>
			<?php if ( $values || $i === $n - 1 ) : ?>
				<text class="tobd__point-value" x="<?php echo esc_attr( sprintf( '%.1f', $x( $i ) ) ); ?>" y="<?php echo esc_attr( sprintf( '%.1f', $y( $point['score'] ) - 9 ) ); ?>" text-anchor="middle" aria-hidden="true"><?php echo (int) $point['score']; ?></text>
			<?php endif; ?>
		<?php endforeach; ?>
		<text class="tobd__axis" x="<?php echo (int) $left; ?>" y="<?php echo (int) ( $height - 6 ); ?>"><?php echo esc_html( tvarkom_obd_point_label( $history, 0 ) ); ?></text>
		<text class="tobd__axis" x="<?php echo (int) ( $width - $right ); ?>" y="<?php echo (int) ( $height - 6 ); ?>" text-anchor="end"><?php echo esc_html( tvarkom_obd_point_label( $history, $n - 1 ) ); ?></text>
	</svg>
	<?php
}
