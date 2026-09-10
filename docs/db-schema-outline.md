# DB Schema Outline (starting point — refine during Phase 1 migrations)

## Core / tenancy
- `accounts` (id, name, plan_key, stripe_customer_id, created_at)
- `users` (id, account_id, email, password_hash, role, created_at)
- `sites` (id, account_id, domain, wp_url, wp_username, wp_app_password,
  gsc_property, gsc_refresh_token, gsc_connected_at, ga4_property_id,
  created_at). `wp_app_password` and `gsc_refresh_token` are encrypted at
  rest (`App\Support\Crypto`, `APP_KEY` env) — added in Phase 2.

## Billing
- `plans` (plan_key PK, stripe_price_id, site_limit, competitor_limit,
  keyword_limit, aeo_prompt_limit)
- `subscriptions` (id, account_id, plan_key, status, current_period_end)

## Jobs
- `jobs` (id, type, payload JSON, status, run_after, attempts, last_error,
  created_at, updated_at)

## Find / Write / Publish
- `keyword_map` (id, site_id, keyword, mapped_url, source)
- `content_queue` (id, site_id, keyword, status, source, seo_title,
  meta_desc, h1, outline JSON, content_html, slug, published_url,
  sessions_30d, conversions_30d, created_at, updated_at)
- `competitors` (id, site_id, domain)

## Competitor data
- `competitors` table (above) gains: `sitemap_url`, `last_crawled_at`
- `competitor_pages` (id, competitor_id, url, title, h1, meta_desc,
  headings JSON, crawled_at) — output of `SitemapCrawlProvider`, diffed
  against `keyword_map`/`content_queue` topics to produce gap rows

## AEO
- `aeo_prompts` (id, site_id, prompt_text, active)
- `aeo_visibility_log` (id, site_id, prompt_id, model, mentioned BOOLEAN,
  cited_domains JSON, raw_response, run_at)

Notes:
- Every non-`accounts` table carries `account_id` denormalized alongside
  `site_id` for the single-column tenant check in `BaseModel`.
- `content_queue.status` enum:
  queued, writing, ready_for_review, approved, published, error.
