# Co-Entrepreneur Content Studio — Codex Build Handoff

**Project:** Co-Entrepreneur WordPress content operations tool  
**Product owner:** Cüneyt  
**Website:** https://co-entrepreneur.net  
**Primary language:** Turkish  
**Status:** Approved concept; discovery and implementation not yet started  
**Last updated:** 2026-08-19

**Environment update:** The SEO Framework was removed and **Rank Math SEO** was activated on 2026-08-19. All SEO integration and testing below must target Rank Math. Discovery must still verify the installed Rank Math edition, version, modules and migration state.

---

## 1. Executive decision

Build a small private WordPress plugin called **Co-Entrepreneur Content Studio**.

The plugin will turn a structured publication package prepared with ChatGPT into a validated WordPress draft. It will also manage the site's pillar/cluster metadata and support safe, reviewable internal-link updates.

This replaces the need for n8n in v1. It must run inside the existing WordPress installation and use WordPress's existing authentication and permissions. It must not publish posts live automatically.

### Product principle

> Automate repetitive publishing operations, not editorial judgment.

Content strategy, research, writing, factual review and final publication remain human-led. The plugin handles validation, field mapping, taxonomy assignment, draft creation and controlled internal-link operations.

---

## 2. User and workflow

The tool is for a two-person team with a target volume of approximately 8–10 Turkish posts per month.

### Intended workflow

1. A pillar or cluster article is researched and finalized with ChatGPT.
2. ChatGPT outputs one structured publication package.
3. The user opens **WordPress Admin → Co-Entrepreneur → Content Studio**.
4. The user uploads the package or pastes its contents.
5. The plugin validates the package and shows blocking errors, warnings and proposed internal-link actions.
6. The user approves the valid package.
7. The plugin creates or updates a WordPress post with `status=draft`.
8. The user opens the native WordPress preview, performs the final review and manually publishes.
9. After publication, the user can approve suggested reciprocal links from the pillar or existing cluster posts.

The normal publishing flow should require no manual copying into separate SEO, category, tag, language or cluster fields.

---

## 3. Confirmed existing-site context

The following was observed through the public site and WordPress REST API on 2026-08-19. Codex must verify these facts against the actual staging or development environment before relying on them.

- WordPress REST API is enabled.
- The site currently reports WordPress 6.9.5 in frontend assets.
- The product owner confirms that **The SEO Framework was removed and Rank Math SEO was activated on 2026-08-19**. Codex must verify the installed Rank Math edition, version and active modules in local/staging before implementing the adapter.
- The site uses **Polylang** and Turkish posts use `/tr/` URLs.
- A custom taxonomy named `content_cluster` is exposed through the REST API.
- Existing Turkish editorial categories include:
  - `rehberler` — Rehber
  - `makaleler` — Makaleler
  - `haberler` — Haberler
- An existing content-cluster term is:
  - `netherlands-eu-expansion-base` — Netherlands as EU Expansion Base
- The published pillar post currently has WordPress post ID `550` and slug:
  - `hollandada-sirket-kurma-turk-sirketler-icin-avrupa-operasyonu-rehberi`
- Previously observed standard public post responses exposed only `footnotes` under `meta`. Do not assume Rank Math write fields are exposed through `wp/v2/posts`; verify authenticated save behavior locally or in staging.
- Polylang language and translation fields are not present in the standard public `wp/v2/posts` response.

### Existing issues that are not to be silently changed

These are separate remediation items. Codex may document and propose fixes, but must not modify production without explicit approval.

1. Before the SEO-plugin change, the pillar page output duplicate meta-description and Open Graph layers: one custom/manual layer and one from The SEO Framework. Re-audit after Rank Math activation; do not assume removal of The SEO Framework resolved the custom/manual layer or migrated metadata correctly.
2. The page outputs a `meta keywords` tag, which is unnecessary.
3. A publication checklist and placeholders were shipped inside an HTML comment in the pillar body.
4. A malformed tag exists: `pazara giriş stratejisiHollanda’da şirket kurma`.
5. The visible author card and schema use a generic organization-style identity instead of a clearly named expert author.
6. The Turkish post template contains an English “Back to Blog” link pointing to `/blog/`.

