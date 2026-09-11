<?php
/**
 * Plugin Name:       WP TOC Block
 * Description:       [toc] shortcode that builds a table of contents from the headings in
 *                     the current post or page. No auto-insert, no block editor block.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Aidan Ashby
 * License:           MIT
 * Text Domain:       wp-toc-block
 * Update URI:        https://github.com/aidanashby/wp-toc-block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_TOC_VERSION', '0.1.0' );
define( 'WP_TOC_FILE', __FILE__ );
define( 'WP_TOC_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_TOC_OPTION', 'wp_toc_settings' );
define( 'WP_TOC_PLACEHOLDER', '<!--WP_TOC_PLACEHOLDER-->' );

/**
 * GitHub-based update checker. Harmless before any tagged release exists.
 */
add_action( 'plugins_loaded', 'wp_toc_init_updater' );
function wp_toc_init_updater() {
	$loader = __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $loader ) ) {
		return;
	}
	require_once $loader;

	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/aidanashby/wp-toc-block/',
		WP_TOC_FILE,
		'wp-toc-block'
	);

	$api = $checker->getVcsApi();
	if ( method_exists( $api, 'enableReleaseAssets' ) ) {
		$api->enableReleaseAssets();
	}
}

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Defaults. Also the shape of the stored option.
 *
 * @return array
 */
function wp_toc_defaults() {
	return array(
		'min_headings'    => 2,
		'min_words'       => 0,
		'show_label'      => true,
		'label_text'      => __( 'Table of Contents', 'wp-toc-block' ),
		'toggle_view'     => false,
		'initially_hidden' => true,
		'heading_levels'  => array( 2, 3 ),
		'scale_ratio'     => 0.9,
		'indent_px'       => 16,
		'max_width'       => 250,
		'alignment'       => 'none',
		'color_bg'        => '#f7f7f7',
		'color_text'      => '#1e1e1e',
		'color_link'      => '#1e1e1e',
		'color_link_hover' => '#0073aa',
		'color_border'    => '#dddddd',
	);
}

/**
 * Stored settings merged over defaults.
 *
 * @return array
 */
function wp_toc_get_settings() {
	$saved = get_option( WP_TOC_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, wp_toc_defaults() );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wp_toc_action_links' );
function wp_toc_action_links( $links ) {
	$settings = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=wp-toc-block' ) ),
		esc_html__( 'Settings', 'wp-toc-block' )
	);
	array_unshift( $links, $settings );
	return $links;
}

add_action( 'admin_menu', 'wp_toc_add_settings_page' );
function wp_toc_add_settings_page() {
	add_options_page(
		__( 'WP TOC Block', 'wp-toc-block' ),
		__( 'WP TOC Block', 'wp-toc-block' ),
		'manage_options',
		'wp-toc-block',
		'wp_toc_render_settings_page'
	);
}

add_action( 'admin_init', 'wp_toc_register_settings' );
function wp_toc_register_settings() {
	register_setting(
		'wp_toc_block',
		WP_TOC_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wp_toc_sanitize_settings',
			'default'           => wp_toc_defaults(),
		)
	);
}

/**
 * Sanitise the whole settings array on save.
 *
 * @param mixed $input Raw submitted value.
 * @return array
 */
