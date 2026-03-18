# Change: Align A2A implementation with official A2A protocol terminology

## Why

Our implementation uses ad-hoc naming (`manifest`, `capabilities`, `OpenClaw` namespace, `AgentInvokeBridge`, `intent`) that diverges from the official A2A protocol specification (docs/fetched/a2a-protocol-org/en). This makes it harder for developers to map our code to the spec, and creates confusion as we grow the platform.

The official A2A protocol defines precise terms: **Agent Card** (not "manifest"), **Skill** (not "capability"), **A2A Client** / **A2A Server** (not "OpenClaw" in route context).

Core plays a **gateway/proxy** role: it is an A2A Server for OpenClaw (accepts requests) and an A2A Client for agents (forwards requests). The new `A2AGateway` namespace reflects this dual role.

## What Changes

### 1. Terminology Rename — Agent Card (was "manifest")

- **BREAKING** — Rename `manifest` concept to `Agent Card` in code, schema, and UI
- Rename `agent-manifest.schema.json` → `agent-card.schema.json`
- Rename `AgentManifestFetcher` → `AgentCardFetcher`
- Update JSON Schema `$id` and `title` to "Agent Card"
- Agent-side endpoint URL (`/api/v1/manifest`) stays unchanged (D4 in design.md)

### 2. Terminology Rename — Skills (was "capabilities")

- Rename `capabilities` → `skills` in Agent Card schema
- Rename `capability_schemas` → `skill_schemas` in Agent Card schema
- Update catalog builder to use `skills` field
- Update admin template to display "Skills" instead of "Можливості"

### 3. Namespace Refactor — A2AGateway (was "AgentDiscovery" + "OpenClaw")

- Rename `App\AgentDiscovery\` → `App\A2AGateway\` (gateway between client and servers)
- Rename `Controller\Api\OpenClaw\` → `Controller\Api\A2AGateway\`
- Rename classes:
  - `AgentInvokeBridge` → `A2AClient` (sends messages to remote A2A Servers)
  - `DiscoveryBuilder` → `SkillCatalogBuilder` (builds skill catalog for OpenClaw)
  - `AgentManifestFetcher` → `AgentCardFetcher`
  - `OpenClawSyncService` → `SkillCatalogSyncService`
- Rename route names: `api_openclaw_invoke` → `api_a2a_send_message`, `api_openclaw_discovery` → `api_a2a_discovery`
- Rename URL paths: `/api/v1/agents/invoke` → `/api/v1/a2a/send-message`, `/api/v1/agents/discovery` → `/api/v1/a2a/discovery`

### 4. Admin UI — New "A2A Server" section

- Add "A2A Server" collapsible section per agent (visible only when `a2a_endpoint` is set)
- Show: A2A endpoint URL, skills list with descriptions/schemas, last invocation timestamp
- Rename admin labels: "Деталі маніфесту" → "Agent Card", "Можливості" → "Skills"

### 5. DB alignment

- Rename `agent_invocation_audit` → `a2a_message_audit` (new migration)
- Rename column `tool` → `skill` in audit table
- Keep `a2a_endpoint` field name in Agent Card (D2 in design.md)

## Impact

- Affected specs: `a2a-server` (new capability), `agent-registry` (modified — rename fields)
- Affected code:
  - `apps/core/src/AgentDiscovery/` → `apps/core/src/A2AGateway/`
  - `apps/core/src/Controller/Api/OpenClaw/` → `Controller/Api/A2AGateway/`
  - `apps/core/config/agent-manifest.schema.json` → `agent-card.schema.json`
  - `apps/core/templates/admin/agents.html.twig` — updated labels + A2A Server section
  - `apps/core/config/services.yaml` — updated references
  - `apps/core/tests/` — updated test names and references
  - `apps/hello-agent/manifest.json` + `apps/knowledge-agent/manifest.json` — `capabilities` → `skills`
  - DB migration for audit table rename
- **BREAKING**: API route paths change, Agent Card field names change