Create a separate audit report for these items. Do not couple production cleanup to the plugin's first installation.

---

## 4. V1 scope

### 4.1 WordPress admin interface

Add a top-level or clearly discoverable admin menu:

**Co-Entrepreneur → Content Studio**

V1 screens:

1. **Import Draft**
   - Upload a `.json` package.
   - Paste JSON as an alternative.
   - Show parsed values before any write.
   - Run validation without creating a post.

2. **Content Queue**
   - List packages/imports and linked WordPress posts.
   - Show status: imported, blocked, ready, draft created, published, link updates pending.
   - Link to native WordPress edit and preview screens.

3. **Internal Links**
   - Show outgoing links in the new article.
   - Show proposed reciprocal links from the pillar and existing cluster posts.
   - Require explicit approval for every update to an existing post.
   - Show the exact source post, target URL, anchor text and proposed placement before applying.

4. **Settings**
   - Approved external domains.
   - Approved categories, tags and content clusters.
   - Default author.
   - Default language (`tr`).
   - CTA configurations.
   - Validation thresholds.

Prefer a WordPress-native admin UI. Do not build a React SPA unless discovery shows a clear need.

### 4.2 Draft creation

The plugin must create or update:

- title
- slug
- content HTML
- excerpt
- author
- category
- tags
- `content_cluster`
- featured image ID when supplied
- media alt text when supplied
- language via Polylang
- SEO title via Rank Math
- meta description via Rank Math
- optional Rank Math focus keyword(s), when explicitly supplied in the package
- social title/description/image only if explicitly supplied and supported safely

Canonical URL, robots directives and schema settings should normally inherit the approved Rank Math/site defaults. Only write per-post overrides when they are explicitly represented in a future schema revision and have been reviewed in the import preview.

Every initial write must force `post_status = draft`, regardless of the input package. There must be no API route or interface action in v1 that publishes a post live.

### 4.3 Idempotency

Every package must include a stable `content_id`, such as `ce-c1-01-bv-branch-distributor`.

Store this identifier in namespaced post metadata, for example `_ce_content_id`, using an implementation compatible with WordPress APIs.

If the same package is imported again:

- do not create a duplicate post;
- show the existing linked post;
- display a change summary;
- require confirmation before updating the draft;
- never overwrite a published post as part of the normal import flow.

Published-post changes must use the dedicated internal-link/update flow and explicit approval.

---

## 5. Publication package contract

The publication package is the interface between ChatGPT and the plugin. Treat it as a versioned contract.

### 5.1 Example JSON

```json
{
  "schema_version": "1.0",
  "content_id": "ce-c1-01-bv-branch-distributor",
  "content_type": "cluster_post",
  "language": "tr",
  "title": "Hollanda’ya Giriş Modeli: BV, Şube, Distribütör veya Acente?",
  "seo_title": "Hollanda’da BV mi, Şube mi, Distribütör mü?",
  "meta_description": "Türk şirketleri için Hollanda’ya giriş modellerini; kontrol, maliyet, müşteri ilişkisi ve operasyon ihtiyacı açısından karşılaştırın.",
  "focus_keywords": ["Hollanda'da şirket kurma modelleri"],
  "slug": "hollandada-bv-sube-distributor-acente",
  "excerpt": "Hollanda pazarına BV, şube, distribütör veya acente modeliyle girmenin farklarını ve doğru modeli nasıl seçeceğinizi değerlendirin.",
  "author": {
    "mode": "login",
    "value": "REPLACE_DURING_SETUP"
  },
  "category_slugs": ["rehberler"],
  "tag_slugs": ["hollanda-bv", "avrupa-pazara-giris"],
  "content_cluster_slugs": ["netherlands-eu-expansion-base"],
  "pillar": {
    "content_id": "ce-p1-netherlands-eu-operation",
    "url": "https://co-entrepreneur.net/tr/hollandada-sirket-kurma-turk-sirketler-icin-avrupa-operasyonu-rehberi/"
  },
  "cta": {
    "type": "blueprint",
    "label": "Avrupa’ya Giriş Stratejinizi Netleştirin",
    "url": "REPLACE_DURING_SETUP"
  },
  "body_html": "<p>...</p>",
  "featured_image": null,
  "approved_external_domains": [
    "business.gov.nl",
    "kvk.nl",
    "eur-lex.europa.eu"
  ],
  "internal_links": [
    {
      "direction": "outgoing",
      "target_url": "https://co-entrepreneur.net/tr/hollandada-sirket-kurma-turk-sirketler-icin-avrupa-operasyonu-rehberi/",
      "anchor": "Hollanda üzerinden Avrupa operasyonu kurma rehberi",
      "required": true
    }
  ],
  "reciprocal_link_suggestions": [
    {
      "source_post_id": 550,
      "target_content_id": "ce-c1-01-bv-branch-distributor",
      "anchor": "BV, şube, distribütör veya acente modelinin karşılaştırması",
      "placement_hint": "Operasyon modeli bölümünün sonu"
    }
  ],
  "review": {
    "editorial_approved": true,
    "regulatory_review_required": false,
    "regulatory_review_complete": false,
    "notes": []
  }
}
```

