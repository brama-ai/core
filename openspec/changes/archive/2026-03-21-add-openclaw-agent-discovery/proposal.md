# Change: OpenClaw Agent Discovery

## Why

The platform's core-agent layer runs in OpenClaw. For OpenClaw to delegate work to specialized agents (knowledge-base, locations-catalog, etc.), it must know which agents are available, what they can do, and how to invoke them. Currently there is no bridge between the platform agent registry and the OpenClaw tool/skill layer. This proposal adds a discovery endpoint that translates the platform's enabled agents into an OpenClaw-readable tool catalog, and defines how OpenClaw invokes agents through the platform's A2A contract.

This is Phase 2 in the agent connectivity story: Phase 1 (`add-admin-agent-registry`) gave admin control over which agents are enabled; Phase 2 makes those enabled agents usable by OpenClaw as tools.

## What Changes

- **Agent Discovery API** — `GET /api/v1/agents/discovery` returns enabled agents as an OpenClaw-compatible tool catalog (name, description, input schema, invocation endpoint)
- **Tool schema translation** — the platform maps each agent's manifest `capabilities` and `config_schema` into an OpenClaw tool definition (JSON Schema input, description from manifest)
- **A2A bridge for OpenClaw** — OpenClaw invokes agents by calling `POST /api/v1/agents/invoke` (platform-owned wrapper); the platform validates the call, routes it via A2A, and returns the A2A response — OpenClaw never calls agent services directly
- **Admin visibility** — `/admin/agents` page gains an "OpenClaw" column: shows which agents are currently registered as OpenClaw tools and their last sync timestamp
- **Sync on registry change** — when an agent is enabled or disabled in the registry, the platform pushes an updated tool catalog to OpenClaw via OpenClaw's config API (or OpenClaw polls the discovery endpoint on a short interval)
- **Security boundary preserved** — OpenClaw has no direct access to agent services; all calls go through the platform A2A bridge which enforces permissions and audit logging

## Impact

- Affected specs: agent-discovery-api (new), openclaw-tool-registration (new)
- Affected code: `apps/brama-core/` (discovery controller, A2A bridge, OpenClaw sync service), admin UI (`/admin/agents`), `docker/openclaw/` (openclaw config for discovery endpoint)
- Depends on: `add-admin-agent-registry` (registry must exist with enabled/disabled state), `add-knowledge-base-agent` (first concrete agent to appear in OpenClaw), `docs/specs/a2a-protocol.md`
- Depends on: OpenClaw being configured and running (see `docker/openclaw/README.md`)
- No breaking changes to existing agent services; OpenClaw remains an optional runtime
