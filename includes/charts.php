<?php
/**
 * The store profile (sales and checkout, products and inventory, store operations) and the
 * estimated revenue at risk as a chart (founder decisions of 9 and 10 Nov 2026), drawn from the
 * scan's figures (tvarkom_obd_parse_figures()) with CSS. Every value is escaped. Without figures
 * (scans stored before them), the sections' own texts are shown instead.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A count as text, or "" when unknown.
 *
 * @param int|null $value The count.
 * @return string
 */
function tvarkom_obd_count( $value ) {
	return null === $value ? '' : number_format_i18n( $value );
}

/**
 * Small figures: a value with its label, in a grid. Empty values are left out.
 *
 * @param array<int, array{0: string, 1: string}> $figures [label, value] pairs.
 * @return void
 */
function tvarkom_obd_mini_figures( array $figures ) {
	$figures = array_filter(
		$figures,
		function ( $figure ) {
			return '' !== $figure[1];
		}
	);
	if ( empty( $figures ) ) {
		return;
	}
	echo '<dl class="tobd__minis">';
	foreach ( $figures as $figure ) {
		echo '<div><dt>' . esc_html( $figure[0] ) . '</dt><dd>' . esc_html( $figure[1] ) . '</dd></div>';
	}
	echo '</dl>';
}

/**
 * A thin stacked bar of parts of a whole with a legend of counts. Nothing when all are unknown or 0.
 *
 * @param array<int, array{0: string, 1: int|null, 2: string}> $parts [label, count, tone].
 * @return void
 */
function tvarkom_obd_stack( array $parts ) {
	$total = 0;
	foreach ( $parts as $part ) {
		$total += (int) $part[1];
	}
	if ( 0 === $total ) {
		return;
	}
	echo '<div class="tobd__stack" aria-hidden="true">';
	foreach ( $parts as $part ) {
		if ( $part[1] > 0 ) {
			echo '<span class="tobd--' . esc_attr( $part[2] ) . '" style="width: ' . esc_attr( number_format( 100 * $part[1] / $total, 1, '.', '' ) ) . '%"></span>';
		}
	}
	echo '</div><ul class="tobd__legend">';
	foreach ( $parts as $part ) {
		if ( null !== $part[1] ) {
			echo '<li class="tobd--' . esc_attr( $part[2] ) . '"><span class="tobd__swatch"></span>' . esc_html( $part[0] ) . ' <strong>' . esc_html( number_format_i18n( $part[1] ) ) . '</strong></li>';
		}
	}
	echo '</ul>';
}

/**
 * Labels in a row (payment and shipping methods).
 *
 * @param string   $title The row's title.
 * @param string[] $items The labels.
 * @return void
 */
function tvarkom_obd_chips( $title, array $items ) {
	if ( empty( $items ) ) {
		return;
	}
	echo '<p class="tobd__chips-title">' . esc_html( $title ) . '</p><ul class="tobd__chips">';
	foreach ( $items as $item ) {
		echo '<li>' . esc_html( $item ) . '</li>';
	}
	echo '</ul>';
}

/**
 * The store profile in detail, below the overview: sales and checkout, products and inventory, and
 * the payment and shipping methods, side by side on wide screens. The store's key facts (country,
 * currency, size, orders a day, checkout and order storage) are in the store summary
 * (tvarkom_obd_render_store_summary()). Without figures, the free sections' texts.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The analysis's free sections.
 * @param array<string, array<string, mixed>>|null                                                 $figures  The scan's figures.
 * @return void
 */