### 5.2 Contract rules

- JSON Schema must be included in the repository.
- Reject unsupported future `schema_version` values.
- Use slugs rather than numeric taxonomy IDs in packages.
- Do not automatically create unknown categories, tags or clusters in v1.
- Unknown taxonomy terms are blocking validation errors.
- `focus_keywords`, when supplied, must be a short reviewed array; the plugin must not generate keyword stuffing or treat a Rank Math score as a publication gate.
- `body_html` must not contain an `<h1>` because the theme renders the post title.
- The package must not contain credentials, API keys or private client information.

---

## 6. Validation requirements

Classify results as **blocking error**, **warning** or **information**.

### Blocking errors

- Missing `content_id`, title, slug, excerpt, body, category, cluster, author or language.
- Unsupported schema version.
- A different post already uses the requested slug.
- An `<h1>` is present in `body_html`.
- Placeholder patterns are present, including:
  - `INTERNAL:`
  - `href="#"`
  - `[SOYADI]`
  - `REPLACE_DURING_SETUP` after setup is complete
  - “WordPress'e yapıştırmayın” or equivalent publishing instructions
- A required internal link is missing from the body.
- An external link points to a domain outside the approved whitelist.
- A category, tag or cluster is not approved.
- Regulatory review is required but not complete.
- HTML contains `<script>`, executable event attributes, iframe embeds or other disallowed active content.

### Warnings

- Meta description or title may be too wide/long. Do not treat character count as a ranking rule.
- Duplicate or overused internal-link anchor text.
- Too many links to one target.
- Broken heading hierarchy.
- Missing featured image or alt text.
- External URL returns an error or redirects unexpectedly.
- Internal URL is outside the expected language path.
- Post contains inline styles rather than approved CSS classes.

### Required HTML behavior

Preserve approved Co-Entrepreneur classes such as CTA and field-note classes. Do not silently strip tables, lists, links or approved semantic markup. Sanitize with an explicit allowlist and test HTML round-tripping.

Do not add FAQ or HowTo schema merely because a section uses questions or steps. Structured data is owned by Rank Math and the active theme unless a future requirement explicitly changes this. The plugin must not add a second Article, FAQ or HowTo schema layer.

---

## 7. Internal-link design

Internal linking must be deterministic and reviewable, not a blind keyword-replacement system.

### New post to existing content

The package specifies required outgoing links. Validation confirms that each URL and anchor exists in `body_html`.

### Existing content to new post

V1 must generate and display proposals. It must not silently rewrite published HTML.

When a proposal is approved:

1. Re-fetch the current source post.
2. Confirm that it has not changed since the proposal was generated.
3. Create or preserve a WordPress revision before mutation.
4. Apply the smallest possible change.
5. Revalidate the resulting HTML.
6. Record the previous and new value or a reversible patch in the audit log.
7. Never change an existing post's slug, title, metadata or status during a link-only update.

### Managed related-content block

The plugin may maintain a deterministic related-content section bounded by stable HTML comments, for example:

```html
<!-- ce-related-content:start -->
...
<!-- ce-related-content:end -->
```

Only the content inside these markers may be replaced automatically after explicit approval. Contextual links elsewhere in an article require exact placement review.

---

## 8. Integration requirements

### 8.1 Rank Math SEO

