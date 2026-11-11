<?php
/**
 * The analysis (plugin 0.2.0), as Pirkoo's scanner wrote it for this dashboard (latest.analysis in
 * GET /v1/results): the summary, how the score was made, and the store profile, sales and
 * checkout, catalog, operations and marketing sections, the first four drawn as charts from the
 * scan's figures. The modelled sales impact was removed on 10 Nov 2026 (founder decision): scans
 * stored before then may still carry it, and this plugin ignores it. The technical findings, diagnostic details, how the scan works and the developer
 * reference are for a future paid version (founder decision of 9 Nov 2026): Pirkoo doesn't send
 * them, and this plugin skips them if it ever gets them.
 *
 * The texts come from Pirkoo in English. They are checked for shape and size here and escaped
 * when shown; no markup from Pirkoo is ever output.
 *
 * @package Pirkoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The analysis's sections for a future paid version, never shown by this plugin.
 */
const PIRKOO_PAID_SECTIONS = array( 'technical', 'detailed', 'how_this_scan_works', 'developer' );

/**
 * A text from the analysis, or "" when it isn't one. Long texts are cut.
 *
 * @param mixed $value The value.
 * @return string
 */
function pirkoo_analysis_text( $value ) {
	return is_string( $value ) ? mb_substr( $value, 0, 2000 ) : '';
}

/**
 * A list of texts from the analysis, at most $max, without empty ones.
 *
 * @param mixed $value The value.
 * @param int   $max   Most items kept.
 * @return string[]
 */
function pirkoo_analysis_texts( $value, $max = 100 ) {
	if ( ! is_array( $value ) ) {
		return array();
	}
	$texts = array_map( 'pirkoo_analysis_text', array_slice( array_values( $value ), 0, $max ) );
	return array_values(
		array_filter(
			$texts,
			function ( $text ) {
				return '' !== $text;
			}
		)
	);
}

/**
 * Checks the analysis from GET /v1/results: known block types only, texts only, bounded sizes.
 *
 * @param mixed $raw The decoded latest.analysis.
 * @return array{summary: array<string, mixed>, sections: array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}>}|null
 */
function pirkoo_parse_analysis( $raw ) {
	if ( ! is_array( $raw ) || ! isset( $raw['schema'] ) || 1 !== $raw['schema'] ) {
		return null;
	}
	$s       = isset( $raw['summary'] ) && is_array( $raw['summary'] ) ? $raw['summary'] : array();
	$summary = array(
		'headline' => pirkoo_analysis_text( isset( $s['headline'] ) ? $s['headline'] : null ),
		'text'     => pirkoo_analysis_text( isset( $s['text'] ) ? $s['text'] : null ),
		'note'     => pirkoo_analysis_text( isset( $s['score_note'] ) ? $s['score_note'] : null ),
	);

	$sections = array();
	foreach ( isset( $raw['sections'] ) && is_array( $raw['sections'] ) ? array_slice( $raw['sections'], 0, 20 ) : array() as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}
		$blocks = array();
		foreach ( isset( $section['blocks'] ) && is_array( $section['blocks'] ) ? array_slice( $section['blocks'], 0, 80 ) : array() as $b ) {
			$block = is_array( $b ) ? pirkoo_parse_block( $b ) : null;
			if ( null !== $block ) {
				$blocks[] = $block;
			}
		}
		$heading = pirkoo_analysis_text( isset( $section['heading'] ) ? $section['heading'] : null );
		$id      = sanitize_key( isset( $section['id'] ) && is_string( $section['id'] ) ? $section['id'] : '' );
		if ( in_array( $id, PIRKOO_PAID_SECTIONS, true ) ) {
			continue;
		}
		if ( '' !== $heading && ! empty( $blocks ) ) {
			if ( isset( $section['tier'] ) && 'paid' === $section['tier'] ) {
				continue;
			}
			$sections[] = array(
				'id'      => $id,
				'heading' => $heading,
				'blocks'  => $blocks,
			);
		}
	}
	return array(
		'summary'  => $summary,
		'sections' => $sections,
		'figures'  => pirkoo_parse_figures( isset( $raw['figures'] ) ? $raw['figures'] : null ),
		'scoring'  => pirkoo_parse_scoring( isset( $raw['scoring'] ) ? $raw['scoring'] : null ),
		'pipeline' => pirkoo_parse_pipeline( isset( $raw['pipeline'] ) ? $raw['pipeline'] : null ),
	);
}

/**
 * The scan's figures behind the charts, checked: counts as non-negative integers, texts as texts.
 *
 * @param mixed $raw The analysis's figures.
 * @return array<string, array<string, mixed>>|null
 */
