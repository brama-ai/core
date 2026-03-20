# Tasks: add-knowledge-base-agent

## 0. Project Setup

- [x] 0.1 Add OpenSearch 2.x service to `compose.yaml` with volume and health check
- [x] 0.2 Add RabbitMQ 3.x service to `compose.yaml` with management UI port
- [x] 0.3 Create `apps/knowledge-agent/` as new Symfony 7 application scaffold
- [x] 0.4 Add `neuron-ai/neuron-ai` and `neuron-ai/workflow` to `apps/knowledge-agent/composer.json`
- [x] 0.5 Add deep-research-agent and travel-planner-agent as dev-reference entries in composer:
      `"repositories"` block pointing to `https://github.com/neuron-core/deep-research-agent` and `https://github.com/neuron-core/travel-planner-agent`
- [x] 0.6 Update `index.md` with neuron-ai section: links to deep-research-agent and travel-planner-agent, key source files, and usage patterns
- [x] 0.7 Add `LOCAL_DEV.md` entries for OpenSearch (port 9200) and RabbitMQ (port 5672, management 15672)

## 1. Infrastructure: OpenSearch Index

- [x] 1.1 Create `OpenSearchIndexManager` service for index lifecycle (create, check, reindex)
- [x] 1.2 Define `knowledge_entries_v1` mapping: text fields with Ukrainian analyzer, `knn_vector` for embedding (1536 dims, HNSW cosinesimil), keyword fields for tree_path/tags/category
- [x] 1.3 Create Symfony console command `knowledge:index:setup` to create/verify index on deploy
- [x] 1.4 Write unit tests for index creation and mapping validation

## 2. Infrastructure: RabbitMQ + Postgres

- [x] 2.1 Create Postgres migration: `processed_chunks (chunk_hash, status, attempt_count, processed_at, created_at)`
- [x] 2.2 Create `RabbitMQPublisher` service: connect, declare exchange `knowledge.direct`, queue `knowledge.chunks`, DLQ `knowledge.dlq`
- [x] 2.3 Write integration test for chunk publish → queue presence

## 3. neuron-ai Agent: Extraction Workflow

- [x] 3.1 Create `KnowledgeExtractionWorkflow` extending `NeuronAI\Workflow\Workflow`
- [x] 3.2 Implement `AnalyzeMessages` node: LLM call to determine if chunk has extractable knowledge; returns `is_valuable: bool`
- [x] 3.3 Implement `ExtractKnowledge` node: LLM call to extract `title`, `body`, `tags[]`, `category`, `tree_path` as structured JSON
- [x] 3.4 Implement `EnrichMetadata` node: attach `source_message_id`, `message_link` (Telegram deep link), `created_by`, `created_at`
- [x] 3.5 Add Inspector integration for workflow monitoring
- [x] 3.6 Load base instructions + security instructions from config/storage into workflow system prompt
- [x] 3.7 Write unit tests for each node with mocked LLM responses
- [x] 3.8 Write integration test for full workflow with a sample message chunk

## 4. Knowledge Worker

- [x] 4.1 Create `KnowledgeWorker` Symfony console command (long-running): consume from `knowledge.chunks`
- [x] 4.2 Implement dedup check against `processed_chunks` before calling workflow
- [x] 4.3 Implement retry logic: nack + requeue if `attempt_count < 3`, move to DLQ otherwise
- [x] 4.4 Implement rate limiter (token bucket, 60 LLM calls/min default, configurable)
- [x] 4.5 Add concurrency: configurable via `KNOWLEDGE_WORKER_CONCURRENCY` env var
- [x] 4.6 Implement `/health/worker` HTTP endpoint (lightweight Symfony controller)
- [x] 4.7 Write functional tests for worker processing, dedup, retry, and DLQ behaviour

## 5. Message Ingestion API

- [x] 5.1 Implement `MessageChunker` service: apply 15-min time-window, 50-message size cap, 5-message overlap
- [x] 5.2 Implement chunk hash: `sha256(json_encode(sort(message_ids)))`
- [x] 5.3 Create POST `/api/v1/knowledge/upload` controller: validate, chunk, publish to RabbitMQ, return `202`
- [x] 5.4 Add authentication guard (API key or JWT) to upload endpoint
- [x] 5.5 Write unit tests for `MessageChunker` with edge cases (large batch, time gap, overlap)
- [x] 5.6 Write functional test for upload endpoint (happy path + validation errors)

## 6. Knowledge Storage Service