Do not guess metadata keys or depend directly on undocumented database fields.

During discovery:

1. Determine the installed Rank Math edition, version, operating mode and enabled modules.
2. Check whether metadata was imported from The SEO Framework and identify any stale custom/manual SEO output without changing production.
3. Inspect Rank Math's official/current developer hooks and the actual editor/save behavior in a disposable local or staging environment.
4. Implement a narrow `SeoAdapter` interface with a `RankMathAdapter`; keep plugin-specific storage details isolated there.
5. Verify the rendered `<head>` output and WordPress editor values, not only database writes.
6. Confirm exactly one effective title, description, canonical, robots, Open Graph and Twitter-card layer.
7. Verify Rank Math sitemap inclusion and schema output for a representative draft preview and published staging post.

Rank Math documents a `rankmath/v1/getHead` endpoint for retrieving generated head markup when its Headless CMS Support setting is enabled. This project is not headless, so do not enable that setting merely for the plugin. It may be used as an optional verification aid only if already enabled or explicitly approved.

If a stable supported write path cannot be established, stop and present options before implementing a brittle workaround. Do not make the Rank Math numerical SEO score a blocking acceptance criterion; validate actual fields and rendered output instead.

### 8.2 Polylang

Create a `LanguageAdapter` that:

- detects whether Polylang is active;
- sets the post language explicitly to Turkish;
- does not assume that the URL path alone establishes language;
- leaves translation pairing empty unless a translation is explicitly supplied;
- fails safely with a blocking error if language assignment cannot be verified.

Use supported Polylang functions/APIs where available and guard all calls with compatibility checks.

### 8.3 Taxonomies

- Resolve taxonomy terms by slug.
- Support core categories and tags plus the `content_cluster` taxonomy.
- Do not hardcode environment-specific numeric IDs.
- Do not create arbitrary AI-generated tags.
- Provide an admin allowlist and a future taxonomy-cleanup report.

---

## 9. Security and safety requirements

- Use WordPress capability checks for every action.
- Define a dedicated capability such as `manage_ce_content`; assign it only to approved roles during setup.
- Use WordPress nonces for admin forms and AJAX/REST actions.
- Sanitize inputs and escape outputs according to context.
- Do not store WordPress passwords or third-party API keys in publication packages.
- V1 must not need an externally reachable custom publishing endpoint.
- V1 must not call an AI service.
- V1 must not support live auto-publishing.
- V1 must not delete posts, taxonomies, media or revisions.
- Use namespaced functions/classes, options, metadata and database structures.
- Uninstall must not delete content or settings without a separate explicit destructive action.
- Log actor, time, action, content ID, post ID and outcome without logging secrets or full sensitive payloads.
- Use current WordPress coding and security standards.

---

## 10. Suggested code architecture

The exact structure may change after discovery, but keep domain logic separated from WordPress UI wiring.

```text
co-entrepreneur-content-studio/
├── co-entrepreneur-content-studio.php
├── readme.txt
├── uninstall.php
├── src/
│   ├── Admin/
│   ├── Contracts/
│   ├── Import/
│   ├── Validation/
│   ├── Publishing/
│   ├── Integrations/
│   │   ├── RankMathAdapter.php
│   │   └── PolylangAdapter.php
│   ├── InternalLinks/
│   └── Audit/
├── assets/
├── schemas/
│   └── publication-package-v1.schema.json
├── samples/
│   ├── valid-cluster-post.json
│   └── invalid-placeholders.json
├── tests/
├── docs/
│   ├── architecture.md
│   ├── setup.md
│   ├── test-report.md
│   └── production-audit.md
├── AGENTS.md
└── README.md
```

Avoid adding production dependencies without explaining why they are needed. Prefer WordPress core APIs and a simple server-rendered admin UI.

---

## 11. Development phases

### Phase 0 — Discovery, no production writes

- Establish a Git repository.
- Inspect any supplied site/plugin/theme code and development environment.
- Determine PHP, WordPress, Rank Math edition/version/modules and Polylang version.
- Audit whether Rank Math migration left duplicate or stale SEO output from the previous setup.
- Confirm how `content_cluster` is registered.
- Document current post/meta/taxonomy behavior.
- Produce an implementation plan and risk register.
- Identify what requires staging access or user confirmation.

