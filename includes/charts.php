<?php
/**
 * The store widget (founder decisions of 9 Nov 2026): the store at a glance, sales and checkout,
 * products and inventory, and store operations in one compact panel beside the score and the
 * points lost, drawn from the scan's figures (tvarkom_obd_parse_figures()) with CSS; and the
 * modelled sales impact under them. Every value is escaped. Without figures (scans stored before
 * them), the sections' own texts are shown instead.
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
 * The store widget: the store at a glance, sales and checkout, products and inventory, and store
 * operations. Without figures, the free sections' texts.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The analysis's free sections.
 * @param array<string, array<string, mixed>>|null                                                 $figures  The scan's figures.
 * @return void
 */
function tvarkom_obd_render_store_widget( array $sections, $figures ) {
	$on_off = function ( $value ) {
		return null === $value ? '' : ( $value ? __( 'On', 'on-board-diagnostics-scanner-for-woocommerce' ) : __( 'Off', 'on-board-diagnostics-scanner-for-woocommerce' ) );
	};
	?>
	<section class="tobd__panel tobd__store-widget" aria-labelledby="tobd-store-title">
		<h2 class="tobd__h2" id="tobd-store-title"><?php esc_html_e( 'Your store at a glance', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h2>
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
		$p      = $figures['profile'];
		$o      = $figures['orders'];
		$c      = $figures['catalog'];
		$ops    = $figures['operations'];
		$failed = null !== $o['failed'] && null !== $o['paid'] && $o['failed'] + $o['paid'] > 0
			? (int) round( 100 * $o['failed'] / ( $o['failed'] + $o['paid'] ) ) : null;
		tvarkom_obd_mini_figures(
			array(
				array( __( 'Country', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['country'] ),
				array( __( 'Currency', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['currency'] ),
				array( __( 'Store size', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['size'] ),
				array( __( 'Paid orders a day', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $p['orders_per_day'] ? '' : number_format_i18n( $p['orders_per_day'], 1 ) ),
			)
		);
		?>
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
		<h3 class="tobd__h3"><?php esc_html_e( 'Products & inventory', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
		<?php
		tvarkom_obd_mini_figures(
			array(
				array( __( 'Products', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $c['products'] ) ),
				array( __( 'Variations', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $c['variations'] ) ),
				array( __( 'Stock management', 'on-board-diagnostics-scanner-for-woocommerce' ), $on_off( $c['stock_management'] ) ),
			)
		);
		?>
		<h3 class="tobd__h3"><?php esc_html_e( 'Store operations', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></h3>
		<?php
		$checkout = array(
			'block'   => __( 'Checkout block', 'on-board-diagnostics-scanner-for-woocommerce' ),
			'classic' => __( 'Classic checkout', 'on-board-diagnostics-scanner-for-woocommerce' ),
		);
		tvarkom_obd_mini_figures(
			array(
				array( __( 'Checkout', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $ops['checkout_type'] ? '' : $checkout[ $ops['checkout_type'] ] ),
				array( __( 'Order storage (HPOS)', 'on-board-diagnostics-scanner-for-woocommerce' ), $on_off( $ops['hpos'] ) ),
			)
		);
		tvarkom_obd_chips( __( 'Payment methods', 'on-board-diagnostics-scanner-for-woocommerce' ), $ops['payment_gateways'] );
		tvarkom_obd_chips( __( 'Shipping methods', 'on-board-diagnostics-scanner-for-woocommerce' ), $ops['shipping_methods'] );
		?>
	</section>
	<?php
}

/**
 * The modelled sales impact: the sales section's own texts from its impact heading on. Nothing
 * when the scan has none.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The analysis's free sections.
 * @return void
 */
function tvarkom_obd_render_impact( array $sections ) {
	foreach ( $sections as $section ) {
		if ( 'commercial' !== $section['id'] ) {
			continue;
		}
		$blocks = array();
		$found  = false;
		foreach ( $section['blocks'] as $block ) {
			$found = $found || 'heading' === $block['type'];
			if ( $found ) {
				$blocks[] = $block;
			}
		}
		if ( empty( $blocks ) ) {
			return;
		}
		$title = array_shift( $blocks );
		echo '<section class="tobd__panel tobd__impact-panel" aria-labelledby="tobd-impact-title"><h2 class="tobd__h2" id="tobd-impact-title">' . esc_html( $title['text'] ) . '</h2>';
		foreach ( $blocks as $block ) {
			tvarkom_obd_render_block( $block );
		}
		echo '</section>';
	}
}
