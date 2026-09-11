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
- Removed the "post types" setting — dead weight, since display is entirely
  controlled by where you place the `[toc]` shortcode, not by an automatic
  content-type match.
- Found in live testing on Divi: `[toc]` placed in Divi's **Code** module
  produces an unreplaced `<!--WP_TOC_PLACEHOLDER-->` comment, because the
  Code module runs shortcodes via a direct `do_shortcode()` call on its own
  saved text, bypassing the `the_content` filter chain entirely. Not a bug
  in this plugin — documented as a placement constraint (use a Text module,
  or the block/classic editor body, instead).
- Added maximum-width and alignment settings. Collapsed (toggle view, list
  hidden) now shrinks to fit just the label and toggle icon rather than
  sitting at the full expanded width; expanded width defaults to 250px via
  the new setting. Alignment adds none/left/right/centre.
- Found in further live testing: the placeholder stayed unreplaced in a
  Divi Text module too, not just Code. Root cause confirmed (with an
  opus-advisor second opinion) — Divi 5 renders its whole module tree
  through its own pipeline, calling `do_shortcode()` per module field
  directly; the assembled HTML never passes through `apply_filters(
  'the_content', ...)`, so nothing hooked there can ever see it, on any
  module. Added a full-page output-buffer fallback (`template_redirect`
  + `ob_start`), scoped to the post's own content wrapper (tries
  `#post-{ID}` first, then a couple of Divi-specific classes) so header/
  nav/footer/sidebar headings can't leak into the list. The `the_content`
  filter stays as the fast path for non-builder content.
