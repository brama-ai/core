# Design: OpenClaw Agent Discovery

## Context

OpenClaw is the runtime for the core-agent layer. It handles intent routing, clarification loops, and response composition. To delegate work to platform agents (knowledge-base, locations-catalog, etc.), OpenClaw needs a tool definition for each: a name, description, and JSON Schema for the input it should pass.

The platform must remain the ownership boundary — OpenClaw must never call agent services directly or hold agent state. The platform exposes two things to OpenClaw:
1. A **discovery endpoint** to read the current enabled tool catalog
2. An **invoke bridge** to call an agent and get an A2A response back

This keeps the ADR `adr_0002_openclaw_role.md` intact: platform owns data, permissions, registry, routing; OpenClaw owns orchestration.

## Goals / Non-Goals

### Goals
- Discovery endpoint translates registry into OpenClaw tool definitions
- A2A invoke bridge is the only way OpenClaw can call agents (no direct service access)
- Admin can see OpenClaw sync state
- OpenClaw config is updated automatically when agents are enabled/disabled

### Non-Goals
- OpenClaw holding persistent agent state
- OpenClaw calling agent services directly
- Multi-tenant tool catalogs (single community MVP)
- Streaming A2A responses through OpenClaw (out of scope for now)

---

## Architecture

```
OpenClaw (core-agent runtime)
    │
    │  1. On startup: GET /api/v1/agents/discovery
    │  2. On invoke:  POST /api/v1/agents/invoke
    │
    ▼
[Platform A2A Bridge]  ← apps/brama-core
    │
    ├── validates caller token (OpenClaw gateway token)
    ├── checks agent is enabled in registry
    ├── resolves a2a_endpoint from manifest
    │
    ▼
[Agent Service A2A endpoint]
    │
    ▼
response flows back through bridge → OpenClaw
```

---

## Discovery Endpoint Response Format

`GET /api/v1/agents/discovery` returns a tool catalog in a format OpenClaw can consume directly as a skills/tools config block.

```json
{
  "platform_version": "0.1.0",
  "generated_at": "2026-03-04T12:00:00Z",
  "tools": [
    {
      "name": "knowledge_search",
      "agent": "knowledge-base",
      "description": "Search the community knowledge base using hybrid semantic and keyword search.",
      "input_schema": {
        "type": "object",
        "properties": {
          "query": { "type": "string", "description": "Search query" },
          "mode":  { "type": "string", "enum": ["hybrid", "keyword", "vector"], "default": "hybrid" }
        },
        "required": ["query"]
      }
    },
    {
      "name": "extract_from_messages",
      "agent": "knowledge-base",
      "description": "Analyze a set of chat messages and extract structured knowledge entries.",
      "input_schema": {
        "type": "object",
        "properties": {
          "messages": {
            "type": "array",
            "items": { "type": "object" },
            "description": "Array of message objects to analyze"
          }
        },
        "required": ["messages"]
      }
    },
    {
      "name": "get_knowledge_tree",
      "agent": "knowledge-base",
      "description": "Return the full hierarchical knowledge tree.",
      "input_schema": { "type": "object", "properties": {} }
    }
  ]
}
```

Each capability declared in the agent manifest becomes a separate tool in the catalog. Tool `input_schema` is derived from a capability-specific schema file the agent can optionally provide, or defaults to an open `{ "type": "object" }`.

---

## A2A Invoke Bridge

`POST /api/v1/agents/invoke`

```json
{
  "tool": "knowledge_search",
  "input": { "query": "symfony middleware" },
  "trace_id": "uuid",
  "request_id": "uuid"
}
```

Platform bridge:
1. Validates OpenClaw caller token
2. Resolves `tool` → `agent` from registry
3. Checks agent is enabled
4. Constructs A2A request: `{ "intent": "knowledge_search", "payload": input, ... }`
5. POSTs to `agent.a2a_endpoint`
6. Returns A2A response to OpenClaw

Response to OpenClaw:
```json
{
  "request_id": "uuid",
  "status": "completed",
  "result": { ... },
  "agent": "knowledge-base",
  "tool": "knowledge_search",
  "duration_ms": 340
}
```

---

## OpenClaw Sync Strategy

Two complementary mechanisms:

**Poll (primary):** OpenClaw polls `GET /api/v1/agents/discovery` on a configurable interval (default: 30 seconds). This is OpenClaw's responsibility and requires no push from the platform.

**Push on change (secondary):** When admin enables or disables an agent, the platform fires a local event that triggers `OpenClawSyncService::pushDiscovery()` — a POST to OpenClaw's config reload endpoint (if available). This ensures near-instant tool visibility after admin action without waiting for the poll cycle.

---

## Admin Visibility

`/admin/agents` gains an additional column "OpenClaw" with:
- green badge "Зареєстровано" if the tool appears in the last known discovery payload sent to / confirmed by OpenClaw
- grey badge "Очікується" if the agent is enabled but last sync was more than 2 poll cycles ago
- red badge "Не підключено" if the agent is enabled but OpenClaw sync has failed

A "Синхронізувати" button triggers an immediate discovery push/reload.

---

## Security

- OpenClaw authenticates to the platform invoke bridge using the same `OPENCLAW_GATEWAY_TOKEN` stored in `docker/openclaw/.env`
- The bridge verifies this token on every `/api/v1/agents/invoke` and `/api/v1/agents/discovery` call
- OpenClaw is granted only the minimum: discover tools + invoke via bridge; no direct DB or registry write access
- Audit log records every invoke call: `tool`, `agent`, `actor=openclaw`, `trace_id`, `duration_ms`, `status`

---

## Decisions

### Decision: Per-capability tool entries, not per-agent
- **Why**: OpenClaw tools are single-responsibility; one tool per capability gives OpenClaw fine-grained choice in what to delegate; improves LLM tool selection accuracy
- **Alternative**: one tool per agent — simpler but forces the core-agent to pass intent inside the input, leaking protocol detail to the LLM prompt

### Decision: Platform-owned A2A bridge (no direct agent calls from OpenClaw)
- **Why**: Preserves platform ownership boundary; enables permission checks, audit logging, and request tracing without trusting OpenClaw's security model
- **Alternative**: Give OpenClaw direct agent endpoints — violates ADR `adr_0002_openclaw_role.md` and creates hidden data access paths

### Decision: Poll + push-on-change sync
- **Why**: Poll is resilient (works even if push fails); push-on-change gives fast feedback after admin action; combined approach avoids both staleness and complexity
- **Alternative**: Push-only — fragile if OpenClaw is temporarily down; poll-only — up to 30s lag after admin change
