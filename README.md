# Co-Entrepreneur Content Studio

A small private WordPress plugin that previews a structured JSON article package and creates or updates a WordPress draft. WordPress **Posts → Drafts** remains the queue.

## Requirements

- WordPress 6.5 or newer
- PHP 7.4 or newer
- Rank Math for SEO fields
- Polylang for Turkish language assignment
- A registered `content_cluster` taxonomy for posts

Rank Math and Polylang are soft runtime dependencies: the plugin shows a warning if either is unavailable and still creates a draft for manual correction.

## Install

1. Place this directory at `wp-content/plugins/co-entrepreneur-content-studio`, or upload its ZIP from **Plugins → Add New → Upload Plugin**.
2. Activate **Co-Entrepreneur Content Studio**.
3. Open **Content Studio** in WordPress Admin.
4. Choose a default author and optionally enter preferred external domains, one hostname per line.
5. Paste a package or upload a `.json` file and select **Preview Package**.
6. Review all errors, warnings, resolved fields and links.
7. Select any missing tags that should be created, then select **Create Draft**.
8. Complete editorial review and publish manually from the native WordPress editor.

The plugin never publishes, schedules or deletes content. It never modifies a non-draft post.

## Package fields

Required:

- `content_id`: stable lowercase identifier used for duplicate detection
- `language`: `tr`
- `title`
- `slug`
- `body_html`
- `category_slugs`: at least one existing category
- `content_cluster_slugs`: at least one existing cluster

Supported optional fields:

- `schema_version`: currently `1.0`
- `seo_title`
- `meta_description`
- `focus_keyword`
- `excerpt`
- `author`: WordPress user ID or login; otherwise the configured default is used
- `tag_slugs`
- `featured_image`: `null` or `{ "media_id": 123, "alt_text": "..." }`
- `required_internal_urls`
- `reciprocal_link_suggestions`

See `samples/valid-post.json` and `samples/invalid-post.json`.

## Duplicate behavior

The plugin stores the stable identifier in `_ce_content_id` and a normalized package hash in `_ce_package_hash`.

- No matching post: create a new draft.
- Matching draft: show it and require confirmation before updating.
- Matching non-draft post: stop and provide its edit link.
- Multiple posts with the same identifier: stop for manual cleanup.

## HTML behavior

The validator blocks `<h1>`, scripts, iframes, embedded objects, forms, inline event handlers, executable URLs and common publishing placeholders. Draft HTML is filtered through a small allowlist covering headings below H1, paragraphs, lists, links, tables, figures, images, code and class-based content blocks. Unsupported markup is shown as a warning and removed on draft creation.

## Rank Math and Polylang verification

Rank Math writes are isolated in `includes/class-rank-math-adapter.php`. Before using the plugin on the live site, smoke-test its three keys against the exact installed Rank Math version:

- `rank_math_title`
- `rank_math_description`
- `rank_math_focus_keyword`

Polylang assignment uses `pll_set_post_language( $post_id, 'tr' )` and reads the language back when `pll_get_post_language()` is available.

## Manual smoke test

1. Install and activate the plugin on one disposable or staging WordPress site.
2. Import the valid sample after matching its taxonomy slugs to the site.
3. Confirm exactly one draft, its WordPress fields, Rank Math values, Polylang language and `/tr/` permalink.
4. Confirm the invalid sample is blocked without creating a post.
5. Re-import the valid `content_id` and confirm an update requires explicit confirmation.
6. Publish that test post manually, re-import it and confirm the plugin refuses to modify it.
7. Deactivate the plugin and confirm the post remains intact.

## Removal

Deactivation and uninstall intentionally leave posts, terms, media, post metadata and plugin settings untouched.
