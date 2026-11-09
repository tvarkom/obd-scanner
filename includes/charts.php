<?php
/**
 * The analysis's free sections as charts (founder decision of 9 Nov 2026): the store at a glance as
 * tiles, sales and checkout as figures, an order outcome bar and a customer donut (with the
 * modelled impact under them), the catalog as tiles and a bar, and the store's operations as tiles
 * and labels. Drawn from the scan's figures (tvarkom_obd_parse_figures()) with CSS and inline SVG;
 * every value is escaped. A section without its figures falls back to its texts.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a section as charts when it has some; false when it should be shown as its texts.
 *
 * @param array{id: string, heading: string, blocks: array<int, array<string, mixed>>} $section  The section.
 * @param array<string, array<string, mixed>>|null                                     $figures The scan's figures.
 * @return bool
 */
function tvarkom_obd_render_chart_section( array $section, $figures ) {
	switch ( $section['id'] ) {
		case 'store_profile':
			return null !== $figures ? tvarkom_obd_chart_profile( $figures['profile'] ) : false;
		case 'commercial':
			return null !== $figures && tvarkom_obd_chart_sales( $section, $figures );
		case 'catalog':
			return null !== $figures && tvarkom_obd_chart_catalog( $figures['catalog'] );
		case 'operations':
			return null !== $figures && tvarkom_obd_chart_operations( $figures['operations'] );
	}
	return false;
}

/**
 * Figure tiles: a big value with its label under it.
 *
 * @param array<int, array{0: string, 1: string}> $tiles [label, value] pairs; empty values are left out.
 * @return void
 */
