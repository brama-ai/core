## 1. Implementation

- [x] 1.1 Add `knowledge_source_messages` migration in `apps/knowledge-agent/migrations`
- [x] 1.2 Implement Postgres repository for idempotent message upsert with full metadata and raw payload
- [x] 1.3 Add `knowledge.store_message` intent handler in `KnowledgeA2AHandler`
- [x] 1.4 Update `knowledge-agent` manifest skills/schema to expose `knowledge.store_message`
- [x] 1.5 Keep compatibility aliases for existing dot/snake ingestion intents
- [x] 1.6 Ensure A2A controller accepts gateway-compatible direct request envelope

## 2. Tests

- [x] 2.1 Add unit tests for message store repository/handler behavior
- [x] 2.2 Add functional test for `/api/v1/knowledge/a2a` `knowledge.store_message`
- [x] 2.3 Add E2E gateway test for `knowledge.store_message` and DB persistence check

## 3. Integration and Docs

- [x] 3.1 Update `Makefile` E2E agent registration payload to include `knowledge.store_message`
- [x] 3.2 Update relevant docs/contracts for new knowledge ingestion skill

## 4. Quality Checks

- [x] 4.1 `make knowledge-test`
- [x] 4.2 `make e2e-smoke` (or targeted e2e suite including new scenario)
