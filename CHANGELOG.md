# Changelog

## Unreleased

- Initial build: `[toc]` shortcode, settings page, heading scan with anchor
  assignment, nested list rendering, colour/scale/indent settings, toggle
  view, `ItemList` schema.org JSON-LD.
- Uninstall now also removes the Plugin Update Checker library's own
  bookkeeping (its `external_updates-wp-toc-block` option and
  `puc_cron_check_updates-wp-toc-block` cron event), which it does not
  clean up on its own.