function tvarkom_obd_tiles( array $tiles ) {
	$tiles = array_filter(
		$tiles,
		function ( $tile ) {
			return '' !== $tile[1];
		}
	);
	if ( empty( $tiles ) ) {
		return;
	}
	echo '<div class="tobd__tiles">';
	foreach ( $tiles as $tile ) {
		echo '<div class="tobd__tile"><span class="tobd__tile-value">' . esc_html( $tile[1] ) . '</span><span class="tobd__tile-label">' . esc_html( $tile[0] ) . '</span></div>';
	}
	echo '</div>';
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
 * Your store at a glance: country, currency, size and orders a day as tiles. Without the figures
 * (no business context), the section's own table is shown instead.
 *
 * @param array<string, mixed> $p The profile figures.
 * @return bool
 */
function tvarkom_obd_chart_profile( array $p ) {
	if ( null === $p['country'] && null === $p['size'] ) {
		return false;
	}
	tvarkom_obd_tiles(
		array(
			array( __( 'Country', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['country'] ),
			array( __( 'Currency', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['currency'] ),
			array( __( 'Store size', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $p['size'] ),
			array( __( 'Paid orders a day', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $p['orders_per_day'] ? '' : number_format_i18n( $p['orders_per_day'], 1 ) ),
		)
	);
	return true;
}

/**
 * Sales & checkout: the 30-day figures, the order outcomes, new and returning customers, then the
 * section's own texts from the modelled impact on (its heading, estimates and note).
 *
 * @param array{id: string, heading: string, blocks: array<int, array<string, mixed>>} $section The section.
 * @param array<string, array<string, mixed>>                                          $figures The scan's figures.
 * @return bool
 */
function tvarkom_obd_chart_sales( array $section, array $figures ) {
	$o      = $figures['orders'];
	$failed = null !== $o['failed'] && null !== $o['paid'] && $o['failed'] + $o['paid'] > 0
		? (int) round( 100 * $o['failed'] / ( $o['failed'] + $o['paid'] ) ) : null;
	tvarkom_obd_tiles(
		array(
			array( __( 'Paid orders, last 30 days', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $o['paid'] ) ),
			array( __( 'Gross sales, last 30 days', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $figures['money']['sales'] ),
			array( __( 'Average order value', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) $figures['money']['aov'] ),
			/* translators: %d: percentage. */
			array( __( 'Orders that failed at payment', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $failed ? '' : sprintf( __( '%d%%', 'on-board-diagnostics-scanner-for-woocommerce' ), $failed ) ),
		)
	);

	echo '<div class="tobd__grid tobd__grid--charts">';
	tvarkom_obd_outcome_bar(
		__( 'Order outcomes, last 30 days', 'on-board-diagnostics-scanner-for-woocommerce' ),
		array(
			array( __( 'Paid or on hold', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['paid'], 'good' ),
			array( __( 'Failed', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['failed'], 'bad' ),
			array( __( 'Pending', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['pending'], 'warn' ),
			array( __( 'Cancelled', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['cancelled'], 'none' ),
		)
	);
	tvarkom_obd_customer_donut( $figures['customers']['new'], $figures['customers']['returning'] );
	echo '</div>';

	// The texts from the modelled impact on: its heading, the estimates and how they're built.
	$impact = false;
	foreach ( $section['blocks'] as $block ) {
		$impact = $impact || 'heading' === $block['type'];
		if ( $impact ) {
			tvarkom_obd_render_block( $block );
		} elseif ( 'note' === $block['type'] ) {
			tvarkom_obd_render_block( $block ); // Where the figures come from.
		}
	}
	return true;
}

/**
 * A stacked bar of parts of a whole, with a legend of counts. Nothing when all are unknown or 0.
 *
 * @param string                                               $title The chart's title.
 * @param array<int, array{0: string, 1: int|null, 2: string}> $parts [label, count, tone].
 * @return void
 */
function tvarkom_obd_outcome_bar( $title, array $parts ) {
	$total = 0;
	foreach ( $parts as $part ) {
		$total += (int) $part[1];
	}
	if ( 0 === $total ) {
		return;
	}
	echo '<figure class="tobd__chart"><figcaption class="tobd__chart-title">' . esc_html( $title ) . '</figcaption><div class="tobd__stack" aria-hidden="true">';
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
	echo '</ul></figure>';
}

/**
 * New and returning customers as a donut, with the returning share in the middle.
 *
 * @param int|null $fresh     New customers in 30 days.
 * @param int|null $returning Returning customers in 30 days.
 * @return void
 */
function tvarkom_obd_customer_donut( $fresh, $returning ) {
	if ( null === $fresh || null === $returning || 0 === $fresh + $returning ) {
		return;
	}
	$share = $returning / ( $fresh + $returning );
	$c     = 2 * M_PI * 40;
	/* translators: 1: new customers, 2: returning customers, 3: returning share in percent. */
	$label = sprintf( __( '%1$s new and %2$s returning customers: %3$d%% returning', 'on-board-diagnostics-scanner-for-woocommerce' ), number_format_i18n( $fresh ), number_format_i18n( $returning ), (int) round( 100 * $share ) );
	?>
	<figure class="tobd__chart tobd__donut-chart">
		<figcaption class="tobd__chart-title"><?php esc_html_e( 'Customers, last 30 days', 'on-board-diagnostics-scanner-for-woocommerce' ); ?></figcaption>
		<div class="tobd__donut-row">
			<svg class="tobd__donut" viewBox="0 0 100 100" role="img" aria-label="<?php echo esc_attr( $label ); ?>" focusable="false">
				<circle class="tobd__donut-new" cx="50" cy="50" r="40" />
				<circle class="tobd__donut-returning" cx="50" cy="50" r="40" stroke-dasharray="<?php echo esc_attr( number_format( $share * $c, 2, '.', '' ) . ' ' . number_format( $c, 2, '.', '' ) ); ?>" transform="rotate(-90 50 50)" />
				<text x="50" y="50" text-anchor="middle" dominant-baseline="central" class="tobd__donut-value"><?php echo esc_html( (int) round( 100 * $share ) . '%' ); ?></text>
			</svg>
			<ul class="tobd__legend tobd__legend--column">
				<li class="tobd__legend-returning"><span class="tobd__swatch"></span><?php esc_html_e( 'Returning', 'on-board-diagnostics-scanner-for-woocommerce' ); ?> <strong><?php echo esc_html( number_format_i18n( $returning ) ); ?></strong></li>
				<li class="tobd__legend-new"><span class="tobd__swatch"></span><?php esc_html_e( 'New', 'on-board-diagnostics-scanner-for-woocommerce' ); ?> <strong><?php echo esc_html( number_format_i18n( $fresh ) ); ?></strong></li>
			</ul>
		</div>
	</figure>
	<?php
}

/**
 * Products & inventory: products and variations as tiles and a bar, and stock management.
 *
 * @param array<string, mixed> $c The catalog figures.
 * @return bool
 */
function tvarkom_obd_chart_catalog( array $c ) {
	if ( null === $c['products'] && null === $c['stock_management'] ) {
		return false;
	}
	tvarkom_obd_tiles(
		array(
			array( __( 'Published products', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $c['products'] ) ),
			array( __( 'Variations', 'on-board-diagnostics-scanner-for-woocommerce' ), tvarkom_obd_count( $c['variations'] ) ),
			array(
				__( 'Stock management', 'on-board-diagnostics-scanner-for-woocommerce' ),
				null === $c['stock_management'] ? '' : ( $c['stock_management'] ? __( 'On', 'on-board-diagnostics-scanner-for-woocommerce' ) : __( 'Off', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
			),
		)
	);
	tvarkom_obd_outcome_bar(
		__( 'Catalog items', 'on-board-diagnostics-scanner-for-woocommerce' ),
		array(
			array( __( 'Products', 'on-board-diagnostics-scanner-for-woocommerce' ), $c['products'], 'ink' ),
			array( __( 'Variations', 'on-board-diagnostics-scanner-for-woocommerce' ), $c['variations'], 'accent' ),
		)
	);
	return true;
}

/**
 * Store operations: checkout type and order storage as tiles, payment and shipping methods as labels.
 *
 * @param array<string, mixed> $o The operations figures.
 * @return bool
 */
function tvarkom_obd_chart_operations( array $o ) {
	$checkout = array(
		'block'   => __( 'Checkout block', 'on-board-diagnostics-scanner-for-woocommerce' ),
		'classic' => __( 'Classic checkout', 'on-board-diagnostics-scanner-for-woocommerce' ),
	);
	tvarkom_obd_tiles(
		array(
			array( __( 'Payment methods', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) count( $o['payment_gateways'] ) ),
			array( __( 'Shipping methods', 'on-board-diagnostics-scanner-for-woocommerce' ), (string) count( $o['shipping_methods'] ) ),
			array( __( 'Checkout', 'on-board-diagnostics-scanner-for-woocommerce' ), null === $o['checkout_type'] ? '' : $checkout[ $o['checkout_type'] ] ),
			array(
				__( 'High-Performance Order Storage', 'on-board-diagnostics-scanner-for-woocommerce' ),
				null === $o['hpos'] ? '' : ( $o['hpos'] ? __( 'On', 'on-board-diagnostics-scanner-for-woocommerce' ) : __( 'Off', 'on-board-diagnostics-scanner-for-woocommerce' ) ),
			),
		)
	);
	foreach (
		array(
			array( __( 'Payment methods', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['payment_gateways'] ),
			array( __( 'Shipping methods', 'on-board-diagnostics-scanner-for-woocommerce' ), $o['shipping_methods'] ),
		) as $group
	) {
		if ( empty( $group[1] ) ) {
			continue;
		}
		echo '<p class="tobd__chart-title">' . esc_html( $group[0] ) . '</p><ul class="tobd__chips">';
		foreach ( $group[1] as $name ) {
			echo '<li>' . esc_html( $name ) . '</li>';
		}
		echo '</ul>';
	}
	return true;
}
