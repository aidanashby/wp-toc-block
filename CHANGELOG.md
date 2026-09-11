# Changelog

## Unreleased

- **Rewritten to build the table of contents client-side.** Every server-side
  approach failed against Divi 5, which assembles its module tree through its
  own pipeline and never passes the finished HTML back through `the_content`.
  Hooking `the_content` produced nothing in any Divi module; parsing the whole
  page in an output buffer instead worked only intermittently and, when it
  failed (memory exhaustion, which `try`/`catch` cannot catch), returned an
  empty response — blanking the entire page on any post using the shortcode.
  That is an unacceptable failure mode for a navigation aid.

  PHP now only emits a mount element carrying the settings; `assets/toc.js`
  finds the headings in the already-rendered DOM, where how Divi built them no
  longer matters, and the worst case is no table of contents. Removed the
  `the_content` filter, the output buffer, the server-side DOM parsing, the
  anchor-injection regex, and the debug instrumentation that went with them —
  the plugin is less than half its previous size.

  Trade-off: the list isn't in the server HTML and requires JavaScript.
- Added a "content container selector" setting (default `.et_pb_post_content`),
  so the element holding the post content can be corrected without a code
  change. Guessing it wrongly was the cause of a long debugging detour; the
  plugin now warns in the browser console when it matches nothing.

- Fixed the actual bug: the guessed content-wrapper class names
  (`et_builder_inner_content`, `entry-content`, `et-l--post`) didn't match
  anything on this site, so the buffer fallback marked all real headings
  "out of scope" and correctly-but-uselessly stripped the placeholder to
  empty. Confirmed via debug console output, then the real class —
  `et_pb_post_content` (Divi 5's actual "Post Content" module wrapper on
  this site) — given directly by the site owner. Plugin targets this
  theme only, so the generic guesses were dropped rather than kept as
  fallbacks.

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
