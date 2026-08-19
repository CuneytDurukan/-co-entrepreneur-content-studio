# Co-Entrepreneur Content Studio — Lean Codex Handoff

**Project:** Small private WordPress publishing helper  
**Website:** https://co-entrepreneur.net  
**Owner:** Cüneyt  
**Primary language:** Turkish  
**SEO plugin:** Rank Math  
**Language plugin:** Polylang  
**Last updated:** 2026-08-19

---

## 1. Product decision

Build a small WordPress plugin that converts a structured article package into a WordPress draft.

This is a low-volume internal convenience tool for approximately 8–10 blog posts per month. It is not a business-critical platform. Optimize for:

- quick delivery;
- simple operation;
- minimal maintenance;
- easy removal;
- WordPress-native behavior.

Do not design enterprise infrastructure around it.

### Core rule

> Paste or upload the article package, preview it, and create a WordPress draft.

The plugin must never publish automatically and must never delete posts, pages, media, terms or revisions.

---

## 2. Normal user workflow

1. The article is researched and finalized in ChatGPT.
2. ChatGPT produces one JSON publication package.
3. The user opens **WordPress Admin → Content Studio**.
4. The user pastes the JSON or uploads the `.json` file.
5. The plugin shows a clear preview and a short list of errors or warnings.
6. The user clicks **Create Draft**.
7. The native WordPress editor opens.
8. The user reviews the article and manually publishes it.

WordPress **Posts → Drafts** is the content queue. Do not build a separate queue system.

---

## 3. V1 scope

### 3.1 One simple admin screen

Create one WordPress-native screen:

**Content Studio → Import Draft**

It needs only:

- paste JSON;
- upload JSON;
- preview parsed fields;
- show errors and warnings;
- create or update a draft;
- link to the resulting WordPress edit and preview screens.

A small Settings section may be placed on the same page or a second simple page. No React application is needed.

### 3.2 Fields written to the draft

- WordPress title
- slug
- article HTML
- excerpt
- author, using a configured default when omitted
- category
- tags
- `content_cluster`
- featured-image media ID and alt text when supplied
- Turkish language through Polylang
- SEO title through Rank Math
- meta description through Rank Math
- optional focus keyword through Rank Math

Social metadata, custom canonical URLs, per-post robots rules and custom schema are not required in v1. They inherit the existing Rank Math defaults.

### 3.3 Internal links

The publication package should already contain the approved contextual links in `body_html`.

The plugin should:

- show the internal links found in the article;
- warn when a required pillar link is missing;
- warn when an internal URL appears to use the wrong language path;
- optionally list other published posts in the same `content_cluster` as link suggestions.

V1 must not automatically rewrite existing published posts. For reciprocal links, show the suggested source post, anchor and target, plus an **Open post to edit** link. The user can make that small change manually.

This avoids building a patch engine, approval queue, audit system and rollback interface for a low-volume task.

---

## 4. Simple publication-package format

Use a small, documented JSON contract. Basic PHP validation is sufficient; a JSON Schema file may be included if it remains simple.

```json
{
  "schema_version": "1.0",
  "content_id": "ce-c1-01-bv-branch-distributor",
  "language": "tr",
  "title": "Hollanda’ya Giriş Modeli: BV, Şube, Distribütör veya Acente?",
  "seo_title": "Hollanda’da BV mi, Şube mi, Distribütör mü?",
  "meta_description": "Türk şirketleri için Hollanda’ya giriş modellerini kontrol, maliyet ve operasyon açısından karşılaştırın.",
  "focus_keyword": "Hollanda'da şirket kurma modelleri",
  "slug": "hollandada-bv-sube-distributor-acente",
  "excerpt": "Hollanda pazarına giriş modellerini ve doğru seçimi değerlendirin.",
  "category_slugs": ["rehberler"],
  "tag_slugs": ["hollanda-bv", "avrupa-pazara-giris"],
  "content_cluster_slugs": ["netherlands-eu-expansion-base"],
  "body_html": "<p>...</p>",
  "featured_image": null,
  "required_internal_urls": [
    "https://co-entrepreneur.net/tr/hollandada-sirket-kurma-turk-sirketler-icin-avrupa-operasyonu-rehberi/"
  ],
  "reciprocal_link_suggestions": [
    {
      "source_post_id": 550,
      "anchor": "BV, şube, distribütör veya acente karşılaştırması"
    }
  ]
}
```

