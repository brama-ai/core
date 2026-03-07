# LiteLLM Tracing Contract

## Required Metadata Fields

Every LLM request body must include a `metadata` dict with these fields:

| Key | Type | Langfuse Mapping | Description | Example |
|-----|------|-----------------|-------------|---------|
| `trace_id` | `string` | Trace ID | One orchestration run / agent-chain execution | `"a1b2c3d4e5f67890..."` |
| `trace_name` | `string` | Trace name | Human-readable trace label | `"hello-agent.a2a.hello.greet"` |
| `session_id` | `string` | Session ID | Groups traces into a conversation/thread | `"req_abc123"` |
| `generation_name` | `string` | Generation name | Name of this specific LLM call step | `"a2a.hello.greet"` |
| `tags` | `list[string]` | Tags | Filterable labels | `["agent:hello-agent", "method:a2a.hello.greet"]` |
| `trace_user_id` | `string` | User ID | Who triggered the request | `"service=hello-agent;..."` |
| `trace_metadata` | `object` | Trace metadata | Extra structured context | `{"request_id": "...", ...}` |

## Recommended Fields (Where Available)

| Key | Type | Description |
|-----|------|-------------|
| `parent_observation_id` | `string` | For nesting LLM calls under a parent span |
| `trace_release` | `string` | App version / release tag |
| `trace_version` | `string` | Trace schema version |

## Field Conventions

### `trace_id`
- Format: 32-hex-char string (W3C trace-id compatible) or UUID
- Generated once per orchestration run, propagated across all calls in the chain
- Passed via `X-Trace-Id` header between services

### `trace_name`
- Format: `<agent_name>.<feature_name>`
- Example: `"knowledge-agent.knowledge.analyze_messages"`

### `session_id`
- For interactive chats: use the conversation/thread ID so all turns group together
- For batch jobs: use the request ID (each run gets its own session)
- For per-item processing (e.g., rewriter): use the base request ID (without item suffix) so all items in one batch group together

### `generation_name`
- Matches the `feature_name` — the specific operation being performed
- Example: `"news.ranker.run_ranking"`, `"knowledge.embedding"`

### `tags`
- Always include: `["agent:<agent_name>", "method:<feature_name>"]`
- Tags appear both in `metadata.tags` (for Langfuse) and top-level `tags` (for LiteLLM spend logs)

### `trace_user_id`
- Format: `"service=<name>;feature=<feature>;request_id=<id>"`
- Same value as the top-level `user` field in the request body

### `trace_metadata`
- Contains operational context not used for Langfuse filtering:
  - `request_id` — unique per LLM call
  - `agent_name` — which agent made the call
  - `feature_name` — which feature/operation

## Full Request Body Structure

```json
{
  "model": "minimax/minimax-m2.5",
  "messages": [{"role": "user", "content": "Hello"}],
  "user": "service=hello-agent;feature=a2a.hello.greet;request_id=req_abc",
  "metadata": {
    "trace_id": "a1b2c3d4e5f67890a1b2c3d4e5f67890",
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
  },
  "tags": ["agent:hello-agent", "method:a2a.hello.greet"]
}
```

Note: Top-level `tags` is for LiteLLM spend log filtering. `metadata.tags` is what LiteLLM's Langfuse callback extracts.

## HTTP Headers (Unchanged)

These headers are still sent for OpenSearch log correlation:

| Header | Value |
|--------|-------|
| `X-Request-Id` | Per-call request ID |
| `X-Service-Name` | Agent name |
| `X-Agent-Name` | Agent name |
| `X-Feature-Name` | Feature name |
| `X-Trace-Id` | Trace ID (if available) |