function wp_toc_sanitize_settings( $input ) {
	$defaults = wp_toc_defaults();
	$out      = wp_toc_get_settings();

	if ( ! is_array( $input ) ) {
		return $out;
	}

	if ( isset( $input['min_headings'] ) ) {
		$out['min_headings'] = max( 1, absint( $input['min_headings'] ) );
	}
	if ( isset( $input['min_words'] ) ) {
		$out['min_words'] = absint( $input['min_words'] );
	}

	$out['show_label']       = ! empty( $input['show_label'] );
	$out['label_text']       = isset( $input['label_text'] ) ? sanitize_text_field( $input['label_text'] ) : $defaults['label_text'];
	$out['toggle_view']      = ! empty( $input['toggle_view'] );
	$out['initially_hidden'] = ! empty( $input['initially_hidden'] );

	$levels = array();
	if ( isset( $input['heading_levels'] ) && is_array( $input['heading_levels'] ) ) {
		foreach ( $input['heading_levels'] as $level ) {
			$level = absint( $level );
			if ( $level >= 2 && $level <= 6 ) {
				$levels[] = $level;
			}
		}
	}
	sort( $levels );
	$out['heading_levels'] = ! empty( $levels ) ? $levels : $defaults['heading_levels'];

	if ( isset( $input['scale_ratio'] ) ) {
		$ratio              = (float) $input['scale_ratio'];
		$out['scale_ratio'] = ( $ratio > 0 && $ratio <= 1 ) ? $ratio : $defaults['scale_ratio'];
	}
	if ( isset( $input['indent_px'] ) ) {
		$out['indent_px'] = absint( $input['indent_px'] );
	}

	if ( isset( $input['max_width'] ) ) {
		$max_width         = absint( $input['max_width'] );
		$out['max_width']  = $max_width > 0 ? $max_width : $defaults['max_width'];
	}

	if ( isset( $input['alignment'] ) ) {
		$out['alignment'] = in_array( $input['alignment'], array( 'none', 'left', 'right', 'center' ), true )
			? $input['alignment']
			: $defaults['alignment'];
	}

	foreach ( array( 'color_bg', 'color_text', 'color_link', 'color_link_hover', 'color_border' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$color        = sanitize_hex_color( $input[ $key ] );
			$out[ $key ]  = $color ? $color : $defaults[ $key ];
		}
	}

	return $out;
}

add_action( 'admin_enqueue_scripts', 'wp_toc_admin_assets' );
function wp_toc_admin_assets( $hook ) {
	if ( 'settings_page_wp-toc-block' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		'jQuery(function($){ $(".wp-toc-color").wpColorPicker(); });'
	);
}