function tvarkom_obd_render_store_widget( array $sections, $figures ) {
	?>
	<section class="tobd__panel tobd__store-widget" aria-labelledby="tobd-store-title">
		<h2 class="tobd__h2" id="tobd-store-title"><?php esc_html_e( 'Store profile', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<?php
		if ( null === $figures ) {
			foreach ( $sections as $section ) {
				if ( in_array( $section['id'], array( 'store_profile', 'commercial', 'catalog', 'operations' ), true ) ) {
					echo '<h3 class="tobd__h3">' . esc_html( $section['heading'] ) . '</h3>';
					foreach ( $section['blocks'] as $block ) {
						if ( 'heading' === $block['type'] ) {
							break; // The modelled impact has its own panel.
						}
						tvarkom_obd_render_block( $block );
					}
				}
			}
			echo '</section>';
			return;
		}
		$o      = $figures['orders'];
		$c      = $figures['catalog'];
		$ops    = $figures['operations'];
		$failed = null !== $o['failed'] && null !== $o['paid'] && $o['failed'] + $o['paid'] > 0
			? (int) round( 100 * $o['failed'] / ( $o['failed'] + $o['paid'] ) ) : null;
		?>
		<div class="tobd__profile-groups">
			<div class="tobd__profile-group">
				<h3 class="tobd__h3"><?php esc_html_e( 'Sales & checkout, last 30 days', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
				<?php
				tvarkom_obd_mini_figures(
					array(
						array( __( 'Paid orders', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $o['paid'] ) ),
						array( __( 'Gross sales', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $figures['money']['sales'] ),
						array( __( 'Average order value', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $figures['money']['aov'] ),
						/* translators: %d: percentage. */
						array( __( 'Failed at payment', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $failed ? '' : sprintf( __( '%d%%', 'on-board-diagnostics-scanner-for-woocommerce' ), $failed ) ),
					)
				);
				tvarkom_obd_stack(
					array(
						array( __( 'Paid', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['paid'], 'good' ),
						array( __( 'Failed', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['failed'], 'bad' ),
						array( __( 'Pending', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['pending'], 'warn' ),
						array( __( 'Cancelled', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['cancelled'], 'none' ),
					)
				);
				tvarkom_obd_stack(
					array(
						array( __( 'Returning customers', 'on-board-diagnostics-scanner-for-woocommerce' ), $figures['customers']['returning'], 'good' ),
						array( __( 'New customers', 'on-board-diagnostics-scanner-for-woocommerce' ), $figures['customers']['new'], 'line' ),
					)
				);
				?>
			</div>
			<div class="tobd__profile-group">
				<h3 class="tobd__h3"><?php esc_html_e( 'Products & inventory', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
				<?php
				tvarkom_obd_mini_figures(
					array(
						array( __( 'Products', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $c['products'] ) ),
						array( __( 'Variations', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $c['variations'] ) ),
						array( __( 'Stock management', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_on_off( $c['stock_management'] ) ),
					)
				);
				?>
			</div>
			<div class="tobd__profile-group">
				<h3 class="tobd__h3"><?php esc_html_e( 'Store operations', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
				<?php
				tvarkom_obd_chips( __( 'Payment methods', 'on-board-diagnostics-scanner-for-woocommerce' ), $ops['payment_gateways'] );
				tvarkom_obd_chips( __( 'Shipping methods', 'on-board-diagnostics-scanner-for-woocommerce' ), $ops['shipping_methods'] );
				?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * The commercial section's impact blocks: what follows its heading (the estimate's texts and
 * note). Empty when the scan has none.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The analysis's free sections.
 * @return array<int, array<string, mixed>> Without the heading.
 */
function tvarkom_obd_impact_blocks( array $sections ) {
	foreach ( $sections as $section ) {
		if ( 'commercial' !== $section['id'] ) {
			continue;
		}
		$blocks = array();
		$found  = false;
		foreach ( $section['blocks'] as $block ) {
			if ( $found ) {
				$blocks[] = $block;
			}
			$found = $found || 'heading' === $block['type'];
		}
		return $blocks;
	}
	return array();
}

/**
 * The total at risk as a percentage of the 30 days' sales, or "" when the sales aren't known
 * exactly (only a range was sent).
 *
 * @param array<string, mixed>      $total The total at risk.
 * @param array<string, mixed>|null $sales The sales it's based on.
 * @return string
 */
function tvarkom_obd_impact_share( array $total, $sales ) {
	if ( null === $sales || $sales['low'] <= 0 || $sales['low'] !== $sales['high'] ) {
		return '';
	}
	$low  = (int) round( 100 * $total['low'] / $sales['low'] );
	$high = (int) round( 100 * $total['high'] / $sales['high'] );
	if ( $low === $high ) {
		/* translators: %d: percentage. */
		return sprintf( __( '%d%%', 'on-board-diagnostics-scanner-for-woocommerce' ), $low );
	}
	/* translators: 1: lowest percentage, 2: highest percentage. */
	return sprintf( __( '%1$d%%–%2$d%%', 'on-board-diagnostics-scanner-for-woocommerce' ), $low, $high );
}

/**
 * One cause of the estimate as a range on a shared scale (0 to the largest high estimate): the
 * bar runs from the low estimate to the high one. Under it, what was measured or counted, and
 * the pipeline checks it's based on.
 *
 * @param string                                                                                 $label What causes it.
 * @param array<string, mixed>                                                                   $range Its low, high and label.
 * @param string                                                                                 $note  What was measured or counted, or "".
 * @param float                                                                                  $max   The scale (the largest high estimate).
 * @param array<int, array{stage: string, panel: string, order: int, job: array<string, mixed>}> $jobs  The pipeline checks it's based on.
 * @return void
 */
function tvarkom_obd_impact_range( $label, array $range, $note, $max, array $jobs = array() ) {
	$pct   = function ( $value ) use ( $max ) {
		return $max > 0 ? min( 100, 100 * $value / $max ) : 0;
	};
	$left  = $pct( $range['low'] );
	$width = $pct( $range['high'] ) - $left;
	?>
	<li>
		<div class="tobd__range-head"><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $range['label'] ); ?></strong></div>
		<div class="tobd__range-track" aria-hidden="true">
			<span class="tobd__range-span" style="left: <?php echo esc_attr( number_format( $left, 1, '.', '' ) ); ?>%; width: <?php echo esc_attr( number_format( $width, 1, '.', '' ) ); ?>%"></span>
		</div>
		<?php if ( '' !== $note ) : ?>
			<p class="tobd__range-note"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $jobs ) ) : ?>
			<p class="tobd__range-checks">
				<?php
				esc_html_e( 'Based on:', 'on-board-diagnostics-scanner-for-woocommerce' );
				foreach ( $jobs as $item ) {
					echo ' ' . tvarkom_obd_job_link( $item, 'tobd__check-link', wp_kses( tvarkom_obd_job_icon( $item['job']['status'] ), tvarkom_obd_icon_allowed_html() ) . esc_html( $item['job']['name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
				}
				?>
			</p>
		<?php endif; ?>
	</li>
	<?php
}

/**
 * The estimated revenue at risk: the scan's modelled estimate (its total, each cause as a range
 * linked to the checks it's based on), the store's figures it starts from, and the scan's own
 * assumptions and sources one click away. Always labelled as a modelled scenario, never as lost
 * sales. Scans without the figures show the estimate's texts; a store outside the modelled range
 * gets the scan's text on why. Nothing is calculated here but the bars' lengths and the share.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}>            $sections The analysis's free sections.
 * @param array<string, mixed>|null                                                                           $figures  The scan's figures.
 * @param array<int, array{id: string, name: string, status: string, jobs: array<int, array<string, mixed>>}> $pipeline The pipeline, for the checks behind each cause.
 * @return void
 */
function tvarkom_obd_render_impact_widget( array $sections, $figures, array $pipeline = array() ) {
	$blocks = tvarkom_obd_impact_blocks( $sections );
	$impact = null !== $figures && isset( $figures['impact'] ) ? $figures['impact'] : null;
	$notes  = array_values(
		array_filter(
			$blocks,
			function ( $block ) {
				return 'note' === $block['type'];
			}
		)
	);
	?>
	<section class="tobd__panel tobd__impact-widget" aria-labelledby="tobd-impact-title">
		<h2 class="tobd__h2" id="tobd-impact-title"><?php esc_html_e( 'Estimated revenue at risk', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
		<?php
		if ( null !== $impact && 'estimated' === $impact['status'] ) {
			$slow    = $impact['slow_checkout'];
			$fail    = $impact['failed_orders'];
			$max     = max( null === $slow ? 0 : $slow['high'], null === $fail ? 0 : $fail['high'] );
			$share   = tvarkom_obd_impact_share( $impact['total'], $impact['sales'] );
			$caption = '' === $share
				? __( 'a month, modelled from this scan', 'on-board-diagnostics-scanner-for-woocommerce' )
				/* translators: %s: percentage, or range of percentages, of the month's sales. */
				: sprintf( __( 'a month, modelled from this scan: %s of your gross sales', 'on-board-diagnostics-scanner-for-woocommerce' ), $share );
			$orders = $figures['orders'];
			$money  = $figures['money'];
			$inputs = array_filter(
				array(
					array( __( 'Gross sales, last 30 days', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $money['sales'] ),
					array( __( 'Average order value', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $money['aov'] ),
					array( __( 'Orders failed at payment', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $orders['failed'] ) ),
				),
				function ( $input ) {
					return '' !== $input[1];
				}
			);
			?>
			<p class="tobd__impact-total">
				<span class="tobd__impact-value"><?php echo esc_html( $impact['total']['label'] ); ?></span>
				<span class="tobd__impact-caption"><?php echo esc_html( $caption ); ?></span>
			</p>
			<p class="tobd__impact-disclaimer"><?php esc_html_e( 'A modelled scenario, not measured lost sales: the times and counts are measured on your store, the money figures are estimated from them.', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></p>
			<ul class="tobd__ranges">
				<?php
				if ( null !== $slow ) {
					tvarkom_obd_impact_range(
						__( 'Slow cart or checkout', 'on-board-diagnostics-scanner-for-woocommerce' ),
						$slow,
						null === $slow['excess_seconds'] ? '' : sprintf(
							/* translators: %s: seconds. */
							__( 'Measured: the slower of the two takes %s s longer than a healthy response.', 'on-board-diagnostics-scanner-for-woocommerce' ),
							number_format_i18n( $slow['excess_seconds'], 2 )
						),
						$max,
						tvarkom_obd_pipeline_find( $pipeline, array( 'cart_response', 'checkout_response' ) )
					);
				}
				if ( null !== $fail ) {
					tvarkom_obd_impact_range(
						__( 'Failed orders', 'on-board-diagnostics-scanner-for-woocommerce' ),
						$fail,
						null === $fail['count'] ? '' : sprintf(
							/* translators: %s: number of orders. */
							_n( 'Counted: %s order failed at payment in the last 30 days, valued at your average order value. Some shoppers retry, so not all of it is lost.', 'Counted: %s orders failed at payment in the last 30 days, valued at your average order value. Some shoppers retry, so not all of it is lost.', $fail['count'], 'on-board-diagnostics-scanner-for-woocommerce' ),
							number_format_i18n( $fail['count'] )
						),
						$max
					);
				}
				?>
			</ul>
			<?php if ( ! empty( $inputs ) ) : ?>
				<h3 class="tobd__h3"><?php esc_html_e( 'Store figures the estimate starts from', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
				<dl class="tobd__inputs">
					<?php foreach ( $inputs as $input ) : ?>
						<div><dt><?php echo esc_html( $input[0] ); ?></dt><dd><?php echo esc_html( $input[1] ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<?php if ( ! empty( $notes ) ) : ?>
				<details class="tobd__how">
					<summary><?php esc_html_e( 'Assumptions and sources', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></summary>
					<?php
					foreach ( $notes as $note ) {
						echo '<p>' . esc_html( $note['text'] ) . '</p>';
					}
					?>
				</details>
			<?php endif; ?>
			<?php
		} elseif ( ! empty( $blocks ) ) {
			// No figures (scans before them), or no estimate for this store: the scan's own texts.
			foreach ( $blocks as $block ) {
				tvarkom_obd_render_block( $block );
			}
		} else {
			echo '<p class="tobd__empty">' . esc_html__( 'There is no sales impact to estimate from this scan.', 'on-board-diagnostics-scanner-for-woocommerce' ) . '</p>';
		}
		?>
	</section>
	<?php
}