function pirkoo_parse_figures( $raw ) {
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$group      = function ( $key ) use ( $raw ) {
		return isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
	};
	$count      = function ( array $g, $key ) {
		return isset( $g[ $key ] ) && is_int( $g[ $key ] ) && $g[ $key ] >= 0 ? $g[ $key ] : null;
	};
	$text       = function ( array $g, $key ) {
		$v = isset( $g[ $key ] ) ? pirkoo_analysis_text( $g[ $key ] ) : '';
		return '' === $v ? null : $v;
	};
	$flag       = function ( array $g, $key ) {
		return isset( $g[ $key ] ) && is_bool( $g[ $key ] ) ? $g[ $key ] : null;
	};
	$profile    = $group( 'profile' );
	$orders     = $group( 'orders' );
	$money      = $group( 'money' );
	$customers  = $group( 'customers' );
	$catalog    = $group( 'catalog' );
	$operations = $group( 'operations' );
	return array(
		'profile'    => array(
			'country'        => $text( $profile, 'country' ),
			'currency'       => $text( $profile, 'currency' ),
			'size'           => $text( $profile, 'size' ),
			'orders_per_day' => isset( $profile['orders_per_day'] ) && is_numeric( $profile['orders_per_day'] ) && $profile['orders_per_day'] >= 0 ? (float) $profile['orders_per_day'] : null,
		),
		'orders'     => array(
			'paid'      => $count( $orders, 'paid' ),
			'failed'    => $count( $orders, 'failed' ),
			'pending'   => $count( $orders, 'pending' ),
			'cancelled' => $count( $orders, 'cancelled' ),
		),
		'money'      => array(
			'sales' => $text( $money, 'sales' ),
			'aov'   => $text( $money, 'aov' ),
		),
		'customers'  => array(
			'new'       => $count( $customers, 'new' ),
			'returning' => $count( $customers, 'returning' ),
		),
		'catalog'    => array(
			'products'         => $count( $catalog, 'products' ),
			'variations'       => $count( $catalog, 'variations' ),
			'stock_management' => $flag( $catalog, 'stock_management' ),
		),
		'operations' => array(
			'payment_gateways' => pirkoo_analysis_texts( isset( $operations['payment_gateways'] ) ? $operations['payment_gateways'] : null, 30 ),
			'shipping_methods' => pirkoo_analysis_texts( isset( $operations['shipping_methods'] ) ? $operations['shipping_methods'] : null, 30 ),
			'checkout_type'    => isset( $operations['checkout_type'] ) && in_array( $operations['checkout_type'], array( 'classic', 'block' ), true ) ? $operations['checkout_type'] : null,
			'hpos'             => $flag( $operations, 'hpos' ),
		),
	);
}

/**
 * How the score was made (score model 2, 10 Nov 2026): its model and how many of the scanner's
 * checks ran and have a result. Null for scans before it or when malformed.
 *
 * @param mixed $raw The analysis's scoring.
 * @return array{model: int, total: int, performed: int, measured: int, unavailable: int, not_applicable: int, missing: string[]}|null
 */
function pirkoo_parse_scoring( $raw ) {
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$out = array();
	foreach ( array( 'model', 'total', 'performed', 'measured', 'unavailable', 'not_applicable' ) as $key ) {
		if ( ! isset( $raw[ $key ] ) || ! is_int( $raw[ $key ] ) || $raw[ $key ] < 0 || $raw[ $key ] > 1000 ) {
			return null;
		}
		$out[ $key ] = $raw[ $key ];
	}
	if ( $out['model'] < 1 || $out['measured'] > $out['performed'] || $out['performed'] > $out['total'] ) {
		return null;
	}
	$out['missing'] = array_map( 'sanitize_key', array_filter( isset( $raw['missing_tier1'] ) && is_array( $raw['missing_tier1'] ) ? array_slice( $raw['missing_tier1'], 0, 10 ) : array(), 'is_string' ) );
	return $out;
}

/**
 * One block of a section, checked; null when its type is unknown or it's empty.
 *
 * @param array<string, mixed> $b The block.
 * @return array<string, mixed>|null
 */
