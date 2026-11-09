<?php
/**
 * The full analysis (plugin 0.2.0): the report's own sections and texts, as tvarkom's scanner
 * wrote them for this dashboard (latest.analysis in GET /v1/results). Everything the report says
 * except the fix plan, the next steps and the top actions: the summary with the modelled sales
 * impact, the store profile, sales and checkout, catalog, operations and marketing, the technical
 * findings with why each matters, the measurements, the diagnostic details, how the scan works and
 * the developer reference.
 *
 * The texts come from tvarkom in English. They are checked for shape and size here and escaped
 * when shown; no markup from tvarkom is ever output.
 *
 * @package OBDScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A text from the analysis, or "" when it isn't one. Long texts are cut.
 *
 * @param mixed $value The value.
 * @return string
 */
function tvarkom_obd_analysis_text( $value ) {
	return is_string( $value ) ? mb_substr( $value, 0, 2000 ) : '';
}

/**
 * A list of texts from the analysis, at most $max, without empty ones.
 *
 * @param mixed $value The value.
 * @param int   $max   Most items kept.
 * @return string[]
 */
function tvarkom_obd_analysis_texts( $value, $max = 100 ) {
	if ( ! is_array( $value ) ) {
		return array();
	}
	$texts = array_map( 'tvarkom_obd_analysis_text', array_slice( array_values( $value ), 0, $max ) );
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
function tvarkom_obd_parse_analysis( $raw ) {
	if ( ! is_array( $raw ) || ! isset( $raw['schema'] ) || 1 !== $raw['schema'] ) {
		return null;
	}
	$s       = isset( $raw['summary'] ) && is_array( $raw['summary'] ) ? $raw['summary'] : array();
	$summary = array(
		'headline' => tvarkom_obd_analysis_text( isset( $s['headline'] ) ? $s['headline'] : null ),
		'text'     => tvarkom_obd_analysis_text( isset( $s['text'] ) ? $s['text'] : null ),
		'impact'   => tvarkom_obd_analysis_text( isset( $s['impact'] ) ? $s['impact'] : null ),
		'note'     => tvarkom_obd_analysis_text( isset( $s['score_note'] ) ? $s['score_note'] : null ),
	);

	$sections = array();
	foreach ( isset( $raw['sections'] ) && is_array( $raw['sections'] ) ? array_slice( $raw['sections'], 0, 20 ) : array() as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}
		$blocks = array();
		foreach ( isset( $section['blocks'] ) && is_array( $section['blocks'] ) ? array_slice( $section['blocks'], 0, 80 ) : array() as $b ) {
			$block = is_array( $b ) ? tvarkom_obd_parse_block( $b ) : null;
			if ( null !== $block ) {
				$blocks[] = $block;
			}
		}
		$heading = tvarkom_obd_analysis_text( isset( $section['heading'] ) ? $section['heading'] : null );
		if ( '' !== $heading && ! empty( $blocks ) ) {
			$sections[] = array(
				'id'      => sanitize_key( isset( $section['id'] ) && is_string( $section['id'] ) ? $section['id'] : '' ),
				'heading' => $heading,
				'blocks'  => $blocks,
			);
		}
	}
	return array(
		'summary'  => $summary,
		'sections' => $sections,
	);
}

/**
 * One block of a section, checked; null when its type is unknown or it's empty.
 *
 * @param array<string, mixed> $b The block.
 * @return array<string, mixed>|null
 */
