## Context

The platform implements a custom A2A (Agent-to-Agent) protocol inspired by but not fully aligned with the official A2A protocol specification (https://a2aprotocol.org). The official spec defines precise terminology that our implementation diverges from, creating a mapping burden for developers.

### A2A Role Analysis

Core plays a **dual role** — it is simultaneously:
- **A2A Server** for OpenClaw (accepts `POST /api/v1/agents/invoke`, returns results)
- **A2A Client** for agents (sends requests to `hello-agent`, `knowledge-agent`, etc.)

This makes Core an **A2A Gateway** — a proxy that accepts, authorizes, routes, audits, and forwards A2A messages between the orchestrator (OpenClaw) and remote agents.

```
OpenClaw (User/Orchestrator)
    │
    ▼
┌──────────────────────┐
│  Core (A2A Gateway)  │  ← accepts as A2A Server, forwards as A2A Client
│  ┌────────────────┐  │
│  │ SendMessage EP  │  │  POST /api/v1/a2a/send-message
│  │ Discovery EP    │  │  GET  /api/v1/a2a/discovery
│  │ A2AClient       │  │  sends to agent A2A endpoints
│  │ SkillCatalog    │  │  builds skill catalog for OpenClaw
│  └────────────────┘  │
└──────────┬───────────┘
           │
    ┌──────┼──────┐
    ▼      ▼      ▼
hello   knowledge  ...    (A2A Servers / Remote Agents)
-agent  -agent
```

Key terminology gaps identified via audit:

| Our Term | Official A2A Term | Location |
|----------|------------------|----------|
| `manifest` | **Agent Card** | Schema, fetcher, DB field, templates |
| `/api/v1/manifest` | `/.well-known/agent-card.json` | Agent endpoint convention |
| `capabilities` | **Skills** (AgentSkill) | Schema field, discovery builder |
| `capability_schemas` | **Skill schemas** | Schema field |
| `OpenClaw` namespace | **A2A Gateway** | Controller namespace, route names |
| `AgentDiscovery` namespace | **A2A Gateway** | Service namespace |
| `AgentInvokeBridge` | **A2AClient** | Core service (core acts as client) |
| `DiscoveryBuilder` | **SkillCatalogBuilder** | Builds skill catalog |
| `OpenClawSyncService` | **SkillCatalogSyncService** | Pushes catalog to OpenClaw |
| `/api/v1/agents/invoke` | `/api/v1/a2a/send-message` | API route |
| `tool` (audit column) | **skill** | Invoke/audit terminology |

## Goals / Non-Goals

- Goals:
  - 1:1 mapping between our code terminology and official A2A spec
  - New developers can read the A2A spec and immediately understand our codebase
  - Admin UI uses official A2A terms (Agent Card, Skill, A2A Server)
  - `A2AGateway` namespace clearly communicates Core's dual proxy role
  - Dedicated A2A Server info section in admin panel

- Non-Goals:
  - Full JSON-RPC 2.0 compliance (our simplified REST API is sufficient for MVP)
  - Implementing streaming/SSE in this refactor change (tracked separately in `add-a2a-streaming-and-push`)
  - Implementing push notifications in this refactor change (tracked separately in `add-a2a-streaming-and-push`)
  - Changing the actual A2A protocol payload format (only renaming code-level terms)

## Decisions

### D1: Namespace rename strategy — A2AGateway, rename don't duplicate

- **Chosen**: Rename `AgentDiscovery\` → `A2AGateway\` and `Controller\Api\OpenClaw\` → `Controller\Api\A2AGateway\`
- **Why**: `A2AGateway` communicates that Core is a proxy between client (OpenClaw) and servers (agents). Clean break — no backwards-compat shims needed since all consumers are internal.
- **Risk**: Must update all references in services.yaml, tests, and any imports. Grep-verified — limited blast radius.

### D2: Agent Card field naming — keep `a2a_endpoint`, don't rename to `a2a_server_url`

- **Chosen**: Keep `a2a_endpoint` in Agent Card schema. The official spec doesn't define this field (it's our extension), so no alignment benefit from renaming.
- **Why**: Minimizes breaking changes in manifests already deployed in agent containers.

### D3: DB audit table — rename via new migration

- **Chosen**: New migration renames `agent_invocation_audit` → `a2a_message_audit` and `tool` → `skill` column
- **Why**: PostgreSQL `ALTER TABLE RENAME` is atomic and fast even on large tables.
- **Rollback**: Reverse migration renames back.

### D4: Agent-side manifest endpoint — keep `/api/v1/manifest` for now

- **Chosen**: Do NOT change agent endpoints to `/.well-known/agent-card.json` in this change. The refactor-agent-discovery change already standardized on `/api/v1/manifest`. We rename the *concept* (Agent Card) but keep the *URL path* stable.
- **Why**: Changing agent endpoint URLs requires updating all agents + the discovery service simultaneously. Do this in a follow-up if desired.

### D5: Keep `intent` field in A2A request payload

- **Chosen**: Keep the `intent` field name in the JSON payload sent between core→agent.
- **Why**: The official A2A spec uses JSON-RPC with `method: "SendMessage"` and structured Message objects. Our simplified payload format (`{ "intent": "...", "payload": {} }`) is a pragmatic simplification. Renaming `intent` to `method` or `skill` would be confusing because it's not the same as the JSON-RPC method. Keep it as-is.

### D6: Admin UI — A2A Server info section per agent

- Add a collapsible "A2A Server" section to each agent row in the admin table
- Shows: endpoint URL, skills list, skill schemas (if any), last invocation timestamp from audit
- Replaces the current "Деталі маніфесту" section with "Agent Card" heading
- "Можливості" → "Skills"

## Risks / Trade-offs

- **Breaking API routes** → Mitigated: only internal consumers (OpenClaw gateway). Update OpenClaw config in same deployment.
- **DB migration on running system** → Mitigated: `ALTER TABLE RENAME` is instant in PostgreSQL, no downtime.
- **Grep/search disruption** → Developers must search for new names. Mitigated: clean rename, no hybrid naming.

## Open Questions

- None — scope is clear and self-contained.
