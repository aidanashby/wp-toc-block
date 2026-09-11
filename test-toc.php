<?php
/**
 * Standalone self-check for the pure helpers in wp-toc-block.php
 * (no WordPress environment needed — run with: php test-toc.php).
 */

require_once __DIR__ . '/toc-pure-functions.php';

/**
 * Stand-in for sanitize_title() — lowercase, spaces to hyphens. Good enough
 * for exercising wp_toc_unique_id()'s collision logic without WordPress.
 */
function test_slugify( $text ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $text ) ), '-' );
}

function assert_true( $cond, $label ) {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: $label\n" );
		exit( 1 );
	}
	echo "ok: $label\n";
}

// --- wp_toc_build_tree: flat -> nested by level ---
$flat = array(
	array( 'level' => 2, 'id' => 'a', 'text' => 'A' ),
	array( 'level' => 3, 'id' => 'a1', 'text' => 'A1' ),
	array( 'level' => 2, 'id' => 'b', 'text' => 'B' ),
);
$tree = wp_toc_build_tree( $flat );
assert_true( count( $tree ) === 2, 'two top-level nodes (A, B)' );
assert_true( $tree[0]['id'] === 'a' && count( $tree[0]['children'] ) === 1, 'A has one child' );
assert_true( $tree[0]['children'][0]['id'] === 'a1', 'A child is A1' );
assert_true( empty( $tree[1]['children'] ), 'B has no children' );

// --- level skip: H2 -> H4 nests under H2 ---
$flat_skip = array(
	array( 'level' => 2, 'id' => 'x', 'text' => 'X' ),
	array( 'level' => 4, 'id' => 'x1', 'text' => 'X1' ),
);
$tree_skip = wp_toc_build_tree( $flat_skip );
assert_true( count( $tree_skip[0]['children'] ) === 1 && $tree_skip[0]['children'][0]['id'] === 'x1', 'skipped level nests under last shallower heading' );

// --- wp_toc_unique_id: collision handling ---
$used = array();
$id1  = wp_toc_unique_id( 'Same Title', $used, 'test_slugify' );
$id2  = wp_toc_unique_id( 'Same Title', $used, 'test_slugify' );
$id3  = wp_toc_unique_id( 'Same Title', $used, 'test_slugify' );
assert_true( $id1 === 'same-title', 'first slug is unsuffixed' );
assert_true( $id2 === 'same-title-2', 'second collision gets -2' );
assert_true( $id3 === 'same-title-3', 'third collision gets -3' );

// --- wp_toc_render_tree: escaping ---
$html = wp_toc_render_tree(
	array(
		array( 'id' => 'x"y', 'text' => '<script>alert(1)</script>', 'children' => array() ),
	)
);
assert_true( false === strpos( $html, '<script>alert' ), 'heading text is escaped, not executed' );
assert_true( false !== strpos( $html, 'href="#x&quot;y"' ) || false !== strpos( $html, 'href="#x%22y"' ), 'id is attribute-escaped in href' );

echo "All checks passed.\n";
