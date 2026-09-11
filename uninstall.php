<?php
/**
 * Runs on plugin delete. Removes the plugin's own option, plus the
 * bookkeeping the vendored Plugin Update Checker library leaves behind
 * (it does not clean up after itself: see its Plugin\UpdateChecker
 * constructor default `external_updates-$slug` option name, and
 * Scheduler::getCronHookName() for the cron hook name).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wp_toc_settings' );

delete_site_option( 'external_updates-wp-toc-block' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-wp-toc-block' );
