<?php
/**
 * The store profile (sales and checkout, products and inventory, store operations; founder
 * decisions of 9 and 10 Nov 2026), drawn from the scan's figures (tvarkom_obd_parse_figures())
 * with CSS: only what the plugin counted, never a modelled money figure (removed on 10 Nov 2026).
 * Every value is escaped. Without figures (scans stored before them), the sections' own texts are
 * shown instead.
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
		// A value longer than a tile (a sales range) gets two tiles' width instead of wrapping.
		$wide = mb_strlen( $figure[1] ) > 12 ? ' class="tobd__mini--wide"' : '';
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
 * Labels in a row (payment and shipping methods). An item still in its identifier form (a method
 * neither tvarkom nor this store has a name for) is shown as code, with a note saying so.
 *
 * @param string                                $title The row's title.
 * @param array<int, array{0: string, 1: bool}> $items [label, whether it is an identifier].
 * @return void
 */
function tvarkom_obd_chips( $title, array $items ) {
	if ( empty( $items ) ) {
		return;
	}
	echo '<p class="tobd__chips-title">' . esc_html( $title ) . '</p><ul class="tobd__chips">';
	$ids = false;
	foreach ( $items as $item ) {
		if ( $item[1] ) {
			$ids = true;
			echo '<li class="tobd__chip--id"><code>' . esc_html( $item[0] ) . '</code></li>';
		} else {
			echo '<li>' . esc_html( $item[0] ) . '</li>';
		}
	}
	echo '</ul>';
	if ( $ids ) {
		echo '<p class="tobd__note">' . esc_html__( 'Shown in code: the identifier WooCommerce gives a method this store no longer has a name for (for example, a plugin that was switched off).', 'tvarkom-helper' ) . '</p>';
	}
}

/**
 * The payment gateways' and shipping methods' names: the scan's names, with any identifier the scan
 * couldn't name replaced by this store's own title for it (read here, never sent to tvarkom).
 *
 * @param string[] $names The scan's names (identifiers for methods it doesn't know).
 * @param string   $kind  payment or shipping.
 * @return array<int, array{0: string, 1: bool}> [label, whether it is still an identifier].
 */
function tvarkom_obd_method_names( array $names, $kind ) {
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
 * (tvarkom_obd_render_store_summary()). Without figures, the free sections' texts.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The analysis's free sections.
 * @param array<string, array<string, mixed>>|null                                                 $figures  The scan's figures.
 * @return void
 */
function tvarkom_obd_render_store_widget( array $sections, $figures ) {
	?>
	<section class="tobd__panel tobd__store-widget" aria-labelledby="tobd-store-title">
		<h2 class="tobd__h2" id="tobd-store-title"><?php esc_html_e( 'Store profile', 'tvarkom-helper' ); ?></h2>
		<?php
		if ( null === $figures ) {
			foreach ( $sections as $section ) {
				if ( in_array( $section['id'], array( 'store_profile', 'commercial', 'catalog', 'operations' ), true ) ) {
					echo '<h3 class="tobd__h3">' . esc_html( $section['heading'] ) . '</h3>';
					foreach ( $section['blocks'] as $block ) {
						if ( 'heading' === $block['type'] ) {
							break; // A modelled impact from a scan before 10 Nov 2026: no longer shown.
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
				<h3 class="tobd__h3"><?php esc_html_e( 'Sales & checkout, last 30 days', 'tvarkom-helper' ); ?></h3>
				<?php
				tvarkom_obd_mini_figures(
					array(
						array( __( 'Paid orders', 'tvarkom-helper' ), tvarkom_obd_count( $o['paid'] ) ),
						array( __( 'Gross sales', 'tvarkom-helper' ), (string) $figures['money']['sales'] ),
						array( __( 'Average order value', 'tvarkom-helper' ), (string) $figures['money']['aov'] ),
						/* translators: %d: percentage. */
						array( __( 'Failed at payment', 'tvarkom-helper' ), null === $failed ? '' : sprintf( __( '%d%%', 'tvarkom-helper' ), $failed ) ),
					)
				);
				tvarkom_obd_stack(
					array(
						array( __( 'Paid', 'tvarkom-helper' ), $o['paid'], 'good' ),
						array( __( 'Failed', 'tvarkom-helper' ), $o['failed'], 'bad' ),
						array( __( 'Pending', 'tvarkom-helper' ), $o['pending'], 'warn' ),
						array( __( 'Cancelled', 'tvarkom-helper' ), $o['cancelled'], 'none' ),
					)
				);
				tvarkom_obd_stack(
					array(
						array( __( 'Returning customers', 'tvarkom-helper' ), $figures['customers']['returning'], 'good' ),
						array( __( 'New customers', 'tvarkom-helper' ), $figures['customers']['new'], 'line' ),
					)
				);
				?>
			</div>
			<div class="tobd__profile-group">
				<h3 class="tobd__h3"><?php esc_html_e( 'Products & inventory', 'tvarkom-helper' ); ?></h3>
				<?php
				tvarkom_obd_mini_figures(
					array(
						array( __( 'Products', 'tvarkom-helper' ), tvarkom_obd_count( $c['products'] ) ),
						array( __( 'Variations', 'tvarkom-helper' ), tvarkom_obd_count( $c['variations'] ) ),
						array( __( 'Stock management', 'tvarkom-helper' ), tvarkom_obd_on_off( $c['stock_management'] ) ),
					)
				);
				?>
			</div>
			<div class="tobd__profile-group">
				<h3 class="tobd__h3"><?php esc_html_e( 'Store operations', 'tvarkom-helper' ); ?></h3>
				<?php
				tvarkom_obd_chips( __( 'Payment methods', 'tvarkom-helper' ), tvarkom_obd_method_names( $ops['payment_gateways'], 'payment' ) );
				tvarkom_obd_chips( __( 'Shipping methods', 'tvarkom-helper' ), tvarkom_obd_method_names( $ops['shipping_methods'], 'shipping' ) );
				?>
			</div>
		</div>
	</section>
	<?php
}
