# Changelog

All notable changes to WP TOC Block are documented here.

## 0.1.0

Initial release. Built for Divi 5.

- `[toc]` shortcode, usable more than once on a page, building a nested table of
  contents from the headings in the post content.
- Settings page under Settings → WP TOC Block, with a Settings link on the Plugins
  screen:
  - **Content container selector** (default `.et_pb_post_content`) — which element
    holds the post content, so site header, menu and footer headings stay out of
    the list. The plugin warns in the browser console when it matches nothing.
  - Minimum heading count and minimum word count before the list displays.
  - Header label text, and whether to show it.
  - Toggle view (collapsible), and whether it starts collapsed.
  - Heading levels included (H2–H6).
  - Heading size scale ratio, per-level indent, line height, and space between
    items.
  - Maximum width, alignment (left/right/centre), and whether the block floats so
    content flows around it. Nothing floats on screens 767px and narrower, where
    there isn't the width for content to sit alongside it.
  - List markers: none, bullets or numbers.
  - Scroll offset, to stop a clicked link landing the heading underneath a fixed
    header bar.
  - Colours for background, text, links, hover and border.
- With toggle view on, the whole top of the block expands and collapses it — a real
  `<button>`, so it stays keyboard-operable and properly announced. Width and height
  animate together over 220ms with a rotating chevron, and `prefers-reduced-motion`
  is honoured.
- Anchor IDs are added to headings that don't already have one, without colliding
  with IDs already used elsewhere on the page. The scroll offset is applied as
  `scroll-margin-top` on the headings rather than by intercepting clicks, so it also
  covers arriving on a `#hash` directly and browser back/forward.
- Outputs `ItemList` schema.org JSON-LD for the generated list.
- Self-updates from GitHub releases via the bundled Plugin Update Checker. Uninstall
  removes the plugin's own option plus the update checker's bookkeeping, which that
  library doesn't clean up itself.

### Note on the approach

The list is built in the browser rather than in PHP. Divi 5 assembles its module
tree through its own pipeline and never passes the finished HTML back through
WordPress's `the_content` filter, so no server-side filter reliably sees the
rendered markup. Parsing the whole page server-side in an output buffer was tried
and rejected — a failure there (memory exhaustion in particular, which `try`/`catch`
cannot catch) takes the entire page down, which is an unacceptable risk for a
navigation aid. See the README for the full reasoning and trade-offs.

Text-scale values (indent, line height, item spacing) are in em; block and viewport
dimensions (maximum width, scroll offset) are in px.
