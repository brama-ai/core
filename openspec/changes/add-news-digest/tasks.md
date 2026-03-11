# Tasks: Add News Digest Generation with Deduplication

## Stage 1: Database & Infrastructure

- [x] 1.1 Add pgvector extension to Postgres Docker image (use `pgvector/pgvector:pg16` or install extension in init script)
- [x] 1.2 Add `pgvector` Python package to `requirements.txt`
- [x] 1.3 Alembic migration: add `embedding` vector column to `curated_news_items` table (dimension configurable, default 1536)
- [x] 1.4 Alembic migration: add new statuses support — update status column comment/docs (no schema change needed, statuses are string-based)
- [x] 1.5 Alembic migration: create `digests` table (`id`, `title`, `body`, `language`, `item_count`, `source_statuses_used`, `created_at`)
- [x] 1.6 Alembic migration: create `digest_items` link table (`digest_id`, `curated_news_item_id`)
- [x] 1.7 Alembic migration: add digest settings to `agent_settings` table (`digest_prompt`, `digest_guardrail`, `digest_model`, `digest_source_statuses`, `digest_cron`, `embedding_model`)
- [x] 1.8 Update SQLAlchemy models: `CuratedNewsItem` (add `embedding` column), new `Digest` and `DigestItem` models, extend `AgentSettings`
- [x] 1.9 Update Pydantic schemas for new models and extended settings

## Stage 2: Deduplication Service

- [x] 2.1 Create `app/services/dedup.py` — embedding computation via LiteLLM `/v1/embeddings`
- [x] 2.2 Implement pgvector cosine similarity query (threshold 0.85, 2-month window)
- [x] 2.3 Implement LLM duplicate confirmation (send both summaries, get yes/no JSON response)
- [x] 2.4 Integrate dedup into crawl pipeline: call after rewriter, before items become `ready`
- [x] 2.5 Add trace context propagation to dedup service (consistent with ranker/rewriter pattern)

**Validation**: Run crawl pipeline with duplicate articles from same source → verify `duplicate` status is set.

## Stage 3: Digest Generation Service

- [x] 3.1 Create `app/services/digest.py` — digest generation service
- [x] 3.2 Implement eligible item collection based on configured source statuses
- [x] 3.3 Implement adaptive length prompt construction (item count → detail level mapping)
- [x] 3.4 Implement digest persistence (Digest record + digest_items links)
- [x] 3.5 Implement published status transition for included items (atomic: all or none)
- [x] 3.6 Add trace context propagation to digest service
- [x] 3.7 Register digest job in scheduler (cron from settings + manual trigger)
- [x] 3.8 Add `trigger_digest_now()` function in scheduler module

**Validation**: Seed curated items with `ready` status → run digest → verify digest record created and items marked `published`.

## Stage 4: Admin — Digest Settings

- [x] 4.1 Extend settings form: add digest prompt, guardrail, model fields
- [x] 4.2 Add digest source statuses checkboxes (ready, moderated, new)
- [x] 4.3 Add digest cron schedule input
- [x] 4.4 Add embedding model input field
- [x] 4.5 Add "Generate Digest" manual trigger button
- [x] 4.6 Update settings POST handler to persist new fields
- [x] 4.7 Wire manual digest trigger endpoint (`POST /admin/trigger/digest`)

**Validation**: Update digest settings → trigger digest → verify settings are used.

## Stage 5: Admin — Curated News CRUD

- [x] 5.1 Create admin router `app/routers/admin/news.py` with list endpoint (`GET /admin/news`)
- [x] 5.2 Create admin template `templates/admin/news.html` — table with status badges, filters
- [x] 5.3 Implement status filter (dropdown: all, ready, moderated, duplicate, published, rejected, deleted)
- [x] 5.4 Implement status change endpoint (`POST /admin/news/{item_id}/status`)
- [x] 5.5 Implement soft-delete action (status → `deleted`)
- [x] 5.6 Implement moderate action (status → `moderated`)
- [x] 5.7 Add pagination to news list
- [x] 5.8 Register new router in app main module

**Validation**: Browse curated items → change statuses → verify transitions are enforced.

## Stage 6: Integration & Testing

- [x] 6.1 Update `compose.yaml` to use pgvector image (`pgvector/pgvector:pg16`)
- [x] 6.2 End-to-end test: full pipeline crawl → dedup → digest generation
- [x] 6.3 Verify admin settings persistence and reload
- [x] 6.4 Verify admin news CRUD and status transitions
- [x] 6.5 Run existing E2E tests to ensure no regressions