function wp_toc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s   = wp_toc_get_settings();
	$opt = esc_attr( WP_TOC_OPTION );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WP TOC Block', 'wp-toc-block' ); ?></h1>
		<p><?php esc_html_e( 'Place [toc] anywhere in a post or page\'s content to show a table of contents built from its headings.', 'wp-toc-block' ); ?></p>

		<form action="options.php" method="post">
			<?php settings_fields( 'wp_toc_block' ); ?>

			<h2><?php esc_html_e( 'When it appears', 'wp-toc-block' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="toc-min-headings"><?php esc_html_e( 'Minimum headings to display', 'wp-toc-block' ); ?></label></th>
					<td><input name="<?php echo $opt; ?>[min_headings]" id="toc-min-headings" type="number" min="1" step="1" value="<?php echo esc_attr( $s['min_headings'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-min-words"><?php esc_html_e( 'Minimum word count', 'wp-toc-block' ); ?></label></th>
					<td>
						<input name="<?php echo $opt; ?>[min_words]" id="toc-min-words" type="number" min="0" step="1" value="<?php echo esc_attr( $s['min_words'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( '0 = unlimited (no minimum).', 'wp-toc-block' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Header label', 'wp-toc-block' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show label', 'wp-toc-block' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo $opt; ?>[show_label]" value="1" <?php checked( $s['show_label'] ); ?>> <?php esc_html_e( 'Show the header label', 'wp-toc-block' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-label-text"><?php esc_html_e( 'Label text', 'wp-toc-block' ); ?></label></th>
					<td><input name="<?php echo $opt; ?>[label_text]" id="toc-label-text" type="text" value="<?php echo esc_attr( $s['label_text'] ); ?>" class="regular-text"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Toggle view', 'wp-toc-block' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Toggle view', 'wp-toc-block' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo $opt; ?>[toggle_view]" value="1" <?php checked( $s['toggle_view'] ); ?>> <?php esc_html_e( 'Let visitors show/hide the table of contents', 'wp-toc-block' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Initial state', 'wp-toc-block' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo $opt; ?>[initially_hidden]" value="1" <?php checked( $s['initially_hidden'] ); ?>> <?php esc_html_e( 'Start hidden', 'wp-toc-block' ); ?></label></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Contents list', 'wp-toc-block' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Heading levels included', 'wp-toc-block' ); ?></th>
					<td>
						<?php for ( $level = 2; $level <= 6; $level++ ) : ?>
							<label>
								<input type="checkbox" name="<?php echo $opt; ?>[heading_levels][]" value="<?php echo esc_attr( $level ); ?>" <?php checked( in_array( $level, $s['heading_levels'], true ) ); ?>>
								<?php echo esc_html( 'H' . $level ); ?>
							</label>
						<?php endfor; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-scale"><?php esc_html_e( 'Heading size scale ratio', 'wp-toc-block' ); ?></label></th>
					<td>
						<input name="<?php echo $opt; ?>[scale_ratio]" id="toc-scale" type="number" min="0.1" max="1" step="0.05" value="<?php echo esc_attr( $s['scale_ratio'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Each nested level multiplies text size by this ratio, e.g. 0.9.', 'wp-toc-block' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-indent"><?php esc_html_e( 'Indent per level (px)', 'wp-toc-block' ); ?></label></th>
					<td><input name="<?php echo $opt; ?>[indent_px]" id="toc-indent" type="number" min="0" step="1" value="<?php echo esc_attr( $s['indent_px'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-max-width"><?php esc_html_e( 'Maximum width (px)', 'wp-toc-block' ); ?></label></th>
					<td>
						<input name="<?php echo $opt; ?>[max_width]" id="toc-max-width" type="number" min="1" step="1" value="<?php echo esc_attr( $s['max_width'] ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Width when expanded. When collapsed (toggle view), it shrinks to fit just the label and toggle icon.', 'wp-toc-block' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-alignment"><?php esc_html_e( 'Alignment', 'wp-toc-block' ); ?></label></th>
					<td>
						<select name="<?php echo $opt; ?>[alignment]" id="toc-alignment">
							<?php
							$alignments = array(
								'none'   => __( 'None (full width, in the flow of the content)', 'wp-toc-block' ),
								'left'   => __( 'Left (floats, text wraps around it)', 'wp-toc-block' ),
								'right'  => __( 'Right (floats, text wraps around it)', 'wp-toc-block' ),
								'center' => __( 'Centre', 'wp-toc-block' ),
							);
							foreach ( $alignments as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['alignment'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Colours', 'wp-toc-block' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$colors = array(
					'color_bg'         => __( 'Background', 'wp-toc-block' ),
					'color_text'       => __( 'Text', 'wp-toc-block' ),
					'color_link'       => __( 'Link', 'wp-toc-block' ),
					'color_link_hover' => __( 'Link hover', 'wp-toc-block' ),
					'color_border'     => __( 'Border', 'wp-toc-block' ),
				);
				foreach ( $colors as $key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="toc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input name="<?php echo $opt; ?>[<?php echo esc_attr( $key ); ?>]" id="toc-<?php echo esc_attr( $key ); ?>" type="text" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="wp-toc-color" data-default-color="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Shortcode: drop a placeholder. The real work happens in the
 * the_content filter below, once per page, after everything else
 * (including Divi's own rendering) has already run.
 * ---------------------------------------------------------------------- */

add_shortcode( 'toc', 'wp_toc_shortcode' );
function wp_toc_shortcode( $atts ) {
	return WP_TOC_PLACEHOLDER;
}

/* -------------------------------------------------------------------------
 * Pure helpers (tree building, ID dedup, list rendering) live in
 * toc-pure-functions.php, with no WordPress dependency, so test-toc.php
 * can exercise them standalone: php test-toc.php
 * ---------------------------------------------------------------------- */

require_once __DIR__ . '/toc-pure-functions.php';

/* -------------------------------------------------------------------------
 * The real work: one the_content pass, after everything else has rendered.
 * ---------------------------------------------------------------------- */

/**
 * Read-only heading scan shared by both the the_content fast path and the
 * full-page buffer fallback below. Returns one entry per matched heading,
 * in document order, including empty ones (marked skip) — wp_toc_inject_ids()
 * needs this exact 1:1 alignment with the <h{level}> tags it finds by regex
 * in the same $html string, or every heading after a skipped one gets the
 * wrong id.
 *
 * $scope_node, if given, additionally marks any heading that isn't a
 * descendant of it as skip — used by the buffer fallback to keep header/
 * nav/footer/sidebar headings out of the list.
 *
 * @param string       $html
 * @param array        $levels
 * @param DOMDocument  &$dom_out   Set to the parsed DOMDocument, so callers
 *                                 needing scope detection can reuse it.
 * @return array
 */
function wp_toc_scan_headings( $html, array $levels, ?DOMDocument &$dom_out = null ) {
	$tags = array_map(
		function ( $l ) {
			return 'h' . $l;
		},
		$levels
	);

	// Read-only scan for heading text/existing IDs. We deliberately never
	// write back DOMDocument's own HTML serialization — Divi and other
	// builders emit markup (inline SVGs, self-closing quirks, data
	// attributes) that DOMDocument's save routines are known to subtly
	// rewrite. Anchor IDs are injected with a targeted regex instead
	// (wp_toc_inject_ids), so everything else in $html stays byte-identical.
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	// The XML PI forces UTF-8 interpretation without adding a visible node;
	// NOIMPLIED/NODEFDTD stop libxml wrapping the fragment in <html><body>.
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$dom_out = $dom;

	$xpath    = new DOMXPath( $dom );
	$query    = '//' . implode( '|//', $tags );
	$headings = $xpath->query( $query );

	$flat_all = array();
	if ( $headings ) {
		foreach ( $headings as $heading ) {
			$text        = trim( $heading->textContent );
			$existing_id = $heading->getAttribute( 'id' );
			$flat_all[]  = array(
				'level'  => (int) substr( $heading->nodeName, 1 ),
				'text'   => $text,
				'has_id' => ( '' !== $existing_id ),
				'id'     => ( '' !== $existing_id ) ? $existing_id : null,
				'skip'   => ( '' === $text ),
				'node'   => $heading,
			);
		}
	}

	$used_ids = array();
	foreach ( $flat_all as &$item ) {
		if ( $item['skip'] ) {
			continue;
		}
		if ( $item['has_id'] ) {
			$used_ids[ $item['id'] ] = true;
		} else {
			$item['id'] = wp_toc_unique_id( $item['text'], $used_ids, 'sanitize_title' );
		}
	}
	unset( $item );

	return $flat_all;
}

/**
 * Shared final step: gate on min heading count, inject ids, build the
 * tree, register schema/CSS/JS, and replace every placeholder occurrence
 * in $content — fresh per occurrence, not one shared string, so multiple
 * [toc] instances get distinct ids instead of duplicate ones (which would
 * break toggle_view's aria-controls).
 *
 * @param string $content
 * @param array  $levels
 * @param array  $flat_all From wp_toc_scan_headings() (the 'node' key is dropped here).
 * @param array  $settings
 * @return string
 */
function wp_toc_finish( $content, array $levels, array $flat_all, array $settings ) {
	$flat = array_values(
		array_filter(
			$flat_all,
			function ( $i ) {
				return ! $i['skip'];
			}
		)
	);

	if ( count( $flat ) < $settings['min_headings'] ) {
		return str_replace( WP_TOC_PLACEHOLDER, '', $content );
	}

	$content_with_ids = wp_toc_inject_ids( $content, $levels, $flat_all );
	$tree             = wp_toc_build_tree( $flat );

	wp_toc_register_schema( $flat );
	wp_toc_mark_css_needed();
	if ( $settings['toggle_view'] ) {
		wp_enqueue_script( 'wp-toc-block' );
	}

	return preg_replace_callback(
		'/' . preg_quote( WP_TOC_PLACEHOLDER, '/' ) . '/',
		function () use ( $tree, $settings ) {
			return wp_toc_render_toc( $tree, $settings );
		},
		$content_with_ids
	);
}

add_filter( 'the_content', 'wp_toc_process_content', PHP_INT_MAX );
function wp_toc_process_content( $content ) {
	if ( false === strpos( $content, WP_TOC_PLACEHOLDER ) ) {
		return $content;
	}

	static $processed_post_id = null;

	// Only the queried singular post, not an archive/feed listing, and not
	// a secondary loop (e.g. a "related posts" section) rendering other
	// posts' content while the main query is still singular — is_singular()
	// and is_main_query() alone stay true throughout that inner loop too,
	// since they describe the main query, not whichever post is currently
	// being echoed. Also never run twice for the same post in one request,
	// whatever triggers the repeat call.
	if (
		is_feed() || ! is_singular() || ! is_main_query()
		|| get_the_ID() !== get_queried_object_id()
		|| get_the_ID() === $processed_post_id
	) {
		return str_replace( WP_TOC_PLACEHOLDER, '', $content );
	}

	$settings = wp_toc_get_settings();

	if ( $settings['min_words'] > 0 && str_word_count( wp_strip_all_tags( $content ) ) < $settings['min_words'] ) {
		return str_replace( WP_TOC_PLACEHOLDER, '', $content );
	}

	$levels             = $settings['heading_levels'];
	$dom                = null;
	$flat_all           = wp_toc_scan_headings( $content, $levels, $dom );
	$processed_post_id  = get_the_ID();

	return wp_toc_finish( $content, $levels, $flat_all, $settings );
}

/* -------------------------------------------------------------------------
 * Fallback: full-page output buffer.
 *
 * Confirmed by live testing (not theory): a [toc] placed in a Divi Code
 * module, then a Divi Text module, both produced an unreplaced placeholder
 * even with the_content hooked at PHP_INT_MAX. Divi 5 renders its module
 * tree through its own pipeline and calls do_shortcode() per module field
 * directly — the assembled HTML never gets fed back through
 * apply_filters('the_content', ...), so nothing hooked there can ever see
 * it. This buffers the entire page and does the same job on it directly.
 *
 * Only ever does anything if a placeholder survived the_content's pass —
 * on ordinary WP content that already worked, this is a single strpos()
 * check and nothing else.
 * ---------------------------------------------------------------------- */

add_action( 'template_redirect', 'wp_toc_maybe_buffer' );
function wp_toc_maybe_buffer() {
	if ( is_admin() || is_feed() || ! is_singular() || ! is_main_query() ) {
		return;
	}
	ob_start( 'wp_toc_process_buffer' );
}

/**
 * Find the element that wraps this post's own content, so the heading
 * scan can be scoped to it — otherwise a full-page scan would pick up
 * heading tags from the site header, nav, footer, or sidebar widgets.
 * Tries WordPress core's own `id="post-{ID}"` convention first (near-
 * universal regardless of builder), then a couple of Divi-specific
 * fallbacks. Returns null if none match — callers fall back to treating
 * the whole page as in-scope, which is safe but may pick up stray
 * headings from elsewhere on the page.
 *
 * @param DOMDocument $dom
 * @param int         $post_id
 * @return DOMNode|null
 */
function wp_toc_find_scope_node( DOMDocument $dom, $post_id ) {
	$xpath      = new DOMXPath( $dom );
	$candidates = array(
		'//*[@id="post-' . (int) $post_id . '"]',
		'//*[contains(@class,"et_builder_inner_content")]',
		'//*[contains(@class,"entry-content")]',
		'//*[contains(@class,"et-l--post")]',
	);
	foreach ( $candidates as $query ) {
		$nodes = $xpath->query( $query );
		if ( $nodes && $nodes->length > 0 ) {
			return $nodes->item( 0 );
		}
	}
	return null;
}

/**
 * @param DOMNode $ancestor
 * @param DOMNode $node
 * @return bool
 */
function wp_toc_node_is_within( DOMNode $ancestor, DOMNode $node ) {
	while ( $node ) {
		if ( $node === $ancestor ) {
			return true;
		}
		$node = $node->parentNode;
	}
	return false;
}

/**
 * @param string $buffer
 * @return string
 */
function wp_toc_process_buffer( $buffer ) {
	if ( false === strpos( $buffer, WP_TOC_PLACEHOLDER ) ) {
		return $buffer;
	}

	$settings = wp_toc_get_settings();
	$levels   = $settings['heading_levels'];

	$dom        = null;
	$flat_all   = wp_toc_scan_headings( $buffer, $levels, $dom );
	$scope_node = wp_toc_find_scope_node( $dom, get_queried_object_id() );

	if ( $scope_node ) {
		foreach ( $flat_all as &$item ) {
			if ( ! $item['skip'] && ! wp_toc_node_is_within( $scope_node, $item['node'] ) ) {
				$item['skip'] = true;
			}
		}
		unset( $item );
	}

	// Word count from the scoped content only, if we found it — otherwise
	// the whole page's nav/footer text would inflate the count.
	$word_count_source = $scope_node ? $scope_node->textContent : wp_strip_all_tags( $buffer );
	if ( $settings['min_words'] > 0 && str_word_count( $word_count_source ) < $settings['min_words'] ) {
		return str_replace( WP_TOC_PLACEHOLDER, '', $buffer );
	}

	$result = wp_toc_finish( $buffer, $levels, $flat_all, $settings );

	// wp_footer already ran by the time this callback fires (output
	// buffering only flushes once the whole page, footer included, has
	// been generated) — so the usual wp_footer-hooked CSS/JS from
	// wp_toc_finish() never gets printed. Inject it directly here instead.
	global $wp_toc_css_needed;
	if ( ! empty( $wp_toc_css_needed ) ) {
		$extra = wp_toc_css_string( $settings ) . wp_toc_schema_string();
		if ( $settings['toggle_view'] ) {
			$extra .= '<script src="' . esc_url( WP_TOC_URL . 'assets/toc.js?ver=' . WP_TOC_VERSION ) . '" defer></script>';
		}
		$result = ( false !== strpos( $result, '</body>' ) )
			? str_replace( '</body>', $extra . '</body>', $result )
			: $result . $extra;
	}

	return $result;
}

/**
 * Wrap the rendered list in the nav/header/toggle markup.
 *
 * @param array $tree
 * @param array $settings
 * @return string
 */
function wp_toc_render_toc( array $tree, array $settings ) {
	static $instance = 0;
	$instance++;
	$list_id = 'wp-toc-list-' . $instance;

	$header = '';
	if ( $settings['show_label'] || $settings['toggle_view'] ) {
		$header .= '<div class="wp-toc__header">';
		if ( $settings['show_label'] ) {
			$header .= '<span class="wp-toc__label">' . esc_html( $settings['label_text'] ) . '</span>';
		}
		if ( $settings['toggle_view'] ) {
			$expanded = $settings['initially_hidden'] ? 'false' : 'true';
			$header  .= '<button type="button" class="wp-toc__toggle" aria-expanded="' . $expanded . '" aria-controls="' . esc_attr( $list_id ) . '">' . esc_html__( 'Toggle', 'wp-toc-block' ) . '</button>';
		}
		$header .= '</div>';
	}

	$collapsed   = ( $settings['toggle_view'] && $settings['initially_hidden'] );
	$hidden_attr = $collapsed ? ' hidden' : '';

	$list_html = wp_toc_render_tree( $tree );
	// Tag the outer <ul> with our id/class without re-parsing it.
	$list_html = preg_replace( '/^<ul>/', '<ul class="wp-toc__list" id="' . esc_attr( $list_id ) . '"' . $hidden_attr . '>', $list_html, 1 );

	// is-collapsed is set server-side (not just added by JS on first click)
	// so the page loads already at its shrink-to-fit width instead of
	// flashing full-width-then-shrinking once JS runs.
	$nav_class = 'wp-toc wp-toc--align-' . sanitize_html_class( $settings['alignment'] );
	if ( $collapsed ) {
		$nav_class .= ' is-collapsed';
	}

	return '<nav class="' . esc_attr( $nav_class ) . '" aria-label="' . esc_attr__( 'Table of contents', 'wp-toc-block' ) . '">' . $header . $list_html . '</nav>';
}

/* -------------------------------------------------------------------------
 * Schema.org ItemList JSON-LD — the correct type for an in-page TOC.
 * ---------------------------------------------------------------------- */

function wp_toc_register_schema( array $flat ) {
	global $wp_toc_schema_items;
	if ( ! is_array( $wp_toc_schema_items ) ) {
		$wp_toc_schema_items = array();
	}
	foreach ( $flat as $item ) {
		$wp_toc_schema_items[] = array(
			'@type' => 'ListItem',
			'position' => count( $wp_toc_schema_items ) + 1,
			'name'  => $item['text'],
			'url'   => get_permalink() . '#' . $item['id'],
		);
	}
}

/**
 * @return string
 */
function wp_toc_schema_string() {
	global $wp_toc_schema_items;
	if ( empty( $wp_toc_schema_items ) ) {
		return '';
	}
	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'itemListElement' => $wp_toc_schema_items,
	);
	return '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
}

add_action( 'wp_footer', 'wp_toc_print_schema' );
function wp_toc_print_schema() {
	echo wp_toc_schema_string();
}

/* -------------------------------------------------------------------------
 * CSS (settings-driven, printed once per page) and toggle JS.
 * ---------------------------------------------------------------------- */

function wp_toc_mark_css_needed() {
	global $wp_toc_css_needed;
	$wp_toc_css_needed = true;
}

/**
 * @param array $s Settings.
 * @return string
 */
function wp_toc_css_string( array $s ) {
	ob_start();
	?>
	<style>
		.wp-toc {
			background: <?php echo esc_html( $s['color_bg'] ); ?>;
			color: <?php echo esc_html( $s['color_text'] ); ?>;
			border: 1px solid <?php echo esc_html( $s['color_border'] ); ?>;
			padding: 1em 1.5em;
			box-sizing: border-box;
			width: 100%;
			max-width: <?php echo (int) $s['max_width']; ?>px;
			margin: 0 0 1em 0;
		}
		.wp-toc.is-collapsed { width: fit-content; max-width: 100%; }
		.wp-toc.is-collapsed .wp-toc__header { white-space: nowrap; }
		.wp-toc--align-left { float: left; margin: 0 1.5em 1em 0; }
		.wp-toc--align-right { float: right; margin: 0 0 1em 1.5em; }
		.wp-toc--align-center { margin: 0 auto 1em; }
		.wp-toc__header { display: flex; align-items: center; justify-content: space-between; gap: 1em; }
		.wp-toc__label { font-weight: 600; }
		.wp-toc__toggle { background: none; border: 1px solid currentColor; cursor: pointer; padding: .25em .75em; flex-shrink: 0; }
		.wp-toc__list, .wp-toc__list ul { list-style: none; margin: 0; padding: 0; }
		.wp-toc__list { margin-top: .75em; }
		.wp-toc__list ul {
			font-size: calc(<?php echo esc_html( $s['scale_ratio'] ); ?> * 1em);
			margin-left: <?php echo (int) $s['indent_px']; ?>px;
		}
		.wp-toc a { color: <?php echo esc_html( $s['color_link'] ); ?>; text-decoration: none; }
		.wp-toc a:hover { color: <?php echo esc_html( $s['color_link_hover'] ); ?>; text-decoration: underline; }
	</style>
	<?php
	return ob_get_clean();
}

add_action( 'wp_footer', 'wp_toc_print_css', 5 );
function wp_toc_print_css() {
	global $wp_toc_css_needed;
	if ( empty( $wp_toc_css_needed ) ) {
		return;
	}
	echo wp_toc_css_string( wp_toc_get_settings() );
}

add_action( 'init', 'wp_toc_register_script' );
function wp_toc_register_script() {
	wp_register_script( 'wp-toc-block', WP_TOC_URL . 'assets/toc.js', array(), WP_TOC_VERSION, true );
}

