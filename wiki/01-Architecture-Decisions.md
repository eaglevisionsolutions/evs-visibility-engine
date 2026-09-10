# Architecture Decisions — Visibility Engine

Log new decisions at the top, dated.

## 2026-09-10 — Deploy target: self-managed hosting, not Coolify
Reversed the earlier "Coolify, same as StrikeCircle" call. Steve will
deploy this one himself on cPanel or CyberPanel instead — no PaaS. Removed
Docker/Coolify assumptions from CLAUDE.md and env defaults (`DB_HOST`
default changed from the Docker-compose service name `db` to `localhost`).
No app-code impact: `app/config/env.php`'s loader and PDO `Database`
factory were already environment-agnostic, this only touched defaults and
docs.

## 2026-09-10 — Competitor data: no SERP scraping, no DataForSEO
Researched how DataForSEO sources data: proxy-rotated scraping of live
Google/Bing SERPs, parsed into structured JSON. This violates Google's ToS
and carries real legal exposure — Google filed a DMCA suit against SerpAPI
in Dec 2025 for exactly this pattern (OWASP classifies automated SERP
scraping as abuse pattern OAT-011). Decision: don't build a scraper, don't
pay for one by default. `CompetitorDataProvider` interface with a free/legal
default (`SitemapCrawlProvider`: crawls competitor sitemap.xml + on-page
titles/H1s/meta, respecting robots.txt — gives topic-coverage gaps, not
ranking position) plus Google Ads Keyword Planner API for real search
volume. A paid SERP-API provider stays possible later as an opt-in,
per-account upgrade, never a core dependency.

## 2026-09-10 — Naming deferred
Considered Findable, Visiby, RankSignal, Surfaced — all four already belong
to direct competitors in the AI-visibility space (Findable.ai, Visby.ai,
Surfaced, RankSignal.ai). Decision: don't lock a public brand name yet, it
doesn't block build. Using `visibility-engine` as internal codename. Revisit
before external launch — do a real domain + trademark check then.

## 2026-09-10 — Stack: PHP over Base44/Next.js
Chose PHP 8.3 OOP/MVC, no framework/ORM — matches [[StrikeCircle]]
conventions, self-hosted on existing VPS, no new platform dependency.

## 2026-09-10 — Billing: fresh Stripe, standalone
Not wiring into EspoCRM. Own `accounts`/`subscriptions` tables, Stripe
Checkout + webhooks. Reuses the "plan_key as source of truth" pattern from
the [[Base44 App Standard]] billing contract, Stripe-only (no multi-provider
abstraction needed since this is web-only, not mobile).

## 2026-09-10 — MVP scope: all 4 pillars from the start
Full plan (Find/Write/Publish/Measure/AEO/Competitor-gap) scoped from day
one, built in phased release branches rather than cutting pillars from v1.
See [[02-Phase-Roadmap]].

## 2026-09-10 — Multi-tenancy: accounts → sites
Accounts own billing; sites are 1-to-many under an account (Agency plan =
multiple client domains under one subscription). Tenant isolation enforced
in BaseModel, not per-controller.
