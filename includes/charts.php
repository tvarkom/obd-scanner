<?php
/**
 * The store profile (sales and checkout, products and inventory, store operations; founder
 * decisions of 9 and 10 Nov 2026), drawn from the scan's figures (pirkoo_parse_figures())
 * with CSS: only what the plugin counted, never a modelled money figure (removed on 10 Nov 2026).
 * Every value is escaped. Without figures (scans stored before them), the sections' own texts are
 * shown instead.
 *
 * @package Pirkoo
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
function pirkoo_count( $value ) {
	return null === $value ? '' : number_format_i18n( $value );
}

/**
 * Small figures: a value with its label, in a grid. Empty values are left out.
 *
 * @param array<int, array{0: string, 1: string}> $figures [label, value] pairs.
 * @return void
 */
function pirkoo_mini_figures( array $figures ) {
	$figures = array_filter(
		$figures,
		function ( $figure ) {
			return '' !== $figure[1];
		}
	);
	if ( empty( $figures ) ) {
		return;
	}
	echo '<dl class="pirkoo__minis">';
	foreach ( $figures as $figure ) {
		// A value longer than a tile (a sales range) gets two tiles' width instead of wrapping.
		$wide = mb_strlen( $figure[1] ) > 12 ? ' class="pirkoo__mini--wide"' : '';
		echo '<div' . $wide . '><dt>' . esc_html( $figure[0] ) . '</dt><dd title="' . esc_attr( $figure[1] ) . '">' . esc_html( $figure[1] ) . '</dd></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wide is a fixed attribute.
	}
	echo '</dl>';
}

/**
 * A thin stacked bar of parts of a whole with a legend of counts. Nothing when all are unknown or 0.
 *
 * @param array<int, array{0: string, 1: int|null, 2: string}> $parts [label, count, tone].
 * @return void
 */
function pirkoo_stack( array $parts ) {
	$total = 0;
	foreach ( $parts as $part ) {
		$total += (int) $part[1];
	}
	if ( 0 === $total ) {
		return;
	}
	echo '<div class="pirkoo__stack" aria-hidden="true">';
	foreach ( $parts as $part ) {
		if ( $part[1] > 0 ) {
			echo '<span class="pirkoo--' . esc_attr( $part[2] ) . '" style="width: ' . esc_attr( number_format( 100 * $part[1] / $total, 1, '.', '' ) ) . '%"></span>';
		}
	}
	echo '</div><ul class="pirkoo__legend">';
	foreach ( $parts as $part ) {
		if ( null !== $part[1] ) {
			echo '<li class="pirkoo--' . esc_attr( $part[2] ) . '"><span class="pirkoo__swatch"></span>' . esc_html( $part[0] ) . ' <strong>' . esc_html( number_format_i18n( $part[1] ) ) . '</strong></li>';
		}
	}
	echo '</ul>';
}

/**
 * Labels in a row (payment and shipping methods). An item still in its identifier form (a method
 * neither Pirkoo nor this store has a name for) is shown as code, with a note saying so.
 *
 * @param string                                $title The row's title.
 * @param array<int, array{0: string, 1: bool}> $items [label, whether it is an identifier].
 * @return void
 */
function pirkoo_chips( $title, array $items ) {
	if ( empty( $items ) ) {
		return;
	}
	echo '<p class="pirkoo__chips-title">' . esc_html( $title ) . '</p><ul class="pirkoo__chips">';
	$ids = false;
	foreach ( $items as $item ) {
		if ( $item[1] ) {
			$ids = true;
			echo '<li class="pirkoo__chip--id"><code>' . esc_html( $item[0] ) . '</code></li>';
		} else {
			echo '<li>' . esc_html( $item[0] ) . '</li>';
		}
	}
	echo '</ul>';
	if ( $ids ) {
		echo '<p class="pirkoo__note">' . esc_html__( 'Shown in code: the identifier WooCommerce gives a method this store no longer has a name for (for example, a plugin that was switched off).', 'pirkoo' ) . '</p>';
	}
}

/**
 * The payment gateways' and shipping methods' names: the scan's names, with any identifier the scan
 * couldn't name replaced by this store's own title for it (read here, never sent to Pirkoo).
 *
 * @param string[] $names The scan's names (identifiers for methods it doesn't know).
 * @param string   $kind  payment or shipping.
 * @return array<int, array{0: string, 1: bool}> [label, whether it is still an identifier].
 */
