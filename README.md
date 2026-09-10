# Visibility Engine

Multi-tenant SEO + AEO (AI-search visibility) content automation platform.
Internal EVS tool first, resold to other agencies/businesses later.

See `CLAUDE.md` for architecture rules and product spec (this is what Claude
Code reads for project context). See `docs/phase-roadmap.md` for the release
plan and `docs/db-schema-outline.md` for the starting schema.

Stack: PHP 8.3 (strict types, OOP-only, no framework, no ORM), PDO/MySQL,
JWT auth, Stripe billing. Deploys to self-managed hosting (cPanel or
CyberPanel) — no PaaS, no Docker in production.

## Status
Phase 1 (Core) — built, pushed to `main`. Phase 2 (Find + Write + Publish)
in progress on `release/v2-content-engine`.
