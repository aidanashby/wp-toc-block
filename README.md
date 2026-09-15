# WP TOC Block

A lightweight WordPress plugin that adds a `[toc]` shortcode for a table of contents, built from the headings in the current post or page. No block editor block, no auto-insertion — you place the shortcode where you want the table of contents to appear.

Built for Divi 5.

## Features

- `[toc]` shortcode, usable multiple times on the same page
- Settings page (linked from the Plugins list) to control:
  - Which element holds the post content (so site header, menu and footer headings stay out of the list)
  - Minimum number of headings required before it displays
  - Minimum word count required before it displays (0 = unlimited)
  - Header label text, and whether to show it
  - Toggle view (collapsible), and whether it starts collapsed
  - Which heading levels (H2–H6) are included
  - Heading size scale ratio, and per-level indent for nested items (em)
  - Line height and space between items (em)
  - Maximum width when expanded, in px (shrinks to fit the label and toggle icon when collapsed, if toggle view is on)
  - Alignment: left, right, or centre
  - Float — whether content flows around the block or it sits on its own line above what follows (left/right only; centre never floats, and nothing floats on screens 767px and narrower)
  - List markers: none, bullets, or numbers
  - Scroll offset in px, so a clicked link doesn't land the heading underneath a fixed header bar
  - Colours for background, text, links, hover, and border

Text-scale values (indent, line height, item spacing) are in em so they track the surrounding font size. Block and viewport dimensions — maximum width and scroll offset — stay in px.
- With toggle view on, the whole top of the block expands and collapses it, with a rotating chevron and a short animation (respects `prefers-reduced-motion`)
- Adds anchor IDs to headings that don't already have one, without colliding with IDs already used on the page
- The scroll offset is applied as `scroll-margin-top` on the headings rather than by intercepting clicks, so it also covers arriving on a `#hash` link directly and browser back/forward
- Outputs `ItemList` schema.org JSON-LD for the generated list

## Usage

Place `[toc]` anywhere in a post or page's content — any Divi module (Text, Code, etc.) works.

If the table of contents doesn't appear, check the browser console: the plugin warns there when the **content container selector** doesn't match anything on the page.
Set it under Settings → WP TOC Block to whatever wraps your post content (the default, `.et_pb_post_content`, matches Divi 5's Post Content module).

## How it works, and why

The list is built in the browser, not in PHP.

Divi 5 assembles its module tree through its own rendering pipeline and never passes the finished HTML back through WordPress's `the_content` filter, so no server-side filter reliably sees the rendered markup — a `[toc]` in any Divi module produced nothing at all when hooked there.

Parsing the whole page server-side in an output buffer was tried as a workaround and rejected: a failure there — memory exhaustion in particular, which `try`/`catch` cannot catch — takes down the entire page, which is an unacceptable risk for what is only a navigation aid. Client-side, the finished DOM is simply there, however Divi built it, and the worst case is no table of contents.

The trade-off is that the list isn't in the server HTML, and it needs JavaScript.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Development

`node test-toc.mjs` runs the self-check for the tree-nesting and anchor-ID logic.

## Licence

MIT — see [LICENSE](LICENSE).