- [x] 6.1 Create `KnowledgeRepository` service: `index()`, `get()`, `update()`, `delete()`, `search()`
- [x] 6.2 Implement embedding generation: call configured embedding provider, cache result, store in `embedding` field
- [x] 6.3 Write unit tests for `KnowledgeRepository` with mocked OpenSearch client

## 7. Search API

- [x] 7.1 Implement hybrid search query builder: BM25 `multi_match` + `knn` query, `min_max_score` normalization, `arithmetic_mean` combiner
- [x] 7.2 Create GET `/api/v1/knowledge/search` controller with `q`, `mode` (hybrid/keyword/vector), `size` parameters
- [x] 7.3 Write functional tests for all three search modes

## 8. Knowledge Tree API

- [x] 8.1 Implement `KnowledgeTreeBuilder` service: aggregate `tree_path` terms from OpenSearch with doc counts
- [x] 8.2 Create GET `/api/v1/knowledge/tree` controller (60-second cached response)
- [x] 8.3 Create GET `/api/v1/knowledge/entries` controller with `tree_path`, `tags`, `category` filters and pagination
- [x] 8.4 Write functional tests for tree aggregation and filtered listing

## 9. Knowledge CRUD API

- [x] 9.1 Create GET `/api/v1/knowledge/entries/{id}` controller
- [x] 9.2 Create POST `/api/v1/knowledge/entries` controller (admin auth)
- [x] 9.3 Create PUT `/api/v1/knowledge/entries/{id}` controller (admin auth, regenerates embedding)
- [x] 9.4 Create DELETE `/api/v1/knowledge/entries/{id}` controller (admin auth)
- [x] 9.5 Add admin auth guard middleware to CRUD endpoints
- [x] 9.6 Write functional tests for all CRUD operations and auth checks

## 10. OpenAPI Spec

- [x] 10.1 Author or generate `openapi/knowledge-api.yaml` (OpenAPI 3.1) covering all endpoints from steps 5–9
- [x] 10.2 Serve spec at GET `/api/v1/knowledge/openapi.json`
- [x] 10.3 Validate spec with `spectral lint` or equivalent

## 11. A2A Integration

- [x] 11.1 Create `KnowledgeA2AHandler` implementing platform A2A contract for intents: `search_knowledge`, `extract_from_messages`, `get_tree`
- [x] 11.2 Register `knowledge-base` agent in platform Agent Registry
- [x] 11.3 Write integration tests for A2A request/response round-trip

## 12. Web Encyclopedia

- [x] 12.1 Scaffold web encyclopedia route `/wiki` in knowledge-agent Symfony app
- [x] 12.2 Implement Twig layout: left tree sidebar + center content area (or Vue 3 SPA)
- [x] 12.3 Implement tree navigation: click category → entry list; click entry → entry detail
- [x] 12.4 Implement search bar: call `/api/v1/knowledge/search`, render results in center panel
- [x] 12.5 Implement "Перейти до джерела" message link display
- [x] 12.6 Implement `503` response when encyclopedia is disabled in admin settings
- [x] 12.7 Write E2E tests (Playwright): tree navigation, search, source link visibility

## 13. Admin Panel

- [x] 13.1 Add `/admin/knowledge` route and settings page to admin app
- [x] 13.2 Implement encyclopedia visibility toggle (persisted to config store or Postgres)
- [x] 13.3 Implement base instructions editor: textarea, save button, validation
- [x] 13.4 Implement security instructions read-only section (loaded from config, locked UI)
- [x] 13.5 Implement instruction preview interface: test input → call workflow in preview mode → show result
- [x] 13.6 Implement knowledge CRUD page: tree nav + entry list + create/edit/delete forms
- [x] 13.7 Implement DLQ monitor: show dead-letter count, requeue button
- [x] 13.8 Write E2E tests (Playwright): settings save, CRUD operations, instruction preview

## 14. Quality and Documentation

- [x] 14.1 Run `phpstan analyse` at level 8 — zero errors
- [x] 14.2 Run `php-cs-fixer check` — no violations
- [x] 14.3 Run `codecept run` — all unit + functional suites pass
- [x] 14.4 Run `make e2e` (Playwright) — all E2E tests pass
- [x] 14.5 Update `docs/agents/knowledge-extractor-prd.md` to reflect new capabilities (rename to knowledge-base-agent)
- [x] 14.6 Create `docs/plans/knowledge-base-agent-development-plan.md`
- [x] 14.7 Add Ukrainian documentation page under `docs/` for the web encyclopedia feature
