# Visibility Engine

Multi-tenant SEO + AEO (AI-search visibility) content automation platform.
Internal EVS tool first, resold to other agencies/businesses later.

See `CLAUDE.md` for architecture rules and product spec (this is what Claude
Code reads for project context). See `docs/phase-roadmap.md` for the release
plan and `docs/db-schema-outline.md` for the starting schema.

To actually install, configure, and run this locally or on cPanel/
CyberPanel — including the first end-to-end smoke test — see
[`docs/setup-and-run.md`](docs/setup-and-run.md).

Stack: PHP 8.3 (strict types, OOP-only, no framework, no ORM), PDO/MySQL,
JWT auth, Stripe billing. Deploys to self-managed hosting (cPanel or
CyberPanel) — no PaaS, no Docker in production.

## Status
Phase 1 (Core) — built, pushed to `main`. Phase 2 (Find + Write + Publish)
— built, pushed to `release/v2-content-engine`, not yet merged to `main`.
Neither phase has been run against a real environment yet — see
`docs/setup-and-run.md` before assuming anything works.
