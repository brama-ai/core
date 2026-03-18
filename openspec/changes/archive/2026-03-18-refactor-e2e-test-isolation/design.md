## Context

The platform runs a shared infrastructure stack (Postgres, Redis, RabbitMQ, OpenSearch) with multiple agents, each owning isolated databases/indices. E2E tests must exercise the full stack — including agent admin UIs, A2A messaging, and cross-service flows — without mutating development data.

The current `core-e2e` approach isolates only Core's Postgres database. Agents, OpenClaw, and shared resources (Redis, OpenSearch, RabbitMQ) remain shared between dev and E2E.

### Key constraint
**Agent developers must write ZERO infrastructure code to support E2E testing.** Agents are written in different languages (PHP, Python, potentially Go/Node). Each language has different connection pooling, initialization, and configuration patterns. Requiring per-request DB switching or dual-config support in agent code is error-prone and creates platform-specific coupling.

## Goals / Non-Goals

- Goals:
  - Full data isolation for E2E across all services (Core, agents, OpenClaw)
  - Convention-based resource naming that is predictable and automatic
  - Zero agent-side code changes — isolation is purely via Docker Compose environment overrides
  - A2A message chain stays within the E2E graph (no E2E → prod leakage)
  - `make e2e` remains a single entry command

- Non-Goals:
  - Dynamic start/stop of E2E containers (future work)
  - CI/CD pipeline integration (separate concern)
  - Replacing Codecept/Playwright test stack
  - Agent-level unit/functional test isolation (each agent handles its own)

## Decisions

### Decision 1: Duplicate containers, not dual-config

Run a second container instance of each service with E2E-specific environment variables. The same Docker image, the same code — only `DATABASE_URL`, `REDIS_URL`, `OPENSEARCH_INDEX`, etc. differ.

**Rationale:** Agent code reads `os.environ["DATABASE_URL"]` once at startup. No per-request switching, no connection pool juggling, no middleware. Works identically in PHP, Python, Go, Node, or any other language.

**Alternatives considered:**
- *Header-based per-request switching*: Requires every agent to implement middleware, manage multiple connection pools, handle concurrent prod+test requests. Rejected — too much agent-side complexity.
- *Dual-port entry points*: Each container listens on two ports with different configs. Rejected — requires framework-level changes in each agent and complicates process management.

**Resource overhead:** ~1 GB RAM for all E2E duplicates combined (~25% of total dev stack). Shared infrastructure (Postgres, Redis, OpenSearch, RabbitMQ) is NOT duplicated.

### Decision 2: Namespace isolation within shared infrastructure

Use built-in multi-tenancy features of each infrastructure service:

| Service | Prod resource | Test resource | Isolation mechanism |
|---------|--------------|---------------|-------------------|
| Postgres | `{agent_name}` | `{agent_name}_test` | Separate database |
| Redis | Even DB number (0, 2, 4...) | Odd DB number (1, 3, 5...) | Separate DB number |
| OpenSearch | `{index_name}` | `{index_name}_test` | Separate index |
| RabbitMQ | Default vhost `/` | Vhost `/test` | Separate virtual host |

**Rationale:** No extra containers needed. Each mechanism provides complete data isolation. Cleanup is trivial (`DROP DATABASE`, `FLUSHDB`, `DELETE /index_test`, `delete_vhost`).

### Decision 3: Resource assignment convention

All services follow a predictable naming convention:

**Postgres databases:**
| Service | Prod DB | Test DB | Role |
|---------|---------|---------|------|
| Core | `ai_community_platform` | `ai_community_platform_test` | `app` |
| Knowledge Agent | `knowledge_agent` | `knowledge_agent_test` | `knowledge_agent` |
| News-Maker Agent | `news_maker_agent` | `news_maker_agent_test` | `news_maker_agent` |
| LiteLLM | `litellm` | (not duplicated) | `app` |

**Redis DB assignments:**
| Service | Prod DB | Test DB |
|---------|---------|---------|
| Core (sessions/cache) | 0 | 1 |
| Knowledge Agent | 2 | 3 |
| (future agents) | 4, 6, 8... | 5, 7, 9... |

**BREAKING:** Knowledge agent moves from Redis DB 1 → DB 2. Existing dev Redis data for knowledge-agent will need `FLUSHDB` on DB 1 and re-import to DB 2 (or just accept the loss — it's local dev data).

**OpenSearch indices:**
| Service | Prod index | Test index |
|---------|-----------|------------|
| Knowledge Agent | `knowledge_agent_knowledge_entries` | `knowledge_agent_knowledge_entries_test` |

**RabbitMQ:**
| Context | Vhost |
|---------|-------|
| Prod | `/` (default) |
| Test | `/test` |

### Decision 4: Direct port mapping for E2E services (no Traefik)

E2E containers expose ports directly, bypassing Traefik:

| Service | Prod port (Traefik) | E2E port (direct) |
|---------|--------------------|--------------------|
| Core | 80 | 18080 |
| Knowledge Agent | 8083 | 18083 |
| News-Maker Agent | 8084 | 18084 |
| Hello Agent | 8085 | 18085 |
| OpenClaw Gateway | 8082 (Traefik) + 18789 (direct) | 28789 |

**Rationale:** Avoids polluting Traefik config with E2E entry points. E2E tests don't need to test Traefik routing — that's covered by smoke tests. Agent admin pages don't enforce auth themselves (auth is a Traefik edge-auth middleware concern), so direct access works.

**Edge-auth in E2E tests:** Tests that access agent admin pages via direct ports won't go through Traefik's `edge-auth` middleware. This is acceptable because:
- Auth flow is tested separately in core E2E tests
- Agent admin UI tests focus on the UI functionality, not the auth layer
- The same JWT cookie from `core-e2e` login can still be sent to agents (they just don't validate it)

### Decision 5: E2E A2A routing via openclaw-gateway-e2e

```
E2E test ──→ core-e2e ──→ openclaw-gateway-e2e ──→ agent-e2e containers
                │                                        │
                └── ai_community_platform_test DB         └── agent_test DBs
```

`openclaw-gateway-e2e` is configured with:
- `PLATFORM_CORE_URL: http://core-e2e`
- `PLATFORM_DISCOVERY_URL: http://core-e2e/api/v1/a2a/discovery`
- `PLATFORM_INVOKE_URL: http://core-e2e/api/v1/a2a/send-message`

`core-e2e` agent discovery returns E2E agent endpoints (container names within Docker network).

**Open question:** Core currently discovers agents via Traefik API (`ai.platform.agent=true` label). For E2E, core-e2e needs to discover E2E agents. Options:
- a) Add env var `AGENT_DISCOVERY_ENDPOINTS` for static agent list in E2E mode
- b) Use a different Traefik label for E2E agents (`ai.platform.agent=e2e`)
- c) Core-e2e performs manual agent registration via internal API during `e2e-prepare`

