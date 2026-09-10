# Visibility Engine — Project Overview

**Codename:** visibility-engine (public brand name not yet decided — see
Architecture Decisions log)
**Repo:** github.com/eaglevisionsolutions/evs-visibility-engine
**Local path:** ~/Projects/evs-visibility-engine
**Started:** 2026-09-10

## What it is
Multi-tenant SaaS combining SEO content automation with AEO (AI-search
visibility tracking). Reverse-engineered from Opinly.ai, extended with an
AEO/LLM-citation module Opinly doesn't have. Built to replace the n8n-based
Content Ops pipeline for EVS clients, and to be resold externally.

## Four pillars
1. Find — competitor + own keyword gap analysis
2. Write — Claude-drafted content
3. Publish + Measure — CMS publish, GA4 attribution feeds back into Find
4. AEO — ChatGPT/Gemini/Claude citation tracking, gaps feed the same backlog

## Stack
PHP 8.3 (strict, OOP-only, no framework/ORM), PDO/MySQL, JWT auth, Stripe.
Deploys to self-managed hosting (cPanel or CyberPanel), not a PaaS —
[[StrikeCircle]] conventions minus the Coolify/Docker assumptions.

## Links
- [[01-Architecture-Decisions]]
- [[02-Phase-Roadmap]]
- Repo docs: CLAUDE.md, docs/phase-roadmap.md, docs/db-schema-outline.md
