# Design: Add News Digest Generation with Deduplication

## Context

The news-maker agent currently processes items individually: crawl → rank → rewrite → ready. Publication is a manual per-item API call. There is no semantic deduplication (only URL hash) and no way to generate a compiled digest from multiple items.

## Key Decisions

### D1: Embedding Storage — pgvector extension vs. in-app cosine similarity

**Chosen: pgvector extension on Postgres**

- The news-maker agent already uses Postgres; pgvector adds native vector column + cosine distance operator.
- Scales better than loading all embeddings into memory for comparison.
- 2-month lookback with potentially thousands of items makes in-memory impractical.
- Migration adds `vector` column to `curated_news_items` table.
- Dependency: `pgvector` Postgres extension + `pgvector` Python package.

### D2: Embedding provider — direct model vs. LiteLLM proxy

**Chosen: LiteLLM `/v1/embeddings` endpoint**

- Consistent with existing pattern (ranker/rewriter already use LiteLLM).
- Model is configurable in `AgentSettings` (e.g., `text-embedding-3-small`).
- Single dependency path, no new API keys needed.

### D3: Deduplication flow — embedding-only vs. embedding + LLM confirmation

**Chosen: Two-stage: embedding similarity threshold → LLM confirmation**

- Embedding cosine similarity > 0.85 triggers LLM confirmation step.
- LLM receives both summaries and decides if they cover the same event/topic.
- Reduces false positives from similar-but-distinct news (e.g., two different AI model releases).
- LLM call only happens for close matches, keeping costs low.

### D4: Digest length strategy — fixed vs. adaptive

**Chosen: Adaptive length via LLM prompt engineering**

- The digest prompt includes item count and instructs the LLM:
  - 1 item → full detailed coverage (~500 words)
  - 2–3 items → moderate detail (~200 words each)
  - 4–7 items → concise summaries (~100 words each)
  - 8+ items → brief bullets (~50 words each)
- Total target: ~600–800 words regardless of item count.
- This is enforced via prompt, not hard code — tunable in admin.

### D5: Curated item status model — extend existing vs. separate table

**Chosen: Extend existing `curated_news_items.status` with new values**

Current statuses: `draft | ready | published | rejected`

New statuses added:
- `duplicate` — set by dedup service, excluded from digest
- `moderated` — set by admin after manual review, eligible for digest
- `deleted` — soft delete, excluded from everything

Full status flow:
```
[rewriter] → draft → ready
                        ↓
              [dedup] → duplicate (if match found)
                        ↓ (if unique)
                      ready → moderated (admin action)
                             → deleted (admin action)
                             → published (digest generation)
                             → rejected (admin action)
```

### D6: Digest source status configuration

**Chosen: Configurable list in AgentSettings**

- New column `digest_source_statuses` (comma-separated string, default: `moderated,new`).
- Admin UI shows checkboxes for eligible statuses.
- Digest service queries curated items matching any of the configured statuses.
- This allows operators to decide: require moderation first (`moderated` only) or auto-include (`ready` + `moderated`).

Note: "new" in the context of digest source statuses maps to `ready` status in the curated items table (items that passed dedup and are ready for digest). This avoids confusion — the admin UI labels it clearly.

### D7: Digest output model

**Chosen: New `Digest` table**

- `id`, `title`, `body`, `language`, `item_count`, `source_statuses_used`, `created_at`
- Many-to-many link table `digest_items` (digest_id, curated_news_item_id) tracks which items went into each digest.
- After digest generation, included items transition to `published` status.

### D8: Where to add admin news CRUD

**Chosen: New admin router `/admin/news` in news-maker agent**

- Lists all curated items with status badges, pagination.
- Actions: view detail, change status (moderated/deleted/rejected), bulk status change.
- Consistent with existing `/admin/sources` and `/admin/settings` patterns.

## System Interactions

```
[Crawl Pipeline]
  crawl → rank → rewrite → curated_item(status=draft→ready)
                                ↓
[Dedup Service]  ← called after rewrite
  compute embedding → query pgvector for similar (2 months) → if match: LLM confirm
    → duplicate: status=duplicate
    → unique: status=ready (unchanged)
                                ↓
[Admin moderation] (optional)
  admin reviews ready items → sets status=moderated
                                ↓
[Digest Service]  ← cron or manual trigger
  query items by configured statuses → LLM generates digest → save Digest record
  → mark included items as published
```

## Migration Plan

1. Add `pgvector` extension to Postgres (requires superuser, done in Docker init).
2. Alembic migration: add `embedding` vector column to `curated_news_items`.
3. Alembic migration: add `Digest` and `digest_items` tables.
4. Alembic migration: add digest settings columns to `agent_settings`.
5. Update `compose.agent-news-maker.yaml` to enable pgvector in Postgres image.

## Risks

- **pgvector availability**: Requires `pgvector/pgvector:pg16` Docker image or manual extension install. Mitigated by using official pgvector Docker image.
- **Embedding costs**: Each new curated item requires one embedding call. At ~10 items per crawl run, this is minimal.
- **LLM dedup false negatives**: Two-stage approach (embedding + LLM) minimizes this but doesn't eliminate it. Acceptable for MVP.