**Exit criterion:** Product owner approves the implementation plan. No production data has been changed.

### Phase 1 — Safe plugin skeleton and package validation

- Create the plugin scaffold.
- Add capability and admin screens.
- Implement JSON Schema and validation.
- Add dry-run import preview.
- Add unit tests for package validation.
- No WordPress post creation until validation tests pass.

### Phase 2 — Draft creation and integrations

- Create/update draft posts idempotently.
- Integrate taxonomies.
- Integrate Rank Math through the approved adapter.
- Integrate Polylang through the approved adapter.
- Add audit logging.
- Test only in staging or a disposable local WordPress environment.

### Phase 3 — Internal-link workflow

- Build link inventory and proposal UI.
- Validate target URLs and anchors.
- Implement approved managed-block updates.
- Implement reversible, exact contextual-link updates.
- Test stale-content/conflict handling.

### Phase 4 — Release candidate

- Run automated tests and coding standards.
- Run manual end-to-end tests with at least four representative packages.
- Verify rendered HTML and metadata.
- Produce installable ZIP, setup guide, test report and rollback guide.
- Request product-owner approval before any production installation.

---

## 12. Acceptance criteria

V1 is complete only when all of the following are demonstrated:

1. A valid Turkish package creates exactly one WordPress draft.
2. The post title, slug, excerpt, author, category, tags and cluster are correct.
3. Polylang reports the post language as Turkish and the expected `/tr/` permalink is generated.
4. The SEO title, description and optional reviewed focus keyword(s) are saved through Rank Math and appear correctly in the editor/rendered output as applicable.
5. The generated page has one effective description, canonical and Open Graph layer attributable to the intended stack.
6. A package containing placeholders or an `<h1>` is blocked before any write.
7. Unknown taxonomies are blocked rather than automatically created.
8. Re-importing the same `content_id` does not create a duplicate.
9. Supplying `status=publish` or equivalent input cannot produce a live post.
10. The plugin does not modify a published post without a specific preview and approval action.
11. An approved internal-link update changes only the approved location and can be reversed using the recorded revision/patch.
12. Deactivating the plugin does not remove or damage posts.
13. Tests, linting/coding standards and the documented manual test plan pass.
14. A non-technical user can install the ZIP, configure the plugin and create a draft by following the README.

---

## 13. Explicit non-goals for v1

- AI research or article generation inside WordPress
- OpenRouter or other model APIs
- Google Sheets synchronization
- Automated image generation
- Automatic external-link discovery
- Automatic live publishing or scheduling
- Autonomous rewriting of existing posts
- English content and translation-pair creation
- Search Console submission or indexing requests
- Analytics dashboards
- Replacing Rank Math or Polylang
- Broad taxonomy redesign

These may be evaluated only after v1 is stable and used for several real posts.

---

## 14. Required deliverables

- Version-controlled source repository
- Root `AGENTS.md`
- Installable WordPress plugin ZIP
- Publication-package JSON Schema
- Valid and invalid example packages
- Automated tests
- `README.md` for a non-technical installer
- Architecture and integration notes
- Staging test report
- Production-site audit report
- Rollback and recovery instructions
- Known limitations and future backlog

Do not deliver only a code dump or only a ZIP. The source, tests and operating documentation are part of the product.

---

## 15. Repository-level AGENTS.md guidance

Codex should create a concise root `AGENTS.md` from the rules below after the repository is initialized:

- Read this handoff before planning or editing.
- Never make production WordPress writes without explicit approval.
- Never introduce live auto-publishing.
- Keep Rank Math and Polylang integrations behind adapters.
- Do not guess plugin metadata keys or APIs; verify them.
- Use taxonomy slugs, not environment-specific IDs.
- Preserve approved article HTML and classes.
- Add or update tests for every behavioral change.
- Run the documented test and coding-standard commands before declaring completion.
- Explain new dependencies before adding them.
- Keep credentials out of code, fixtures, logs and documentation.
- Do not broaden v1 scope without product-owner approval.

Keep `AGENTS.md` short. Link back to this handoff and technical docs rather than duplicating the entire specification.

---

## 16. Exact first prompt to give Codex

