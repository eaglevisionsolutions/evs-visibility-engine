# Visibility Engine — Project Instructions for Claude Code

## What this is

A multi-tenant SaaS that combines SEO content automation and AEO (Answer Engine
Optimization / AI-search visibility) into one product. It replaces Opinly.ai for
Eagle Vision Solutions' internal use, will run all EVS client accounts, and will
be sold standalone to other agencies/businesses.

Working codename: `visibility-engine`. Public brand name is NOT decided yet —
do not hardcode a brand name anywhere in code, config keys, or DB names beyond
this codename. Treat "Visibility Engine" as a placeholder string pulled from a
single config constant, not something scattered through the codebase.

## Non-negotiable architecture rules

- **No framework.** Modular MVC-like structure: thin controllers, a Services
  layer, PDO-based models.
- **No ORM.** Raw PDO wrapped in a `BaseModel` (same pattern as StrikeCircle).
- **No TypeScript.** Vanilla ES6 classes only, if/when JS is needed.
- **PHP 8.3, strict types, OOP-only.** No procedural PHP, no function-soup JS.
- **PSR-4 namespacing.**
- All API routes live under `/api/v1/`.
- Auth: JWT with Bearer tokens + refresh tokens (same pattern as StrikeCircle).
- Single CSS file approach for any first-party admin UI.
- Environment config loader pattern: `app/config/env.php` loads root `.env`,
  then `.env.local` / `.env.staging` / `.env.production` based on `APP_ENV`.
  Both web entry points and CLI/migration scripts depend on this loader.
- Migrations are plain PHP files in `/migrations`, run via CLI, no migration
  framework.
- Deploy target: self-managed hosting (cPanel or CyberPanel) — the user
  deploys manually, not via a PaaS. No Docker/Coolify assumptions anywhere
  in the app (paths, env defaults, deploy scripts): it must run as plain
  PHP-FPM/Apache under a standard shared-hosting or VPS-with-control-panel
  layout. Local dev also runs without Docker — PHP 8.3 + Composer installed
  natively, `php -S` for local serving, DB host defaults to `localhost`.

## Multi-tenancy model

- `accounts` — the paying customer (an EVS-managed account or an external
  subscriber). Owns billing.
- `sites` — 1-to-many under `accounts`. An Agency-tier account manages
  multiple client domains under one subscription.
- Every other table (`competitors`, `keywords`, `content_queue`,
  `aeo_prompts`, `aeo_visibility_log`, etc.) is scoped by `site_id`, and
  `account_id` is denormalized onto them too for a single-column tenant
  isolation check.
- **Tenant isolation is enforced in `BaseModel`, not per-controller.** Every
  query builder method must require an `account_id` (or resolve it from the
  authenticated JWT) before it will run. This is the single most important
  security rule in this codebase — do not let a controller query without it.

## Background jobs (no framework = no built-in queue)

- `jobs` table: `id, type, payload JSON, status, run_after, attempts,
  last_error, created_at, updated_at`.
- A single long-running CLI worker (`workers/worker.php`) polls `jobs` on an
  interval, run via supervisor (or cron with a lock file) on the VPS.
- Job types (add as needed, keep the dispatcher a simple switch/map):
  - `crawl_competitor`
  - `sync_gsc`
  - `sync_ga4`
  - `score_content_gaps`
  - `generate_content_draft`
  - `publish_content`
  - `query_llm_visibility`
  - `sync_stripe_subscription`
- Every job handler must be idempotent — jobs can be retried.

## The four product pillars (build in this order — see Phase Roadmap)

1. **Find** — GSC + Bing Webmaster Tools data (per-site OAuth) for own
   opportunities, plus a `CompetitorDataProvider` interface for competitor
   gaps. Default provider is free/legal (sitemap + on-page crawl, see
   "Competitor data" below); a paid SERP-API provider can be swapped in
   later per-account without changing the pipeline. Gap scoring writes
   `content_queue` rows with `source = competitor_gap` or
   `source = own_opportunity`.
2. **Write** — Claude API drafts (title, meta, h1, outline, html, slug) from
   `content_queue` rows. Status machine:
   `queued → writing → ready_for_review → approved → published → error`.
3. **Publish + Measure** — `PublisherInterface` with swappable
   implementations (`WordPressPublisher` first, `ShopifyPublisher` etc.
   later — same provider-pattern instinct as the Base44 billing contract:
   one interface, swappable vendor adapters). GA4 sessions/conversions join
   back to `content_queue.slug` monthly, feeding a "what worked" signal into
   the next Find pass.
4. **AEO** — `aeo_prompts` per site (buyer-intent prompts). A job queries
   ChatGPT, Gemini, and Claude on a schedule, parses responses for brand
   mention + cited domains, logs to `aeo_visibility_log`. Gaps (competitor
   mentioned, client isn't) auto-feed into `content_queue` with
   `source = aeo_gap`. This is the pillar that goes beyond Opinly — AEO gaps
   land in the *same* backlog as SEO gaps, not a separate report.

## Competitor data (no scraping — legal/free by default)

DataForSEO and similar vendors get rankings by proxy-rotated scraping of
live Google SERPs — this violates Google's ToS and carries real legal
exposure (see Google's Dec 2025 DMCA suit against SerpAPI). We are not
building a scraper. Default competitor data comes from sources that are
explicitly permitted:

- **Sitemap/on-page crawler** — fetch a competitor's own `sitemap.xml`
  (respecting `robots.txt`), pull titles/H1s/meta/headings from their public
  pages. This is ordinary page fetching, not SERP scraping. Gives topic
  coverage gaps (they have a page on X, we don't) — not ranking position.
- **Google Ads Keyword Planner API** — free with a Google Ads account, real
  search volume + CPC. Replaces DataForSEO's volume estimates.

`CompetitorDataProvider` is an interface (same provider-adapter pattern as
billing/publishing). Ship `SitemapCrawlProvider` as the only implementation
in Phase 5. A paid SERP-API-backed provider can be added later as an
opt-in, per-account upgrade for accounts that want exact ranking data — it
is never a hard dependency of the core pipeline.

## Billing contract (Stripe, web-only — simpler than the Base44 multi-provider

pattern, but keep the same discipline)

- `plan_key` is the single source of truth everywhere in app logic. A
  `plans` config/table maps `plan_key → stripe_price_id`. Never reference a
  Stripe price ID directly outside the billing provider adapter.
- Implement as an interface even though there's only one provider right now:
  `getAvailablePlans()`, `subscribe(account_id, plan_key)`,
  `getSubscriptionStatus(account_id)`, `cancelOrManageSubscription(account_id)`
  (Stripe billing portal deep link), `handleWebhookEvents()`.
- Metering dimensions per plan: number of `sites`, competitor slots, keyword
  slots, AEO prompt slots. Enforce limits in the Services layer, not just UI.

## Release / branch strategy

- `main` — always the current stable release, deployable.
- Initial build happens directly on `main` (no branch) until Phase 1 ships.
- After that, every new feature set is built on a `release/vX-shortname`
  branch, PR'd back into `main` when it's stable and demoed. Tag `main` with
  the version (`vX.Y`) on merge.
- See `docs/phase-roadmap.md` for what each release contains.

## Definition of done for any phase

- Migrations run clean on a fresh DB.
- Tenant isolation test: two accounts, verify account A cannot read/write
  account B's data through any endpoint.
- Job handlers tested for idempotency (run twice, no duplicate side effects).
- No hardcoded brand name, vendor name, or price ID outside their config/
  provider layer.
