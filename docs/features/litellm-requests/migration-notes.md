# Migration Notes

## What Changed

The `metadata` dict in LLM request bodies was restructured from a flat custom format to a Langfuse-compatible format. This is a **breaking change** for any code that reads or relies on the old metadata structure.

## Old Format (Replaced)

```json
{
  "metadata": {
    "request_id": "req_abc",
    "service_name": "hello-agent",
    "agent_name": "hello-agent",
    "feature_name": "a2a.hello.greet",
    "trace_id": "abc123..."
  }
}
```

## New Format

```json
{
  "metadata": {
    "trace_id": "abc123...",
    "trace_name": "hello-agent.a2a.hello.greet",
    "session_id": "req_abc",
    "generation_name": "a2a.hello.greet",
    "tags": ["agent:hello-agent", "method:a2a.hello.greet"],
    "trace_user_id": "service=hello-agent;feature=a2a.hello.greet;request_id=req_abc",
    "trace_metadata": {
      "request_id": "req_abc",
      "agent_name": "hello-agent",
      "feature_name": "a2a.hello.greet"
    }
  }
}
```

## Field Mapping

| Old Field | New Location | Notes |
|-----------|-------------|-------|
| `metadata.request_id` | `metadata.trace_metadata.request_id` | Moved into sub-object |
| `metadata.service_name` | Removed | Redundant with `agent_name` |
| `metadata.agent_name` | `metadata.trace_metadata.agent_name` | Moved into sub-object |
| `metadata.feature_name` | `metadata.generation_name` + `metadata.trace_metadata.feature_name` | Promoted to Langfuse generation name |
| `metadata.trace_id` | `metadata.trace_id` | Same key, now Langfuse-aware |
| (new) | `metadata.trace_name` | `<agent>.<feature>` |
| (new) | `metadata.session_id` | Groups into conversations |
| (new) | `metadata.tags` | For Langfuse filtering |
| (new) | `metadata.trace_user_id` | For Langfuse user tracking |

## What Stayed the Same

- Top-level `tags` array — still used for LiteLLM spend log filtering
- Top-level `user` string — same `service=...;feature=...;request_id=...` format
- HTTP headers (`X-Request-Id`, `X-Service-Name`, etc.) — unchanged for OpenSearch correlation
- `LangfuseIngestionClient` for A2A orchestration — unchanged, separate concern

## Infrastructure Changes

- `docker/litellm/config.yaml` — Added `success_callback: ["langfuse"]` and `failure_callback: ["langfuse"]`
- `compose.yaml` — Added `LANGFUSE_PUBLIC_KEY`, `LANGFUSE_SECRET_KEY`, `LANGFUSE_HOST` to litellm service

## Affected Files

- `apps/core/src/LLM/LlmRequestContext.php` — New metadata format, added `sessionId` param
- `apps/core/src/LLM/LiteLlmClient.php` — Uses `userTag()` method
- `apps/core/src/Command/AgentChatCommand.php` — Passes `sessionId`
- `apps/hello-agent/src/A2A/HelloA2AHandler.php` — Restructured inline metadata
- `apps/knowledge-agent/src/Llm/TracingHttpClient.php` — Restructured metadata in decorator
- `apps/knowledge-agent/src/Service/EmbeddingService.php` — Restructured metadata
- `apps/news-maker-agent/app/services/ranker.py` — Refactored `_trace_context()`
- `apps/news-maker-agent/app/services/rewriter.py` — Refactored `_trace_context()`
