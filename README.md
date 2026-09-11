# WP TOC Block

A lightweight WordPress plugin that adds a `[toc]` shortcode for a table of contents,
built from the headings in the current post or page. No block editor block, no
auto-insertion — you place the shortcode where you want the table of contents to appear.

## Features

- `[toc]` shortcode, usable multiple times on the same page
- Settings page (linked from the Plugins list) to control:
  - Minimum number of headings required before it displays
  - Minimum word count required before it displays (0 = unlimited)
  - Header label text, and whether to show it
  - Toggle view (collapsible), and whether it starts collapsed
  - Which heading levels (H2–H6) are included
  - Heading size scale ratio and per-level indent for nested items
  - Maximum width when expanded (shrinks to fit the label and toggle icon when
    collapsed, if toggle view is on)
  - Alignment: none (full width, in the flow), left/right (floats, text wraps
    around it), or centre
  - Colours for background, text, links, hover, and border
- Automatically adds anchor IDs to headings that don't already have one
- Outputs `ItemList` schema.org JSON-LD for the generated list
- Compatible with Divi 5 — reads the final rendered page content, not raw post content

## Usage

Place `[toc]` anywhere in a post or page's content. It only works inside the main
post/page content area — not inside widgets or Divi 5 Theme Builder template parts
(headers, footers, global templates), since those aren't part of the post content.

In Divi, place it in a **Text** module (or directly in the block/classic editor body) —
not the **Code** module. The Code module runs shortcodes by calling `do_shortcode()`
directly on its own saved text, bypassing the standard `the_content` filter chain this
plugin hooks into, so `[toc]` there produces an unreplaced placeholder comment instead
of the table of contents.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Development

See `PLAN.md` for the implementation plan and design decisions.

## Licence

MIT — see [LICENSE](LICENSE).
