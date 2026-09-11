<?php
/**
 * Runs on plugin delete. Removes the single stored option.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_toc_settings' );