function pirkoo_parse_block( array $b ) {
	$type = isset( $b['type'] ) ? $b['type'] : '';
	$text = pirkoo_analysis_text( isset( $b['text'] ) ? $b['text'] : null );
	switch ( $type ) {
		case 'heading':
			return '' === $text ? null : array(
				'type'   => 'heading',
				'text'   => $text,
				'status' => pirkoo_analysis_text( isset( $b['status'] ) ? $b['status'] : null ),
			);
		case 'text':
		case 'note':
			return '' === $text ? null : array(
				'type' => $type,
				'text' => $text,
			);
		case 'list':
			$items = pirkoo_analysis_texts( isset( $b['items'] ) ? $b['items'] : null );
			return empty( $items ) ? null : array(
				'type'  => 'list',
				'items' => $items,
			);
		case 'pairs':
			$pairs = array();
			foreach ( isset( $b['items'] ) && is_array( $b['items'] ) ? array_slice( $b['items'], 0, 50 ) : array() as $item ) {
				if ( is_array( $item ) && isset( $item['term'], $item['text'] ) && is_string( $item['term'] ) && is_string( $item['text'] ) ) {
					$pairs[] = array(
						'term' => pirkoo_analysis_text( $item['term'] ),
						'text' => pirkoo_analysis_text( $item['text'] ),
					);
				}
			}
			return empty( $pairs ) ? null : array(
				'type'  => 'pairs',
				'items' => $pairs,
			);
		case 'table':
			$rows = array();
			foreach ( isset( $b['rows'] ) && is_array( $b['rows'] ) ? array_slice( $b['rows'], 0, 200 ) : array() as $row ) {
				if ( is_array( $row ) ) {
					$rows[] = array_map( 'pirkoo_analysis_text', array_slice( array_values( $row ), 0, 10 ) );
				}
			}
			return array(
				'type'  => 'table',
				'title' => pirkoo_analysis_text( isset( $b['title'] ) ? $b['title'] : null ),
				'note'  => pirkoo_analysis_text( isset( $b['note'] ) ? $b['note'] : null ),
				'head'  => pirkoo_analysis_texts( isset( $b['head'] ) ? $b['head'] : null, 10 ),
				'rows'  => $rows,
				'empty' => pirkoo_analysis_text( isset( $b['empty'] ) ? $b['empty'] : null ),
			);
	}
	return null;
}

/**
 * Sections shown as their texts, each in its own panel (traffic and marketing).
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections The sections.
 * @return void
 */
function pirkoo_render_analysis_sections( array $sections ) {
	foreach ( $sections as $i => $section ) {
		$id = 'pirkoo-analysis-' . $i;
		?>
		<section class="pirkoo__panel pirkoo__analysis pirkoo__analysis--<?php echo esc_attr( $section['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>">
			<h2 class="pirkoo__h2" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section['heading'] ); ?></h2>
			<?php
			foreach ( $section['blocks'] as $block ) {
				pirkoo_render_block( $block );
			}
			?>
		</section>
		<?php
	}
}

/**
 * One block.
 *
 * @param array<string, mixed> $block From pirkoo_parse_block().
 * @return void
 */
function pirkoo_render_block( array $block ) {
	switch ( $block['type'] ) {
		case 'heading':
			echo '<h3 class="pirkoo__h3">' . esc_html( $block['text'] );
			if ( '' !== $block['status'] ) {
				echo ' <span class="pirkoo__status">' . esc_html( $block['status'] ) . '</span>';
			}
			echo '</h3>';
			break;
		case 'text':
			echo '<p class="pirkoo__text">' . esc_html( $block['text'] ) . '</p>';
			break;
		case 'note':
			echo '<p class="pirkoo__note">' . esc_html( $block['text'] ) . '</p>';
			break;
		case 'list':
			echo '<ul class="pirkoo__list">';
			foreach ( $block['items'] as $item ) {
				echo '<li>' . esc_html( $item ) . '</li>';
			}
			echo '</ul>';
			break;
		case 'pairs':
			echo '<dl class="pirkoo__pairs">';
			foreach ( $block['items'] as $item ) {
				echo '<dt>' . esc_html( $item['term'] ) . '</dt><dd>' . esc_html( $item['text'] ) . '</dd>';
			}
			echo '</dl>';
			break;
		case 'table':
			pirkoo_render_table( $block );
			break;
	}
}

/**
 * A table block: its title, note, and rows (or its empty text), scrollable on small screens.
 *
 * @param array<string, mixed> $block A table block.
 * @return void
 */
function pirkoo_render_table( array $block ) {
	if ( '' !== $block['title'] ) {
		echo '<h3 class="pirkoo__h3">' . esc_html( $block['title'] ) . '</h3>';
	}
	if ( '' !== $block['note'] ) {
		echo '<p class="pirkoo__note">' . esc_html( $block['note'] ) . '</p>';
	}
	if ( empty( $block['rows'] ) ) {
		if ( '' !== $block['empty'] ) {
			echo '<p class="pirkoo__text">' . esc_html( $block['empty'] ) . '</p>';
		}
		return;
	}
	echo '<div class="pirkoo__table-wrap"><table class="pirkoo__table">';
	if ( ! empty( $block['head'] ) ) {
		echo '<thead><tr>';
		foreach ( $block['head'] as $cell ) {
			echo '<th scope="col">' . esc_html( $cell ) . '</th>';
		}
		echo '</tr></thead>';
	}
	echo '<tbody>';
	foreach ( $block['rows'] as $row ) {
		echo '<tr>';
		foreach ( $row as $n => $cell ) {
			// A table without a header is a list of label and value (the store profile).
			echo empty( $block['head'] ) && 0 === $n ? '<th scope="row">' . esc_html( $cell ) . '</th>' : '<td>' . esc_html( $cell ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}
