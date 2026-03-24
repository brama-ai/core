# Change: Add Knowledge Base Agent

## Why

Community chats accumulate valuable knowledge that disappears in long message streams. This agent automatically extracts, structures, and indexes knowledge from chat history, making it searchable and navigable via a dedicated web encyclopedia and admin panel.

## What Changes

- **New service: `knowledge-agent`** — PHP Symfony service using neuron-ai (Workflow) to analyze chat message chunks and extract structured knowledge entries
- **New infrastructure: OpenSearch** — replaces planned basic full-text search for knowledge; provides hybrid (BM25 + kNN vector) search with rich metadata including direct message links
- **New infrastructure: RabbitMQ** — async chunked processing pipeline for message ingestion; chunks are formed by time window and count, deduplicated by chunk hash
- **New capability: Knowledge Tree** — hierarchical topic tree built from extracted knowledge tags and categories, stored in OpenSearch
- **New capability: REST API** — OpenAPI-documented endpoints: upload conversation batch, get knowledge tree, get knowledge page, hybrid search, CRUD operations
- **New capability: Web Encyclopedia** — Ukrainian-language SPA: left panel = knowledge tree, center = knowledge content; publicly accessible (toggleable)
- **New capability: Admin Panel** — settings (enable/disable web panel), base agent instructions editor (chat-like interface), security instructions (always appended), knowledge CRUD page
- **A2A integration** — KnowledgeBaseAgent implements the platform A2A contract; callable on-demand from core-agent or other platform modules
- **neuron-ai integration** — Agent extends `NeuronAI\Workflow\Workflow` with nodes: `AnalyzeMessages → ExtractKnowledge → EnrichMetadata`

## Impact

- Affected specs: knowledge-ingestion, knowledge-storage, knowledge-search, knowledge-tree, knowledge-api, knowledge-web, knowledge-admin, knowledge-worker
- Affected code: `apps/brama-core/`, new `apps/knowledge-agent/` service, `compose.yaml` (OpenSearch, RabbitMQ services), `openspec/` (new capability specs)
- **New external dependencies**: OpenSearch 2.x, RabbitMQ 3.x, neuron-ai PHP framework, an embedding provider (e.g. OpenAI `text-embedding-3-small`)
- **Breaking**: No breaking changes to existing platform contracts; extends MVP scope beyond original simple full-text search assumption