Do not require editorial-approval flags inside the JSON. The WordPress draft and manual publication step are the editorial gate.

---

## 5. Validation

### Block draft creation only for clear problems

- Invalid JSON.
- Missing title, slug, body, language or `content_id`.
- An `<h1>` exists in the article body.
- Obvious placeholders remain, such as `INTERNAL:`, `href="#"`, `[SOYADI]` or `REPLACE_DURING_SETUP`.
- Active content such as `<script>`, iframe or inline event handlers is present.
- The requested category or `content_cluster` does not exist.
- A required internal URL is missing from the body.

### Warnings that do not block draft creation

- Missing featured image or alt text.
- Long or short SEO title/description.
- Unknown tag. The preview may offer to create the missing tag when the draft is created.
- External domain not on the preferred-source list.
- Imperfect heading order.
- Missing reciprocal-link suggestion.

Do not make the Rank Math SEO score a publication gate. Do not perform remote link-health checks in v1.

---

## 6. Draft-only and non-destructive behavior

- Always force `post_status = draft`.
- Do not include a publish or schedule action.
- Do not include deletion actions.
- Do not modify an already published post through normal import.
- If the same `content_id` belongs to an existing draft, show the draft and allow an update after confirmation.
- If the same `content_id` belongs to a published post, show the post link and stop. The user can decide how to update it manually.
- Deactivation and uninstall must leave all WordPress content untouched.

Use `_ce_content_id` and an optional package hash in post metadata for duplicate detection. No custom database tables are needed.

---

## 7. Integrations

### Rank Math

Keep Rank Math-specific logic in one small class, for example `RankMathAdapter.php`.

On a disposable draft in staging or a test WordPress installation:

1. Confirm the installed Rank Math version.
2. Save SEO title, description and focus keyword once through the normal editor.
3. Confirm the values Rank Math actually stores and renders.
4. Implement the same verified behavior in the adapter.

If Rank Math has no suitable public write API, using the verified metadata fields from the installed version is acceptable for this private site-specific plugin. Keep those details isolated in the adapter so they can be changed later in one place.

Do not turn this into a broad SEO migration or compatibility project. Separately check two or three existing posts after the switch from The SEO Framework to Rank Math for missing or duplicate title, description, canonical and social tags. This one-time check is not a release blocker for the Content Studio plugin unless the new drafts are affected.

### Polylang

- If Polylang is active, set the post language to `tr` using its supported function.
- Read the language back once and verify the expected `/tr/` permalink.
- Translation pairing is not required in v1.
- If Polylang is unavailable, show a warning and still create the draft unless doing so would place the post in the wrong site section.

### Taxonomies

- Resolve categories and `content_cluster` by slug.
- Do not create missing categories or clusters automatically.
- Missing tags may be created only after the preview clearly shows them.
- Do not hardcode numeric term IDs.

---

## 8. Minimum implementation

Prefer a small structure such as:

```text
co-entrepreneur-content-studio/
├── co-entrepreneur-content-studio.php
├── includes/
│   ├── class-import-screen.php
│   ├── class-package-validator.php
│   ├── class-draft-writer.php
│   ├── class-rank-math-adapter.php
│   └── class-polylang-adapter.php
├── samples/
│   ├── valid-post.json
│   └── invalid-post.json
├── README.md
└── uninstall.php
```

Use WordPress core APIs and a server-rendered admin form.

Do not add unless genuinely required:

- custom database tables;
- React, Node or npm;
- Composer runtime dependencies;
- Docker or `wp-env` as a user requirement;
- CI/CD workflows;
- PHPStan or a large testing stack;
- custom REST publishing endpoints;
- background jobs or cron;
- custom audit/event logs;
- link crawlers or remote URL checks;
- a rollback/patch engine;
- a separate content queue;
- complex roles or custom capabilities;
- automated changes to published posts.

Normal WordPress administrator permission, nonces, input sanitization and output escaping are still required. These are small baseline safeguards, not an enterprise security program.

---

## 9. Lightweight build plan

### Phase 1 — Working plugin

Build the complete lean workflow:

- plugin scaffold;
- one import screen;
- JSON parsing and preview;
- basic blocking validation and warnings;
- draft creation;
- duplicate detection using post metadata;
- categories, tags and `content_cluster`;
- Rank Math adapter;
- Polylang adapter;
- internal-link summary;
- valid and invalid sample packages;
- short installation and usage README.

### Phase 2 — One smoke-test and polish pass

Install the ZIP on a staging or disposable WordPress site and test:

1. one valid Turkish cluster article;
2. one package containing placeholders and `<h1>`;
3. re-import of the same `content_id`;
4. one draft with Rank Math and Polylang fields;
5. plugin deactivation without content loss.

Fix only issues that affect this workflow. Then produce the installable ZIP.

No full production clone, sanitized database, Docker environment or exhaustive automated test suite is required.

---

## 10. V1 acceptance criteria

V1 is done when:

1. A non-technical administrator can paste a valid package and create a WordPress draft.
2. Title, slug, body, excerpt, category, tags and cluster are correct.
3. Rank Math receives the SEO title and meta description.
4. Polylang assigns Turkish and the expected permalink is shown.
5. Required internal links are visible in the article and summarized in the preview.
6. Obvious placeholders, active HTML and `<h1>` block draft creation.
7. Re-importing the same `content_id` does not create a second post accidentally.
8. The plugin never publishes, schedules or deletes content.
9. Existing published posts are not automatically rewritten.
10. Deactivating the plugin leaves all created drafts intact.

---

## 11. Explicitly deferred

- Automatic reciprocal-link insertion into published posts
- Content generation or model APIs inside WordPress
- Google Sheets synchronization
- Image generation
- Link-health monitoring
- Search Console integration
- English content and translation pairing
- Analytics dashboards
- Custom schema generation
- Social-metadata overrides
- Complex SEO migration tooling
- Enterprise audit, rollback or approval workflows

Add a deferred item only after the basic plugin has been used and the missing feature is proven to save meaningful time.

---

## 12. Prompt to revise the current Codex plan

Use Plan mode and send:

```text
The current Phase 0 plan is not approved because it over-engineers a low-volume internal blog-post helper.

Read the updated Codex_Handoff_CoEntrepreneur_Content_Studio.md in full. Treat it as the new source of truth.

Replace the previous plan with a concise lean implementation plan. Do not edit files yet.

Required direction:
- one WordPress-native import screen;
- paste/upload JSON, preview, create draft;
- WordPress Posts > Drafts is the queue;
- post meta for content_id/idempotency; no custom database tables;
- no custom event/audit system;
- no automatic edits to published posts in v1;
- no rollback/patch engine;
- no Docker, Node, npm, CI or large test stack as project requirements;
- no exhaustive SEO migration audit;
- no remote link checker;
- Rank Math and Polylang integrations kept in small adapter classes;
- always draft, never publish, schedule or delete;
- one staging/disposable-site smoke test and a short manual checklist;
- target the smallest maintainable plugin that saves time for 8–10 posts per month.

Produce:
1. the proposed minimal file structure;
2. the exact Phase 1 implementation scope;
3. a short manual smoke-test checklist;
4. only the genuinely blocking questions.

Keep the response concise and stop for approval before implementation.
```

