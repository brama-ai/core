# Proposal: Add News Digest Generation with Deduplication

## Problem

The news-maker agent crawls, ranks, and rewrites individual news items but has no mechanism to:
1. Combine multiple curated items into a single digest summary.
2. Detect semantic duplicates across items using embeddings + LLM verification.
3. Manage curated news lifecycle (soft-delete, moderation status) from admin UI.
4. Control which statuses feed into the digest pipeline.

Currently, items are published individually via API, and there is no duplicate detection beyond URL-based `dedup_hash`.

## Solution

Extend the news-maker agent with four capabilities:

1. **News Deduplication** — When a new curated item is created, compute its embedding via LiteLLM, compare against existing items from the last 2 months, and use LLM to confirm/reject duplicate status. Duplicates get `status = duplicate` instead of `ready`.

2. **News Digest Generation** — A new digest service that collects curated items matching configured statuses (default: `new` + `moderated`), generates an adaptive-length digest via LLM (length per item scales inversely with item count), and marks included items as `published`.

3. **News Item Management (Admin CRUD)** — Admin UI page listing all curated items with ability to view, change status (including soft-delete via `deleted` status), and moderate items (`moderated` status).

4. **Digest Admin Settings** — System prompt/guardrail for the digest agent, configurable digest source statuses, and digest model selection in the existing settings page.

## Scope

### In Scope
- Embedding-based similarity search for deduplication (via LiteLLM `/embeddings` endpoint)
- LLM-based duplicate confirmation step
- 2-month lookback window for dedup comparisons
- Digest generation service with adaptive length
- New curated item statuses: `duplicate`, `moderated`, `deleted`
- Admin CRUD for curated news items with status transitions
- Admin settings for digest prompt, guardrail, model, and source statuses
- Manual digest trigger from admin
- Digest cron schedule in settings

### Out of Scope
- Vector database (use pgvector or in-memory cosine similarity)
- Personalized digests per user
- Automatic posting to Telegram (future scope)
- Digest history/archive browsing

## Affected Capabilities

| Capability | Action |
|---|---|
| `news-deduplication` | NEW — embedding + LLM duplicate detection |
| `news-digest` | NEW — adaptive digest generation |
| `news-digest-admin` | NEW — digest settings, curated items CRUD |
| `news-item-management` | NEW — status lifecycle, soft delete, moderation |
| `news-curation` (existing) | MODIFIED — new statuses in curated item flow |
| `news-admin` (existing) | MODIFIED — digest settings added |