Option (c) is simplest — no core code changes needed. The `e2e-prepare` Makefile target calls `POST /api/v1/internal/agents/register` on `core-e2e` for each agent, pointing to E2E container URLs.

### Decision 6: Unified Postgres init script

Replace the current per-service init scripts with a unified approach:

```
docker/postgres/init/
├── 01_create_roles.sql          # All roles (knowledge_agent, news_maker_agent)
├── 02_create_databases.sql      # All prod databases
├── 03_create_test_databases.sql # All test databases (_test suffix)
```

This ensures a fresh `docker compose up` provisions everything needed for both dev and E2E.

### Decision 7: Inline E2E services via Docker Compose profiles (zero new files)

E2E service duplicates live inside the same compose file as their prod counterpart, gated by `profiles: [e2e]`:

```yaml
# compose.core.yaml
services:
  core:
    # ...prod config, always starts...

  core-e2e:
    profiles: [e2e]
    build:
      context: .
      dockerfile: docker/core/Dockerfile
    environment:
      DATABASE_URL: postgresql://app:app@postgres:5432/ai_community_platform_test?serverVersion=16&charset=utf8
    ports:
      - "18080:80"
    # ...same volumes, network...
```

```yaml
# compose.agent-knowledge.yaml
services:
  knowledge-agent:
    # ...prod config...

  knowledge-agent-e2e:
    profiles: [e2e]
    build: ...
    environment:
      DATABASE_URL: postgresql://knowledge_agent:knowledge_agent@postgres:5432/knowledge_agent_test?...
      REDIS_URL: redis://redis:6379/3
      OPENSEARCH_INDEX: knowledge_agent_knowledge_entries_test
      RABBITMQ_URL: amqp://app:app@rabbitmq:5672/test
      PLATFORM_CORE_URL: http://core-e2e
    ports:
      - "18083:80"
```

**Behaviour:**
- `docker compose up -d` → starts only prod services (no `profiles` = always on)
- `docker compose --profile e2e up -d` → starts prod + e2e services
- `make up` → prod only
- `make e2e` → uses `--profile e2e`

**Rationale:** Zero new compose files. Each service's e2e twin lives next to the prod definition — easy to find, easy to keep in sync. The `profiles` mechanism is a built-in Docker Compose feature (v2.1+), no extensions needed.

**Alternatives considered:**
- *Separate `compose.e2e.yaml`*: One file for all E2E services. Rejected — separates e2e config from its prod counterpart, making it easy to drift out of sync when agent config changes.
- *Current `compose.core.e2e.yaml`*: Only covers core. Rejected — does not scale to agents.

`compose.core.e2e.yaml` is removed. All E2E services connect to the same `dev-edge` network (access shared infra).

## Risks / Trade-offs

- **~1 GB RAM overhead** when E2E containers are running.
  - Mitigation: E2E containers only run during `make e2e`; future work adds dynamic start/stop.
- **Knowledge agent Redis DB reassignment** breaks existing dev Redis data.
  - Mitigation: `redis-cli -n 1 FLUSHDB` during migration; dev data is transient.
- **E2E agents bypass Traefik edge-auth** (direct port access).
  - Mitigation: Auth is tested by core E2E tests; agent UI tests focus on functionality.
- **Agent discovery in E2E** requires manual registration or static config.
  - Mitigation: Option (c) — `e2e-prepare` registers agents via internal API.

## Migration Plan

1. Create unified Postgres init scripts (roles + prod DBs + test DBs).
2. Update `compose.agent-knowledge.yaml` Redis DB from 1 → 2.
3. Add `profiles: [e2e]` service definitions inline to each compose file (core, agents, openclaw).
4. Remove `compose.core.e2e.yaml`.
5. Update Makefile: use `--profile e2e` in E2E targets, update `e2e-prepare` with full provisioning + migration + agent registration.
6. Update all E2E test files to use E2E ports (18083-18085, 28789).
7. Update `codecept.conf.js` with E2E environment variables.
8. Update documentation.
9. Run full E2E suite to verify.

## Rollback Plan

- Remove `profiles: [e2e]` services from compose files.
- Restore old `compose.core.e2e.yaml`.
- Revert knowledge-agent Redis DB back to 1.
- Revert Makefile targets.
- Revert init scripts.
- E2E tests continue to work with partial core-only isolation.

## Open Questions

- Should `make e2e-smoke` use E2E containers or remain against prod (read-only smoke tests)?
- Should E2E RabbitMQ vhost `/test` be provisioned in init script or in `e2e-prepare`?
