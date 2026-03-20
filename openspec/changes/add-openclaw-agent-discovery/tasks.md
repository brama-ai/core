# Tasks: add-openclaw-agent-discovery

## 0. Prerequisites

- [x] 0.1 Verify `add-admin-agent-registry` is applied and `agent_registry` table exists
- [x] 0.2 Verify `OPENCLAW_GATEWAY_TOKEN` is set in `docker/openclaw/.env` and accessible as platform env var

## 1. Discovery Endpoint

- [x] 1.1 Create `DiscoveryBuilder` service: reads enabled agents from `AgentRegistryRepository::findEnabled()`, maps each capability to a tool definition with `input_schema`
- [x] 1.2 Implement capability schema resolution: use `manifest.capability_schemas[cap]` if present; fall back to `{ "type": "object" }`
- [x] 1.3 Create `GET /api/v1/agents/discovery` controller with gateway token auth guard
- [x] 1.4 Cache discovery payload in Redis with 30s TTL (invalidated on registry change, same as registry cache)
- [x] 1.5 Write unit tests for `DiscoveryBuilder`: multiple agents, disabled agents excluded, schema fallback
- [x] 1.6 Write functional tests for discovery endpoint: auth, response structure, disabled agent absent

## 2. A2A Invoke Bridge

- [x] 2.1 Create `AgentInvokeBridge` service: resolves tool → agent, validates enabled state, constructs A2A request, calls `agent.a2a_endpoint`, returns A2A response
- [x] 2.2 Create `POST /api/v1/agents/invoke` controller with gateway token auth guard
- [x] 2.3 Implement invoke audit logger: write to `agent_invocation_audit` (agent, tool, trace_id, request_id, duration_ms, status, actor)
- [x] 2.4 Create Postgres migration for `agent_invocation_audit` table
- [x] 2.5 Write unit tests for `AgentInvokeBridge`: success path, disabled agent rejection, unknown tool rejection
- [x] 2.6 Write functional tests for invoke endpoint: valid call, disabled agent, missing tool, bad token

## 3. OpenClaw Sync Service

- [x] 3.1 Create `OpenClawSyncService`: `pushDiscovery()` method — POST updated discovery payload to OpenClaw config reload URL (configurable via `OPENCLAW_DISCOVERY_PUSH_URL` env)
- [x] 3.2 Wire `OpenClawSyncService::pushDiscovery()` to fire after `agent.enabled` and `agent.disabled` registry events (synchronous call in controllers; Messenger deferred to future iteration)
- [x] 3.3 Implement sync status tracking: persist last push attempt timestamp + result to Redis key `openclaw_sync_status`
- [x] 3.4 Write unit tests for `OpenClawSyncService`: push success, push failure (no exception propagated), retry on next poll

## 4. OpenClaw Configuration

- [x] 4.1 Document OpenClaw discovery polling configuration in `docker/openclaw/README.md` (discovery URL, token header, poll interval)
- [x] 4.2 Add example OpenClaw config snippet for discovery and A2A bridge endpoint to `docker/openclaw/` (as a comment or template file)

## 5. Admin UI Update

- [x] 5.1 Add "OpenClaw" column to `/admin/agents` agent table: green/amber/red badge based on sync status from Redis
- [x] 5.2 Implement "Синхронізувати" button: calls new `POST /admin/agents/sync` endpoint that triggers `OpenClawSyncService::pushDiscovery()` for all enabled agents
- [x] 5.3 Show tooltip on red badge: display last error message from sync status
- [x] 5.4 Write E2E test (Playwright): sync badge state visible, manual sync button triggers status update

## 6. Network Isolation

- [x] 6.1 Ensure agent service containers in `compose.yaml` are on an internal-only network not accessible from the OpenClaw container
- [x] 6.2 Verify OpenClaw container can only reach `apps/core` platform API (not knowledge-agent, etc.) via network policy

## 7. Quality

- [x] 7.1 Run `phpstan analyse` — zero errors at level 8
- [x] 7.2 Run `php-cs-fixer check` — no violations
- [x] 7.3 Run `codecept run` — all suites pass
- [x] 7.4 Run `make e2e` — Playwright passes