function pirkoo_method_names( array $names, $kind ) {
	$titles = array();
	if ( function_exists( 'WC' ) ) {
		$registry = 'payment' === $kind ? WC()->payment_gateways() : WC()->shipping();
		$methods  = null === $registry ? array() : ( 'payment' === $kind ? $registry->payment_gateways() : $registry->get_shipping_methods() );
		foreach ( is_array( $methods ) ? $methods : array() as $id => $method ) {
			$title = is_object( $method ) && method_exists( $method, 'get_method_title' ) ? (string) $method->get_method_title() : '';
			if ( '' === $title && is_object( $method ) && isset( $method->method_title ) ) {
				$title = (string) $method->method_title;
			}
			$title = trim( wp_strip_all_tags( $title ) );
			if ( '' !== $title ) {
				$titles[ (string) $id ] = $title;
			}
		}
	}
	$out = array();
	foreach ( $names as $name ) {
		if ( isset( $titles[ $name ] ) ) {
			$out[] = array( $titles[ $name ], false );
		} else {
			// The scanner names the methods it knows ("Flat rate"); anything else arrives as its id.
			$is_id = 1 === preg_match( '/^[a-z0-9_\-:]+$/', $name );
			$out[] = array( $name, $is_id );
		}
	}
	return $out;
}

/**
 * The store profile in detail, below the overview: sales and checkout, products and inventory, and
 * the payment and shipping methods, side by side on wide screens. The store's key facts (country,
 * currency, size, orders a day, checkout and order storage) are in the store summary
 * (pirkoo_render_store_summary()). Without figures, the free sections' texts.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The analysis's free sections.
 * @param array<string, array<string, mixed>>|null                                                 $figures  The scan's figures.
 * @return void
 */
function pirkoo_render_store_widget( array $sections, $figures ) {
	?>
	<section class="pirkoo__panel pirkoo__store-widget" aria-labelledby="pirkoo-store-title">
		<h2 class="pirkoo__h2" id="pirkoo-store-title"><?php esc_html_e( 'Store profile', 'pirkoo' ); ?></h2>
		<?php
		if ( null === $figures ) {
			foreach ( $sections as $section ) {
				if ( in_array( $section['id'], array( 'store_profile', 'commercial', 'catalog', 'operations' ), true ) ) {
					echo '<h3 class="pirkoo__h3">' . esc_html( $section['heading'] ) . '</h3>';
					foreach ( $section['blocks'] as $block ) {
						if ( 'heading' === $block['type'] ) {
							break; // A modelled impact from a scan before 10 Nov 2026: no longer shown.
						}
						pirkoo_render_block( $block );
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
		<div class="pirkoo__profile-groups">
			<div class="pirkoo__profile-group">
				<h3 class="pirkoo__h3"><?php esc_html_e( 'Sales & checkout, last 30 days', 'pirkoo' ); ?></h3>
				<?php
				pirkoo_mini_figures(
					array(
						array( __( 'Paid orders', 'pirkoo' ), pirkoo_count( $o['paid'] ) ),
						array( __( 'Gross sales', 'pirkoo' ), (string) $figures['money']['sales'] ),
						array( __( 'Average order value', 'pirkoo' ), (string) $figures['money']['aov'] ),
						/* translators: %d: percentage. */
						array( __( 'Failed at payment', 'pirkoo' ), null === $failed ? '' : sprintf( __( '%d%%', 'pirkoo' ), $failed ) ),
					)
				);
				pirkoo_stack(
					array(
						array( __( 'Paid', 'pirkoo' ), $o['paid'], 'good' ),
						array( __( 'Failed', 'pirkoo' ), $o['failed'], 'bad' ),
						array( __( 'Pending', 'pirkoo' ), $o['pending'], 'warn' ),
						array( __( 'Cancelled', 'pirkoo' ), $o['cancelled'], 'none' ),
					)
				);
				pirkoo_stack(
					array(
						array( __( 'Returning customers', 'pirkoo' ), $figures['customers']['returning'], 'good' ),
						array( __( 'New customers', 'pirkoo' ), $figures['customers']['new'], 'line' ),
					)
				);
				?>
			</div>
			<div class="pirkoo__profile-group">
				<h3 class="pirkoo__h3"><?php esc_html_e( 'Products & inventory', 'pirkoo' ); ?></h3>
				<?php
				pirkoo_mini_figures(
					array(
						array( __( 'Products', 'pirkoo' ), pirkoo_count( $c['products'] ) ),
						array( __( 'Variations', 'pirkoo' ), pirkoo_count( $c['variations'] ) ),
						array( __( 'Stock management', 'pirkoo' ), pirkoo_on_off( $c['stock_management'] ) ),
					)
				);
				?>
			</div>
			<div class="pirkoo__profile-group">
				<h3 class="pirkoo__h3"><?php esc_html_e( 'Store operations', 'pirkoo' ); ?></h3>
				<?php
				pirkoo_chips( __( 'Payment methods', 'pirkoo' ), pirkoo_method_names( $ops['payment_gateways'], 'payment' ) );
				pirkoo_chips( __( 'Shipping methods', 'pirkoo' ), pirkoo_method_names( $ops['shipping_methods'], 'shipping' ) );
				?>
			</div>
		</div>
	</section>
	<?php
}
