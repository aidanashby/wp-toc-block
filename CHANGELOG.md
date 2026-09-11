# Changelog

## Unreleased

- Initial build: `[toc]` shortcode, settings page, heading scan with anchor
  assignment, nested list rendering, colour/scale/indent settings, toggle
  view, `ItemList` schema.org JSON-LD.
- Uninstall now also removes the Plugin Update Checker library's own
  bookkeeping (its `external_updates-wp-toc-block` option and
  `puc_cron_check_updates-wp-toc-block` cron event), which it does not
  clean up on its own.
- Fixed before first release, caught in review: multiple `[toc]` shortcodes
  on one page produced duplicate `id`s, breaking toggle_view on all but the
  first instance. Anchor ID assignment no longer reserializes the whole
  post content through DOMDocument (risk of corrupting builder markup it
  didn't need to touch) — IDs are injected into the original content with
  a targeted, alignment-safe regex instead. The `the_content` filter guard
  no longer relies on `in_the_loop()` (unreliable with some builders) and
  now explicitly excludes secondary loops (e.g. related-posts sections) via
  a post-ID check.
