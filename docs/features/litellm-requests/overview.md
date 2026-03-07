# LiteLLM Request Tracing — Overview

## Purpose

Every LLM call in the AI Community Platform flows through the LiteLLM proxy (`http://litellm:4000`). To enable full observability, all agents must attach a standardized metadata payload to each request. The LiteLLM proxy then forwards these metadata fields to Langfuse via native callbacks.

## Architecture

```
Agent (PHP/Python/TS)
  │
  │  POST /v1/chat/completions
  │  body: { metadata: { trace_id, session_id, generation_name, ... } }
  │
  ▼
LiteLLM Proxy (port 4000)
  │
  ├─► OpenRouter / LLM Provider (model inference)
  │
  └─► Langfuse (success_callback / failure_callback)
        Extracts metadata.trace_id → Langfuse trace
        Extracts metadata.session_id → Langfuse session
        Extracts metadata.generation_name → Langfuse generation name
        Extracts metadata.tags → Langfuse tags
```

## Two Integration Paths

1. **LiteLLM Callbacks (LLM generations)** — Automatic. LiteLLM proxy reads `metadata` from request body and sends generation events to Langfuse. Agents only need to set the correct metadata keys.

2. **Custom LangfuseIngestionClient (A2A orchestration)** — Manual. PHP services in `core` and `hello-agent` send trace/span events directly to Langfuse for A2A call orchestration. This captures the orchestration layer (which agent called which), not the LLM generation itself.

Both paths coexist. The `trace_id` field correlates them — an A2A orchestration trace and the LLM generation it triggered share the same `trace_id`.

## Key Files

- `docker/litellm/config.yaml` — LiteLLM proxy config with Langfuse callbacks
- `compose.yaml` — Langfuse credentials injected into LiteLLM container
- `apps/core/src/LLM/LlmRequestContext.php` — Canonical PHP metadata builder
- `apps/news-maker-agent/app/services/ranker.py` — Python metadata pattern

## Related Docs

- [Tracing Contract](tracing-contract.md) — Full metadata specification
- [Langfuse Integration](langfuse-integration.md) — Configuration and debugging
- [Migration Notes](migration-notes.md) — Changes from previous format
- [Examples](examples.md) — Code samples for all languages
