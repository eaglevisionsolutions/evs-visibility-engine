# Phase Roadmap → Release Branches

## Phase 1 — Core (branch: `main`, tag `v0.1`)
- Project skeleton: folder structure, env config loader, PDO `BaseModel`,
  router, JWT auth (login/refresh), migrations runner.
- `accounts`, `users`, `sites` tables + tenant isolation enforced in
  `BaseModel`.
- Stripe billing skeleton: `plans` table, checkout, webhook handler, account
  subscription status.
- `jobs` table + `workers/worker.php` polling loop with a no-op test job type.
- **Done when:** can register an account, add a site, subscribe to a plan via
  Stripe test mode, and the worker picks up and completes a dummy job.

## Phase 2 — Find + Write + Publish (branch: `release/v2-content-engine`)
- Google OAuth per site, GSC sync job.
- `content_queue`, `keyword_map` tables.
- Claude API content generation job, status machine.
- `PublisherInterface` + `WordPressPublisher`.
- Slack/email notification hooks for ready-for-review and published states.
- **Done when:** a queued keyword becomes a published WP draft end-to-end
  through the job pipeline, no manual DB edits required.

## Phase 3 — Measure (branch: `release/v3-attribution`)
- GA4 API sync job, join sessions/conversions to `content_queue.slug`.
- Scoring feedback: top performers' topics feed into Find's next pass.
- **Done when:** a published post's 30-day sessions/conversions show up
  against its `content_queue` row automatically.

## Phase 4 — AEO / LLM Visibility (branch: `release/v4-aeo`)
- `aeo_prompts`, `aeo_visibility_log` tables.
- Job queries ChatGPT, Gemini, Claude APIs, parses for brand mention +
  cited domains.
- AEO gaps auto-append to `content_queue` with `source = aeo_gap`.
- **Done when:** a prompt run produces a visibility log row and, if the
  brand isn't mentioned but a competitor is, a new content_queue task.

## Phase 5 — Competitor Gap Analysis (branch: `release/v5-competitor-gap`)
- No SERP scraping (DataForSEO-style vendors scrape live Google results via
  rotating proxies — this violates Google's ToS and carries real legal
  exposure; see Google's Dec 2025 DMCA suit against SerpAPI). We don't do
  this.
- `CompetitorDataProvider` interface. Default implementation:
  `SitemapCrawlProvider` — fetches a competitor's `sitemap.xml` (respecting
  `robots.txt`), pulls titles/H1s/meta/headings from public pages. Free,
  legal, no proxies. Gives topic-coverage gaps, not ranking position.
- Google Ads Keyword Planner API for real search volume/CPC — free with a
  Google Ads account, official first-party access.
- **Done when:** a competitor gap keyword (topic they cover, we don't)
  lands in `content_queue` with `source = competitor_gap`, with Keyword
  Planner volume/CPC attached.
- A paid SERP-API-backed `CompetitorDataProvider` can be added later as an
  opt-in per-account upgrade for exact ranking data — never a hard
  dependency of the core pipeline.

## Phase 6+ — Productization (branch: `release/v6-agency-features`)
- White-label reporting, multi-user seats per account, additional
  `PublisherInterface` adapters (Shopify, Webflow), public brand decision
  and rename pass.
