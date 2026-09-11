# WP Table of Contents Block — Implementation Plan

Shortcode-only, no auto-insert, Divi 5 compatible (hooks into `the_content` post-render —
Divi's internal storage format is irrelevant, see below).

## File structure (single-file-plugin lazy default, split only where it earns it)

```
wp-toc-block/
  wp-toc-block.php        # bootstrap: header, activation, hooks, shortcode registration
  includes/
    class-settings.php    # Settings API page + option get/defaults
    class-render.php       # heading scan, ID assignment, list build, output HTML
    class-schema.php       # ItemList JSON-LD output
  assets/
    toc.css                # colour/spacing vars, generated inline from settings (see below)
    toc.js                 # toggle open/close only — no scrollspy (out of scope)
  readme.txt               # WP.org-style header (version, tested up to) for update-checker parity w/ reading-progress plugin
```

skipped: build step / bundler — plain CSS+JS, no npm needed for this scope.

## Settings (Settings API, one page, `includes/class-settings.php`)

| Setting | Type | Default |
|---|---|---|
| Enabled post types | checkboxes over `get_post_types(['public'=>true])` | `post`, `page` |
| Min heading count | number | 2 |
| Min word count | number | 0 (unlimited) |
| Show header label | checkbox | on |
| Header label text | text (shown only if above checked) | "Table of Contents" |
| Toggle view | checkbox | off |
| Initially collapsed | checkbox | on |
| Heading levels included | checkbox group H2–H6 | H2, H3 |
| Heading size scale ratio | number (e.g. 0.9) | 0.9 — each level down multiplies font-size by this |
| Per-level left indent | number (px) | 16 |
| Colours: background, text, link, link hover, border | color picker (`wp-color-picker`, bundled w/ core, no new dep) | theme-neutral defaults |

All stored as one array option (`wp_toc_settings`), not individual options — one `get_option` call, one autoload entry.

## Heading scan & anchor assignment (`class-render.php`)

**Ordering constraint:** `do_shortcode()` itself runs on `the_content` at priority 11. A
naive design that builds the heading list *inside* the `[toc]` shortcode callback runs
before headings even exist in the pipeline — it would always render empty.

Correct design — placeholder + single late-priority pass:
1. Shortcode `[toc]` callback does no scanning. It just returns a placeholder marker
   (`<!--WP_TOC_PLACEHOLDER-->`) immediately, once per instance.
2. One `add_filter('the_content', [...], PHP_INT_MAX)` — guaranteed to run after every other
   content filter, Divi's own rendering included, regardless of Divi 5's internal storage
   format — does the actual work once per request:
   - Guard: `is_singular() && in_the_loop() && is_main_query() && !is_feed()` — without this,
     the filter also fires (and duplicates/mangles output) on archive/index loops and RSS/Atom
     feeds.
   - `DOMDocument`: load the fragment with UTF-8 forced (British copy uses en dashes/curly
     quotes — without explicit UTF-8 handling `loadHTML()` mangles them) and
     `libxml_use_internal_errors(true)` around the call (suppress libxml's HTML5-tag noise
     only — not a general error swallow). Pull output back via `saveHTML()` on the body's
     child nodes, not the whole document — `loadHTML()` wraps fragments in `<html><body>`,
     and re-saving the whole document duplicates that wrapper into the page.
   - Query configured heading levels (H2–H6 per setting) in document order.
   - If under min heading count or under min word count (`str_word_count(wp_strip_all_tags($content))`)
     → replace every placeholder with an empty string, skip the rest.
   - For each heading: keep an existing `id` if present; otherwise
     `id = sanitize_title($heading->textContent)`. Track used IDs in a set for this render;
     on collision append `-2`, `-3`, ...
   - Escape on output: `esc_html()` the heading text into the `<a>`, `esc_attr()` the id into
     the `href`. Built from arbitrary post content, so this isn't optional.
   - Build nested `<ul>` by heading level (H2 > H3 = child list); if content skips a level
     (H2 straight to H4), nest under the last-seen shallower heading — no extra setting needed.
   - `str_replace()` every `WP_TOC_PLACEHOLDER` occurrence with the built list markup — this
     makes rendering every shortcode instance (no dedupe) free: one scan, N replacements.

**Stated constraint:** `[toc]` only works placed inside actual post/page content. Divi 5
Theme Builder areas (global header/footer/template parts) don't pass through `the_content` at
all, so a `[toc]` dropped there won't be replaced. Document this rather than let someone
discover it as a silent bug.

## Output markup

```html
<nav class="wp-toc" aria-label="Table of contents">
  <div class="wp-toc__header">
    <span class="wp-toc__label">Table of Contents</span>
    <button class="wp-toc__toggle" aria-expanded="false" aria-controls="wp-toc-list-{id}">...</button>
  </div>
  <ul class="wp-toc__list" id="wp-toc-list-{id}">
    <li><a href="#heading-id">Heading text</a>
      <ul><li><a href="#sub-heading-id">Sub heading</a></li></ul>
    </li>
  </ul>
</nav>
```

- Toggle button only rendered if "toggle view" setting is on.
- `wp-toc__list[hidden]` (native `hidden` attribute, not inline style) when "initially collapsed" is on — `toc.js` just flips `hidden` + `aria-expanded` on click, no animation library.
- Colours/scale/indent settings → emitted as a small `<style>` block with CSS custom properties, scoped to `.wp-toc`, printed once per page load (not once per shortcode instance) in `wp_footer` if the TOC was used.

## Schema

`class-schema.php`: when a TOC renders, register the heading list as an `ItemList` (`itemListElement` = each heading as `url` = anchor, `name` = heading text) and inject as JSON-LD in `wp_footer`. `ItemList` is the correct schema.org type for an in-page TOC — there is no "site navigation" schema type applicable here. One block per page even with multiple shortcode instances.

## Activation / plugin header

Follows the `reading-progress` plugin's existing pattern for this dev environment (Plugin
Update Checker bundled, version constant, WP.org-style readme.txt, text domain for
`esc_html__()` strings) — reuse that scaffold rather than reinventing one.

Settings page: `current_user_can('manage_options')` capability check + `settings_fields()`
nonce, standard Settings API — the actual security boundary in this plugin, since the
frontend side takes no user input at all. One-line `register_uninstall_hook` to delete the
`wp_toc_settings` option on uninstall.

## Out of scope
- Auto-insert / block editor block — shortcode only
- Per-post-type or per-post override — global settings only
- Active-heading scrollspy highlighting
- Any JS beyond toggle open/close

## Build order
1. Bootstrap file + settings page (Settings API, no JS needed for the form itself except colour picker enqueue)
2. Render class: DOM scan, ID assignment, nested list build, word/heading-count gating
3. Shortcode registration wired to render class
4. CSS custom properties + `<style>` emission from settings
5. Toggle JS
6. Schema JSON-LD
7. Manual test pass on the remote dev site — WP native post, then a Divi 5 builder page, confirm headings inside Divi modules are picked up
