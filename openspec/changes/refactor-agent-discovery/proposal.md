# Change: Traefik-Based Agent Discovery and Convention Verification

## Why

The current push-based registration model (`knowledge:register` command) forces every agent to
implement its own registration logic and depend on core being available at startup. As the
platform grows to support agents written in different languages (PHP, Python, and others), this
creates duplicated boilerplate and fragile timing dependencies.

Core should be the single owner of agent lifecycle: discovering, registering, verifying, and
monitoring agents — with no cooperation required from the agent beyond exposing a standard
`/api/v1/manifest` endpoint.

## What Changes

- **BREAKING** — Remove push-based registration. `KnowledgeRegisterCommand` is deleted.
- Add `AgentDiscoveryCommand` in core: queries Traefik API on startup + every 60s, discovers
  services matching the `*-agent@docker` naming convention, pulls `/api/v1/manifest` from each.
- Add `AgentConventionVerifier` service: validates manifest schema and endpoint contracts,
  stores violations per-agent, surfaces them in the admin panel.
- Introduce 4-state agent status machine: `healthy → degraded → unavailable → error`.
- Admin panel: state badges with click-to-expand violation detail; "Add by URL" modal stub
  (shows "in development" message — full URL-based provisioning is future scope).
- Add `ai.platform.agent=true` Docker label to all agent services in `compose.yaml`.
- Create `docs/agent-requirements/` — conventions every agent must follow and required test cases.
- Add `tests/agent-conventions/` — Codecept.js + Playwright compliance test suite.
- Add `make conventions-test` Makefile target.
- Update `openspec/AGENTS.md` to require documentation updates in every proposal.

## Impact

- Affected specs: `agent-registry`, `agent-conventions` (new capability)
- Affected code:
  - `apps/brama-core/` — new services, updated commands, updated admin UI and templates
  - `apps/knowledge-agent/` — remove `KnowledgeRegisterCommand`
  - `compose.yaml` — add Docker labels to agent services
  - `Makefile` — add `conventions-test` target
  - `tests/agent-conventions/` — new test suite (new directory)
  - `docs/agent-requirements/` — new documentation (new directory)
- Agents need zero code changes beyond having `GET /api/v1/manifest` + `GET /health` (already implemented)
