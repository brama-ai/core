# Tasks: refactor-agent-discovery

## 0. Preparation

- [x] 0.1 Read `design.md` — understand Traefik API format, state machine, and naming convention
- [x] 0.2 Read `docs/agent-requirements/conventions.md` — understand the full agent contract
- [x] 0.3 Verify Traefik API is accessible: `curl http://traefik:8080/api/http/services` from core container

## 1. Core: Agent Discovery Infrastructure

- [x] 1.1 Create `AgentDiscoveryService` — queries Traefik API, filters `*-agent@docker` services, returns list of internal hostnames
- [x] 1.2 Create `AgentManifestFetcher` — **DEVIATION**: implemented as `AgentCardFetcher` (not `AgentManifestFetcher`). Functionally equivalent; naming follows A2A spec terminology (Agent Card). No rename planned.
- [x] 1.3 Create `AgentConventionVerifier` — validates manifest with inline PHP rules (not JSON Schema library). `config/agent-manifest-schema.json` created as formal contract artifact; inline validation retained per design decision D1.
- [x] 1.4 Create `config/agent-manifest-schema.json` — created (reconciled with existing `config/agent-card.schema.json`; both files maintained)
- [x] 1.5 Create `AgentDiscoveryCommand` (`agent:discovery`) — implemented
- [x] 1.6 Register `AgentDiscoveryCommand` in `services.yaml` with dependencies injected — implemented via Symfony autowiring
- [x] 1.7 Add `agent:discovery` to scheduler (60s interval) — **DEVIATION**: implemented in `SchedulerRunCommand` as a built-in periodic task (time-based, not DB-backed). Symfony Scheduler component not used; platform uses custom `scheduler:run` loop. `config/packages/scheduler.yaml` does not exist and was not created.

## 2. Core: Agent State Machine

- [x] 2.1 Add `violations` (JSON) column to `agent_registry` table — migration `Version20260305000001.php`
- [x] 2.2 Update `AgentRegistryInterface` + `DoctrineAgentRegistry` to support `status` values: `healthy | degraded | unavailable | error`
- [x] 2.3 Update `AgentHealthPollerCommand` — uses new state machine transitions
- [x] 2.4 Write unit tests for `AgentConventionVerifier` — 8 tests total (2 original + 6 new): valid manifest, missing name, missing version, missing a2a_endpoint with capabilities, null input, non-semver version, postgres migration contract (2 cases)

## 3. Core: Admin UI Updates

- [x] 3.1 Update `agents.html.twig` — 4-state badge: `healthy` (green), `degraded` (amber), `unavailable` (grey), `error` (red)
- [x] 3.2 Add violation detail modal — click on `degraded` or `error` badge → modal shows violation list
- [x] 3.3 Add "Run Discovery" button → `POST /admin/agents/discover` → triggers discovery synchronously
- [x] 3.4 Create `AgentRunDiscoveryController` — implemented
- [x] 3.5 Add "Add by URL" button → opens modal with "Функціонал в розробці" message — implemented

## 4. Cleanup: Remove Push Model

- [x] 4.1 Delete `apps/knowledge-agent/src/Command/KnowledgeRegisterCommand.php` — removed
- [x] 4.2 Remove `KnowledgeRegisterCommand` wiring — removed
- [x] 4.3 Remove `knowledge-register` target from root `Makefile` — removed
- [x] 4.4 Add `ai.platform.agent=true` Docker label to `knowledge-agent` — added
- [x] 4.5 Add `ai.platform.agent=true` Docker label to `news-maker-agent` — added

## 5. Convention Test Suite

- [x] 5.1 Create `tests/agent-conventions/package.json` — implemented
- [x] 5.2 Create `tests/agent-conventions/codecept.conf.js` — **DEVIATION**: `.js` not `.ts`. Functionally equivalent; TypeScript not used in convention tests.
- [x] 5.3 Create `tests/agent-conventions/support/manifest-schema.json` — created (mirrors `config/agent-manifest-schema.json`)
- [x] 5.4 Implement `tests/agent-conventions/tests/manifest_test.js` — **DEVIATION**: `.js` not `.ts`. TC-01-01 through TC-01-09 implemented.
- [x] 5.5 Implement `tests/agent-conventions/tests/health_test.js` — **DEVIATION**: `.js` not `.ts`. TC-02-01 through TC-02-04 implemented.
- [x] 5.6 Implement `tests/agent-conventions/tests/a2a_observability_test.js` — **DEVIATION**: `.js` not `.ts`, file named `a2a_observability_test.js` not `a2a_test.ts`. TC-03 baseline implemented.
- [x] 5.7 Add `conventions-test` target to root `Makefile` — implemented (uses `npm install` not `npm ci`)
- [x] 5.8 Run `make conventions-test` — passes for available agents

## 6. Quality Checks

- [x] 6.1 `make analyse` (core) — PHPStan level 8, zero errors
- [x] 6.2 `make cs-check` (core) — no CS violations
- [x] 6.3 `make test` (core) — all Codeception suites pass (381 tests)
- [x] 6.4 `make knowledge-analyse` — PHPStan level 8
- [x] 6.5 `make knowledge-cs-check` — no CS violations
- [x] 6.6 `make knowledge-test` — all Codeception suites pass
- [x] 6.7 `make conventions-test` — TC-01, TC-02, TC-03 pass for available agents

## 7. Documentation

- [x] 7.1 `docs/agent-requirements/conventions.md` — verified accurate against implementation
- [x] 7.2 `docs/agent-requirements/test-cases.md` — verified TC IDs match implementation
- [x] 7.3 Update `docs/setup/local-dev/en/local-dev.md` — added "Adding a new agent" section with checklist reference to conventions.md
- [x] 7.4 Update `AGENTS.md` (repo root) — added "Agent Contract Reference" section with pointer to `docs/agent-requirements/conventions.md`

## Deviation Summary

| Task | Spec | Implementation | Reason |
|------|------|----------------|--------|
| 1.2 | `AgentManifestFetcher` | `AgentCardFetcher` | A2A spec terminology; functionally equivalent |
| 1.3 | JSON Schema validation | Inline PHP validation | Design D1: no new dependency; schema file created as contract artifact |
| 1.7 | `config/packages/scheduler.yaml` | `SchedulerRunCommand` time-based loop | Platform uses custom scheduler, not Symfony Scheduler component |
| 5.2–5.6 | `.ts` files | `.js` files | Convention tests use plain JS; TypeScript not required |
| 5.7 | `npm ci` | `npm install` | Equivalent for local dev; `npm ci` requires lockfile in sync |
