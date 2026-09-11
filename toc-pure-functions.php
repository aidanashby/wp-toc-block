<?php
/**
 * Pure logic for wp-toc-block.php — no WordPress functions, so this file
 * (and test-toc.php, which exercises it) can run standalone: php test-toc.php
 *
 * Escaping falls back to htmlspecialchars() when WordPress isn't loaded,
 * so wp_toc_render_tree() is still safe to test in isolation.
 */

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

/**
 * Turn a flat, document-order list of headings into a nested tree.
 *
 * @param array $flat Each item: ['level' => int, 'id' => string, 'text' => string].
 * @return array Each node: ['id' => string, 'text' => string, 'children' => array].
 */
function wp_toc_build_tree( array $flat ) {
	$root  = array();
	$stack = array(); // stack of ['level' => int, 'ref' => &children array]

	foreach ( $flat as $item ) {
		$node = array(
			'id'       => $item['id'],
			'text'     => $item['text'],
			'children' => array(),
		);

		while ( ! empty( $stack ) && $stack[ count( $stack ) - 1 ]['level'] >= $item['level'] ) {
			array_pop( $stack );
		}

		if ( empty( $stack ) ) {
			$root[]  = $node;
			$last_key = count( $root ) - 1;
			$stack[] = array(
				'level' => $item['level'],
				'ref'   => &$root[ $last_key ]['children'],
			);
		} else {
			$parent_ref   =& $stack[ count( $stack ) - 1 ]['ref'];
			$parent_ref[] = $node;
			$last_key     = count( $parent_ref ) - 1;
			$stack[]      = array(
				'level' => $item['level'],
				'ref'   => &$parent_ref[ $last_key ]['children'],
			);
		}
	}

	return $root;
}

/**
 * Turn a heading's text into a unique slug, given the IDs already used on
 * this page (existing manual anchors included).
 *
 * @param string   $text     Heading text.
 * @param array    $used_ids IDs already assigned, keyed by id for fast lookup.
 * @param callable $slugify  sanitize_title() in production; a plain stub in tests.
 * @return string
 */
function wp_toc_unique_id( $text, array &$used_ids, $slugify ) {
	$base = call_user_func( $slugify, $text );
	if ( '' === $base ) {
		$base = 'heading';
	}
	$id = $base;
	$n  = 2;
	while ( isset( $used_ids[ $id ] ) ) {
		$id = $base . '-' . $n;
		$n++;
	}
	$used_ids[ $id ] = true;
	return $id;
}

/**
 * Inject id="..." into heading opening tags that don't already have one,
 * leaving everything else in $content byte-identical — no DOM reserialize,
 * which is known to subtly rewrite builder markup (inline SVGs, self-closing
 * quirks) it never needed to touch.
 *
 * $flat_all must have one entry per <h{level}> opening tag in $content, in
 * the same source order — including entries for empty headings (skip=true)
 * — so the nth regex match here lines up with $flat_all[n]. A caller that
 * filters empty headings out before calling this will misalign every
 * heading after the first gap.
 *
 * @param string $content
 * @param array  $levels   Heading levels included, e.g. [2, 3].
 * @param array  $flat_all Each item: ['id' => ?string, 'has_id' => bool, 'skip' => bool].
 * @return string
 */
function wp_toc_inject_ids( $content, array $levels, array $flat_all ) {
	$pattern = '/<h([' . implode( '', $levels ) . '])\b([^>]*)>/i';
	$index   = 0;

	return preg_replace_callback(
		$pattern,
		function ( $matches ) use ( &$index, $flat_all ) {
			$item = isset( $flat_all[ $index ] ) ? $flat_all[ $index ] : null;
			$index++;

			if ( ! $item || $item['skip'] || $item['has_id'] ) {
				return $matches[0];
			}

			return '<h' . $matches[1] . ' id="' . esc_attr( $item['id'] ) . '"' . $matches[2] . '>';
		},
		$content
	);
}

/**
 * Render a tree (from wp_toc_build_tree) as a nested <ul>.
 *
 * @param array $tree
 * @return string
 */
function wp_toc_render_tree( array $tree ) {
	if ( empty( $tree ) ) {
		return '';
	}
	$html = '<ul>';
	foreach ( $tree as $node ) {
		$html .= '<li><a href="#' . esc_attr( $node['id'] ) . '">' . esc_html( $node['text'] ) . '</a>';
		$html .= wp_toc_render_tree( $node['children'] );
		$html .= '</li>';
	}
	$html .= '</ul>';
	return $html;
}
