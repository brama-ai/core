# Change: Refactor E2E Test Isolation to Full-Stack Duplicate Containers

## Why
The current E2E isolation covers only Core (`core-e2e` with `ai_community_platform_e2e` DB). Agent-facing E2E tests still hit production agent containers and their databases. When a news-maker admin test creates a source, it mutates the dev `news_maker_agent` DB. When a knowledge admin test toggles settings, it changes real dev state.

Additionally, the current approach has no A2A isolation: if `core-e2e` sends a message to an agent, it reaches the prod agent instance reading prod data. This makes E2E results non-deterministic and pollutes development state.

We need a unified approach where every service has an E2E duplicate container connected to isolated data stores, with zero changes required in agent business code.

## What Changes
- **BREAKING**: Remove `compose.core.e2e.yaml`. E2E service definitions move inline into their respective compose files using Docker Compose `profiles: [e2e]`.
- **BREAKING**: Rename `ai_community_platform_e2e` DB to `ai_community_platform_test` (convention: `_test` suffix).
- **BREAKING**: Reassign knowledge-agent Redis DB from `1` to `2` (convention: even=prod, odd=test).
- Add E2E service definitions (with `profiles: [e2e]`) to each existing compose file:
  - `compose.core.yaml` → add `core-e2e`
  - `compose.agent-knowledge.yaml` → add `knowledge-agent-e2e`, `knowledge-worker-e2e`
  - `compose.agent-news-maker.yaml` → add `news-maker-agent-e2e`
  - `compose.agent-hello.yaml` → add `hello-agent-e2e`
  - `compose.openclaw.yaml` → add `openclaw-gateway-e2e`
- Add unified Postgres init script that provisions both prod and test databases for all services.
- Add `knowledge_agent` role + DB to init scripts (currently missing).
- Update Makefile to use `--profile e2e` for E2E targets; `make up` starts only prod services.
- Update Makefile `e2e-prepare` to provision all E2E databases, run all migrations (Core Doctrine, Knowledge Doctrine, News-Maker Alembic), and start all E2E containers.
- Update all E2E tests to target E2E agent URLs (port 18083-18085) instead of prod Traefik ports (8083-8085).
- Update `docs/agent-requirements/e2e-testing.md` with the unified approach and conventions.
- Supersedes `add-core-e2e-test-database-isolation` proposal.
- Zero new compose files created.

## Impact
- Affected specs: `e2e-testing`, `local-dev-runtime`
- Affected code:
  - `compose.core.e2e.yaml` (removed)
  - `compose.core.yaml` (add core-e2e with `profiles: [e2e]`)
  - `compose.agent-knowledge.yaml` (Redis DB reassignment + add e2e services with `profiles: [e2e]`)
  - `compose.agent-news-maker.yaml` (add e2e service with `profiles: [e2e]`)
  - `compose.agent-hello.yaml` (add e2e service with `profiles: [e2e]`)
  - `compose.openclaw.yaml` (add e2e gateway with `profiles: [e2e]`)
  - `compose.yaml` shared infrastructure (no changes)
  - `docker/postgres/init/` (unified init scripts)
  - `Makefile` (E2E targets use `--profile e2e`)
  - `tests/e2e/` (all test files, codecept config, page objects)
  - `docs/agent-requirements/e2e-testing.md`
  - `docs/local-dev.md`
- Depends on: none
- Supersedes: `add-core-e2e-test-database-isolation`
