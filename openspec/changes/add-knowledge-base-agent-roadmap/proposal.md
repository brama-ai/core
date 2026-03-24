# Change: Knowledge Base Agent — Roadmap and Improvements

## Why

The initial `add-knowledge-base-agent` change delivers core extraction, storage, search, and UI capabilities. This proposal captures a curated set of improvements and extensions to grow the Knowledge Base Agent into a more intelligent, discoverable, and community-integrated system after the initial release has been validated in production.

These items are deliberately out of scope for the initial implementation to keep the first release focused and deliverable. They are proposed here so that product and engineering can track, prioritize, and plan them together.

## What Changes

### Intelligence & Quality
- **Auto-deduplication of knowledge entries** — LLM-based similarity check before indexing; merge near-duplicate entries instead of creating redundant ones
- **Knowledge confidence scoring** — assign a confidence score (0–1) to each extracted entry based on LLM certainty; surface low-confidence entries for human review
- **Feedback loop from search usage** — track which entries are opened and linked; boost relevance of frequently accessed entries; deprecate stale entries with zero access
- **Multi-language support** — extracting and searching in languages other than Ukrainian (starting with English as the most common second language in the community)

### Ingestion & Integration
- **Real-time streaming ingestion** — subscribe to `message.created` platform events directly (in addition to batch upload API); process new messages within seconds
- **Auto-trigger on moderator signal** — when a moderator reacts with a designated emoji (e.g., 📌), automatically trigger extraction for that message thread
- **Telegram forward ingestion** — support forwarded messages as a knowledge source with proper attribution
- **Scheduled re-extraction** — periodic re-analysis of old low-quality entries using improved prompts or updated models

### Search & Discovery
- **Query expansion** — before hybrid search, expand user query with LLM-generated synonyms or related terms to improve recall
- **Related entries panel** — on each knowledge entry page, show semantically similar entries (kNN-only lookup on current entry embedding)
- **Faceted filtering in web UI** — filter encyclopedia by tags, date range, source (manual vs auto-extracted), confidence score
- **Personalized search ranking** — track per-user search history and boost entries matching their browsing pattern

### Infrastructure & Operations
- **OpenSearch alias and zero-downtime reindex** — use index aliases (`knowledge_entries` → `knowledge_entries_v1`) and implement seamless alias swap for mapping migrations
- **Embedding cache layer** — cache computed embeddings keyed by content hash (Redis); avoid recomputing unchanged content during re-index
- **Worker autoscaling** — configure RabbitMQ-driven autoscaling for KnowledgeWorker based on queue depth
- **Cost dashboard in admin** — show LLM API token usage, embedding cost, and extraction volume over time

### Community & UX
- **Knowledge entry voting** — community members can upvote/downvote entries; score affects tree ordering and search boosting
- **Suggested knowledge** — after each chat search that returns no results, offer a one-click "запропонувати збереження" flow for members to flag the conversation for extraction
- **Knowledge export** — admin can export the full knowledge base as JSON, Markdown archive, or PDF encyclopedia
- **Embeddable search widget** — a lightweight JS snippet that can embed knowledge search in external community tools (Discord, Notion, etc.)

## Impact

- Affected specs: knowledge-ingestion, knowledge-search, knowledge-web, knowledge-admin, knowledge-worker, knowledge-storage (all modified in subsequent proposals)
- Affected code: `apps/knowledge-agent/`, `apps/brama-core/` (event subscription), `compose.yaml` (Redis for cache)
- No breaking changes — all items are additive extensions
- Dependencies: some items require Redis, updated LLM prompt versions, or additional Telegram Bot API capabilities