function tvarkom_obd_parse_block( array $b ) {
	$type = isset( $b['type'] ) ? $b['type'] : '';
	$text = tvarkom_obd_analysis_text( isset( $b['text'] ) ? $b['text'] : null );
	switch ( $type ) {
		case 'heading':
			return '' === $text ? null : array(
				'type'   => 'heading',
				'text'   => $text,
				'status' => tvarkom_obd_analysis_text( isset( $b['status'] ) ? $b['status'] : null ),
			);
		case 'text':
		case 'note':
			return '' === $text ? null : array(
				'type' => $type,
				'text' => $text,
			);
		case 'list':
			$items = tvarkom_obd_analysis_texts( isset( $b['items'] ) ? $b['items'] : null );
			return empty( $items ) ? null : array(
				'type'  => 'list',
				'items' => $items,
			);
		case 'pairs':
			$pairs = array();
			foreach ( isset( $b['items'] ) && is_array( $b['items'] ) ? array_slice( $b['items'], 0, 50 ) : array() as $item ) {
				if ( is_array( $item ) && isset( $item['term'], $item['text'] ) && is_string( $item['term'] ) && is_string( $item['text'] ) ) {
					$pairs[] = array(
						'term' => tvarkom_obd_analysis_text( $item['term'] ),
						'text' => tvarkom_obd_analysis_text( $item['text'] ),
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
					$rows[] = array_map( 'tvarkom_obd_analysis_text', array_slice( array_values( $row ), 0, 10 ) );
				}
			}
			return array(
				'type'  => 'table',
				'title' => tvarkom_obd_analysis_text( isset( $b['title'] ) ? $b['title'] : null ),
				'note'  => tvarkom_obd_analysis_text( isset( $b['note'] ) ? $b['note'] : null ),
				'head'  => tvarkom_obd_analysis_texts( isset( $b['head'] ) ? $b['head'] : null, 10 ),
				'rows'  => $rows,
				'empty' => tvarkom_obd_analysis_text( isset( $b['empty'] ) ? $b['empty'] : null ),
			);
	}
	return null;
}

/**
 * The analysis's summary, under the score: what the score means and the modelled sales at risk.
 *
 * @param array<string, string> $summary From tvarkom_obd_parse_analysis().
 * @return void
 */
function tvarkom_obd_render_analysis_summary( array $summary ) {
	if ( '' === $summary['text'] && '' === $summary['impact'] ) {
		return;
	}
	?>
	<section class="tobd__panel tobd__summary" aria-labelledby="tobd-summary-title">
		<h2 class="tobd__h2" id="tobd-summary-title"><?php echo esc_html( '' !== $summary['headline'] ? $summary['headline'] : __( 'What your score means', 'on-board-diagnostics-scanner-for-woocommerce' ) ); ?></h2>
		<?php if ( '' !== $summary['text'] ) : ?>
			<p class="tobd__lead"><?php echo esc_html( $summary['text'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $summary['impact'] ) : ?>
			<p class="tobd__impact"><?php echo esc_html( $summary['impact'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $summary['note'] ) : ?>
			<p class="tobd__note"><?php echo esc_html( $summary['note'] ); ?></p>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * The analysis's sections, each in its own panel.
 *
 * @param array<int, array{id: string, heading: string, blocks: array<int, array<string, mixed>>}> $sections From tvarkom_obd_parse_analysis().
 * @return void
 */
function tvarkom_obd_render_analysis_sections( array $sections ) {
	foreach ( $sections as $i => $section ) {
		$id = 'tobd-analysis-' . $i;
		?>
		<section class="tobd__panel tobd__analysis tobd__analysis--<?php echo esc_attr( $section['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>">
			<h2 class="tobd__h2" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $section['heading'] ); ?></h2>
			<?php
			foreach ( $section['blocks'] as $block ) {
				tvarkom_obd_render_block( $block );
			}
			?>
		</section>
		<?php
	}
}

/**
 * One block.
 *
 * @param array<string, mixed> $block From tvarkom_obd_parse_block().
 * @return void
 */
function tvarkom_obd_render_block( array $block ) {
	switch ( $block['type'] ) {
		case 'heading':
			echo '<h3 class="tobd__h3">' . esc_html( $block['text'] );
			if ( '' !== $block['status'] ) {
				echo ' <span class="tobd__status">' . esc_html( $block['status'] ) . '</span>';
			}
			echo '</h3>';
			break;
		case 'text':
			echo '<p class="tobd__text">' . esc_html( $block['text'] ) . '</p>';
			break;
		case 'note':
			echo '<p class="tobd__note">' . esc_html( $block['text'] ) . '</p>';
			break;
		case 'list':
			echo '<ul class="tobd__list">';
			foreach ( $block['items'] as $item ) {
				echo '<li>' . esc_html( $item ) . '</li>';
			}
			echo '</ul>';
			break;
		case 'pairs':
			echo '<dl class="tobd__pairs">';
			foreach ( $block['items'] as $item ) {
				echo '<dt>' . esc_html( $item['term'] ) . '</dt><dd>' . esc_html( $item['text'] ) . '</dd>';
			}
			echo '</dl>';
			break;
		case 'table':
			tvarkom_obd_render_table( $block );
			break;
	}
}

/**
 * A table block: its title, note, and rows (or its empty text), scrollable on small screens.
 *
 * @param array<string, mixed> $block A table block.
 * @return void
 */
function tvarkom_obd_render_table( array $block ) {
	if ( '' !== $block['title'] ) {
		echo '<h3 class="tobd__h3">' . esc_html( $block['title'] ) . '</h3>';
	}
	if ( '' !== $block['note'] ) {
		echo '<p class="tobd__note">' . esc_html( $block['note'] ) . '</p>';
	}
	if ( empty( $block['rows'] ) ) {
		if ( '' !== $block['empty'] ) {
			echo '<p class="tobd__text">' . esc_html( $block['empty'] ) . '</p>';
		}
		return;
	}
	echo '<div class="tobd__table-wrap"><table class="tobd__table">';
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
