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
		'scope_selector'  => '.et_pb_post_content',
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
		'list_type'       => 'none',
		'nested_list'     => true,
		'color_bg'        => '#eaeaea',
		'color_text'      => '#1e1e1e',
		'color_link'      => '#1e1e1e',
		'color_link_hover' => '#d72715',
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

	if ( isset( $input['scope_selector'] ) ) {
		$selector               = sanitize_text_field( $input['scope_selector'] );
		$out['scope_selector']  = '' !== $selector ? $selector : $defaults['scope_selector'];
	}

	if ( isset( $input['min_headings'] ) ) {
		$out['min_headings'] = max( 1, absint( $input['min_headings'] ) );
	}
	if ( isset( $input['min_words'] ) ) {
		$out['min_words'] = absint( $input['min_words'] );
	}

	$out['nested_list']      = ! empty( $input['nested_list'] );
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

	if ( isset( $input['list_type'] ) ) {
		$out['list_type'] = in_array( $input['list_type'], array( 'none', 'bullet', 'number' ), true )
			? $input['list_type']
			: $defaults['list_type'];
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
					<th scope="row"><label for="toc-scope"><?php esc_html_e( 'Content container selector', 'wp-toc-block' ); ?></label></th>
					<td>
						<input name="<?php echo $opt; ?>[scope_selector]" id="toc-scope" type="text" value="<?php echo esc_attr( $s['scope_selector'] ); ?>" class="regular-text code">
						<p class="description"><?php esc_html_e( 'CSS selector for the element holding the post content. Only headings inside it are listed, which keeps site header, menu and footer headings out. Default suits Divi 5\'s Post Content module.', 'wp-toc-block' ); ?></p>
					</td>
				</tr>
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
								'none'   => __( 'None (full width, up to the maximum)', 'wp-toc-block' ),
								'left'   => __( 'Left (content flows around it)', 'wp-toc-block' ),
								'right'  => __( 'Right (content flows around it)', 'wp-toc-block' ),
								'center' => __( 'Centre', 'wp-toc-block' ),
							);
							foreach ( $alignments as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['alignment'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Nested list', 'wp-toc-block' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo $opt; ?>[nested_list]" value="1" <?php checked( $s['nested_list'] ); ?>>
							<?php esc_html_e( 'Indent sub-headings under their parent heading', 'wp-toc-block' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Unchecked lists every heading at the same level.', 'wp-toc-block' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="toc-list-type"><?php esc_html_e( 'List markers', 'wp-toc-block' ); ?></label></th>
					<td>
						<select name="<?php echo $opt; ?>[list_type]" id="toc-list-type">
							<?php
							$list_types = array(
								'none'   => __( 'None', 'wp-toc-block' ),
								'bullet' => __( 'Bullets', 'wp-toc-block' ),
								'number' => __( 'Numbers', 'wp-toc-block' ),
							);
							foreach ( $list_types as $value => $label ) :
								?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['list_type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
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
 * Front end.
 *
 * The table of contents is built in the browser, not in PHP. Divi 5 renders
 * its module tree through its own pipeline and never feeds the assembled
 * HTML back through the_content, so there is no server-side filter that
 * reliably sees the finished markup. Parsing the whole page server-side to
 * work around that was tried and rejected: a failure there (memory
 * exhaustion in particular, which try/catch cannot catch) takes the entire
 * page down, which is an absurd risk for a navigation aid.
 *
 * So PHP only emits a mount element carrying the settings. toc.js finds the
 * headings in the already-rendered DOM, where it does not matter how Divi
 * assembled them. Worst case is no table of contents.
 * ---------------------------------------------------------------------- */

add_shortcode( 'toc', 'wp_toc_shortcode' );
function wp_toc_shortcode( $atts ) {
	$s = wp_toc_get_settings();

	$config = array(
		'scope'       => $s['scope_selector'],
		'levels'      => array_map( 'intval', $s['heading_levels'] ),
		'minHeadings' => (int) $s['min_headings'],
		'minWords'    => (int) $s['min_words'],
		'showLabel'   => (bool) $s['show_label'],
		'labelText'   => $s['label_text'],
		'toggle'      => (bool) $s['toggle_view'],
		'startHidden' => (bool) $s['initially_hidden'],
		'scale'       => (float) $s['scale_ratio'],
		'indent'      => (int) $s['indent_px'],
		'maxWidth'    => (int) $s['max_width'],
		'align'       => $s['alignment'],
		'listType'    => $s['list_type'],
		'nested'      => (bool) $s['nested_list'],
		'colors'      => array(
			'bg'        => $s['color_bg'],
			'text'      => $s['color_text'],
			'link'      => $s['color_link'],
			'linkHover' => $s['color_link_hover'],
			'border'    => $s['color_border'],
		),
	);

	return '<div class="wp-toc-mount" data-wp-toc="' . esc_attr( wp_json_encode( $config ) ) . '"></div>';
}

/**
 * Enqueued on every front-end view rather than from the shortcode
 * callback: Divi renders modules through its own pipeline, so the callback
 * can't be relied on to run before scripts are printed. The script exits
 * immediately when there's no mount element on the page.
 */
add_action( 'wp_enqueue_scripts', 'wp_toc_enqueue' );
function wp_toc_enqueue() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_script( 'wp-toc-block', WP_TOC_URL . 'assets/toc.js', array(), WP_TOC_VERSION, true );
}