Start Codex in the Git repository created specifically for this plugin. Add this handoff file to the repository root, then use Plan mode with the following prompt. Plan mode is discovery-only: it must not edit files in this first turn.

```text
Read Codex_Handoff_CoEntrepreneur_Content_Studio.md in full.

We are building the private WordPress plugin described there. Begin with Phase 0 only. Do not write production integration code. Do not authenticate to, administer or modify the live WordPress site. Public read-only inspection is allowed only when needed to verify non-sensitive output.

Your task in this turn:
1. Inspect the repository and any development-environment files available to you.
2. Identify missing information that materially affects architecture or safe implementation.
3. Propose the smallest local or staging WordPress development environment needed to verify Rank Math, Polylang and the content_cluster taxonomy.
4. Include a read-only migration audit plan for possible stale or duplicate SEO output left after replacing The SEO Framework with Rank Math.
5. Produce a phased implementation plan mapped to the handoff acceptance criteria.
6. Propose the repository structure, test strategy, coding-standard commands and rollback approach.
7. List the durable rules that should go into AGENTS.md after plan approval.
8. Stop after presenting the plan and open questions. Do not edit files and do not begin Phase 1 until I approve the plan.

Challenge assumptions where necessary. Do not guess undocumented plugin fields or APIs. Clearly separate what is confirmed, what must be verified in staging and what requires a decision from me.
```

---

## 17. Prompt for Phase 1 after plan approval

```text
The Phase 0 plan is approved, including the decisions recorded in the repository docs.

Switch from Plan mode to the normal coding mode. First create a concise root AGENTS.md containing the approved durable repository rules and linking back to the handoff. Then implement Phase 1 only: the safe plugin skeleton, WordPress-native admin interface, publication-package JSON Schema, dry-run parsing and validation, and automated validation tests.

Do not implement Rank Math writes, Polylang writes, post creation, internal-link mutations or production deployment yet. Use fixtures for the supplied valid and invalid sample packages.

Before handing back:
- run all documented tests, linting and coding-standard checks;
- review the diff for security and scope regressions;
- update README and architecture notes;
- report exactly what is complete, what remains stubbed and any decisions needed for Phase 2.
```

---

## 18. Decisions the product owner must provide during discovery

Do not request these all at once unless they are immediately blocking. Resolve them during Phase 0:

1. Is a staging copy of the WordPress site available?
2. Can the current custom theme/plugin code that registers `content_cluster` be provided?
3. What PHP version runs on the server?
4. What Rank Math edition/version/modules and Polylang version are installed?
5. During the switch to Rank Math, was metadata imported from The SEO Framework, and is there a staging environment where migration output can be checked safely?
6. Which WordPress login should be the default named author?
7. What is the canonical Blueprint CTA URL?
8. Which tags should be in the approved v1 vocabulary?
9. Should existing published posts ever be editable by the plugin, or should v1 stop at link proposals?
10. Who is allowed to use the Content Studio capability?
11. What backup/staging workflow does the hosting provider currently offer?

Never request or store production passwords in repository files or chat. Credentials, if needed later, must be entered directly in the appropriate secure environment.

---

## 19. Optional future backlog

Only evaluate after v1 has processed several real posts successfully:

- One-way Google Sheets content-map import
- Automated broken-link monitoring
- Search Console inspection queue
- Featured-image workflow
- Approved-source research pack import
- English content packages and Polylang translation pairing
- Model-assisted link-placement suggestions
- Content-refresh reminders
- Structured author-profile improvements
- Reusable ChatGPT/Codex skill for generating valid publication packages

---

## 20. Reference documentation

- WordPress REST post fields: https://developer.wordpress.org/rest-api/reference/posts/
- WordPress REST authentication: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- WordPress plugin handbook: https://developer.wordpress.org/plugins/
- Rank Math developer filters and hooks: https://rankmath.com/kb/filters-hooks-api-developer/
- Rank Math headless metadata endpoint (verification reference only): https://rankmath.com/kb/headless-cms-support/
- Codex best practices and AGENTS.md: https://learn.chatgpt.com/guides/best-practices
- Codex AGENTS.md discovery: https://learn.chatgpt.com/docs/agent-configuration/agents-md
