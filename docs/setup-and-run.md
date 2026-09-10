# Setup & Run

Nothing in this repo has been executed yet — every migration, service, and
test has been written carefully but never run against a real PHP/MySQL
environment. Treat this as the checklist for the first real test pass, in
order. Each section says what to do and, where it matters, what could go
wrong.

Covers Phases 1–2 (`main` + `release/v2-content-engine`). See
[phase-roadmap.md](phase-roadmap.md) for what's not built yet.

## 1. Prerequisites

- **PHP 8.3** with extensions: `pdo_mysql`, `curl`, `sodium`, `json`
  (all bundled/standard — nothing exotic, but confirm on whatever host
  you're testing on)
- **Composer**
- **MySQL 8** (not MariaDB — see the architecture decision log; the schema
  leans on native `JSON` columns)

```bash
php -v          # expect 8.3.x
php -m | grep -E 'pdo_mysql|curl|sodium'
composer -V
```

## 2. Install dependencies

```bash
cd evs-visibility-engine
composer install
```

This pulls `stripe/stripe-php` and `phpunit/phpunit`. If Composer errors on
`ext-sodium` or `ext-curl`, that extension is missing from your PHP build —
install it before continuing; the app will not run without it (`Crypto`
and `HttpClient` both hard-depend on them).

## 3. Database

```bash
mysql -u root -p -e "CREATE DATABASE visibility_engine CHARACTER SET utf8mb4"
```

## 4. Environment file

```bash
cp .env.example .env
```

Fill in every value below before doing anything else — the app throws on
startup if `APP_KEY` or `JWT_SECRET` is empty (`Crypto`/`Jwt` both refuse
to run without a real key, on purpose).

| Variable | How to get it |
|---|---|
| `APP_KEY` | `php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"` |
| `JWT_SECRET` | `php -r "echo bin2hex(random_bytes(32));"` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Whatever you created in step 3 |
| `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` | [Stripe test-mode API keys](https://dashboard.stripe.com/test/apikeys) |
| `STRIPE_WEBHOOK_SECRET` | From the webhook endpoint you create in step 8 (or the Stripe CLI's `stripe listen` output for local testing) |
| `STRIPE_PRICE_STARTER` / `_PRO` / `_AGENCY` | Create three test-mode Products/Prices in Stripe first, paste their price IDs |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google Cloud Console → OAuth 2.0 Client ID (Web application). Enable the **Search Console API** on the project. |
| `GOOGLE_REDIRECT_URI` | `{APP_URL}/api/v1/gsc/callback` — must be added to the OAuth client's authorized redirect URIs exactly |
| `ANTHROPIC_API_KEY` | [console.anthropic.com](https://console.anthropic.com/settings/keys) |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_ENCRYPTION` / `SMTP_USERNAME` / `SMTP_PASSWORD` / `SMTP_FROM_EMAIL` / `SMTP_FROM_NAME` | Any real SMTP account (a Gmail app password works for testing) |
| `NOTIFICATION_EMAIL_TO` | Where you want ready_for_review/published emails to land |

Leave `APP_URL` as `http://localhost:8080` for local testing (step 6).

## 5. Run migrations

```bash
php migrations/migrate.php
```

Expect ten `Migrated NNN_...` lines (001 through 009 on Phase 1, plus 007's
follow-up alter — check the file list in `migrations/` if the count looks
off). This is the single most likely place to hit a first-run bug — the
SQL has been written but never executed. If a migration fails partway,
`migrate.php` rolls back that migration's transaction and exits nonzero;
fix the issue and rerun (already-applied migrations are tracked and
skipped).

To undo the most recent migration: `php migrations/migrate.php rollback`.

## 6. Seed plans

Create the three Products/Prices in Stripe test mode first (if you haven't
already, for the `.env` values above), then:

```bash
php migrations/seeds/seed_plans.php
```

Safe to rerun — it's an upsert.

## 7. Run the app locally

```bash
php -S localhost:8080 -t public
```

Smoke test it's alive:

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"account_name":"Test Co","email":"test@example.com","password":"correct-horse-battery-staple","plan_key":"starter"}'
```

Expect a 201 with `account_id`, `user_id`, `access_token`, `refresh_token`.
A connection-refused or 500 here means something in steps 3–6 didn't take —
check the PHP built-in server's terminal output for the actual error.

## 8. Run the tests

```bash
vendor/bin/phpunit
```

Both tests (`TenantIsolationTest`, `ContentQueueTenantIsolationTest`) run
against the real database from step 3/5 — not a mock, not SQLite, because
the thing under test (`BaseModel`'s generated SQL) is exactly what would be
faked away by mocking the connection. They clean up their own rows
(`tenant-test-*` / `tenant-cq-test-*` prefixes) in `tearDown()`, so it's
safe to point this at the same DB you're doing manual testing against.

If these fail, that's more informative than a passing run would be — it's
the first real signal on whether `BaseModel`'s tenant scoping and the two
job-pipeline endpoints actually work as designed.

## 9. Run the worker

```bash
php workers/worker.php
```

With nothing queued, expect `Processed 0 job(s).` and a clean exit. To
prove the pipeline end to end, enqueue a no-op job first:

```bash
php -r '
require "vendor/autoload.php";
require "app/config/env.php";
(new App\Models\Job())->enqueue("noop_test");
'
php workers/worker.php   # should print "[N] completed"
```

## 10. Full end-to-end smoke test

1. Register an account (step 7's curl, or a fresh one)
2. `POST /api/v1/sites` with a `domain` — save the `id`
3. `POST /api/v1/billing/subscribe` with `plan_key: starter` → open the
   returned `checkout_url`, pay with Stripe's test card (`4242 4242 4242
   4242`, any future expiry/CVC)
4. Point your Stripe webhook (or `stripe listen --forward-to
   localhost:8080/api/v1/billing/webhook`) at the app, confirm
   `GET /api/v1/billing/status` shows `active` after checkout completes
5. `GET /api/v1/sites/{id}/gsc/connect` → open the `auth_url`, authorize a
   Search Console property you actually own (GSC only returns data for
   verified properties)
6. After the OAuth callback redirects back, a `sync_gsc` job is already
   queued — run `php workers/worker.php` to process it
7. `GET /api/v1/content-queue` — should now list keywords pulled from GSC,
   each triggering its own `generate_content_draft` job; run the worker
   again (repeatedly, or raise `WORKER_BATCH_SIZE`) until they're all
   `ready_for_review`
8. Check `NOTIFICATION_EMAIL_TO` for the ready-for-review emails
9. `POST /api/v1/content-queue/{id}/approve` on one row
10. Run the worker again to process its `publish_content` job — check the
    WordPress site's Drafts for the new post, and `published_url` on the
    `content_queue` row

That's the Phase 1 + Phase 2 "done when" criteria from
[phase-roadmap.md](phase-roadmap.md), exercised for real.

## Deploying to cPanel/CyberPanel

- Document root must point at `public/` — everything else (`app/`,
  `migrations/`, `.env`, `vendor/`) should sit **outside** the web root if
  your control panel allows it, or be blocked via `.htaccess` if not.
  `public/index.php` is the only file meant to be web-accessible.
- Set the PHP version to 8.3 in the control panel's PHP selector.
- If shell/SSH access isn't available, `composer install` and
  `php migrations/migrate.php` need to run via the control panel's
  "Terminal" feature or a one-time SSH session — most cPanel/CyberPanel
  hosts have one even on shared plans now.
- Cron entry (control panel's Cron Jobs UI):
  `* * * * * php /home/youruser/evs-visibility-engine/workers/worker.php >> /home/youruser/evs-visibility-engine/storage/worker.log 2>&1`
- Point the Stripe webhook and Google OAuth redirect URI at the real
  `APP_URL` once it's live, and update `.env` (or `.env.production`, which
  overlays on top per `app/config/env.php`) accordingly.
