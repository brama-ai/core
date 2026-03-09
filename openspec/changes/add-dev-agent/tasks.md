# Tasks: Add Dev Agent

## 1. Infrastructure

- [x] Add Claude Opus 4.6 to `docker/litellm/config.yaml` (OpenRouter)
- [x] Add `dev-agent` entrypoint `:8088` to `docker/traefik/traefik.yml`
- [x] Add port 8088 to Traefik in `compose.yaml`
- [x] Add `dev_agent` role to `docker/postgres/init/01_create_roles.sql`
- [x] Add `dev_agent` database to `docker/postgres/init/02_create_databases.sql`
- [x] Add `dev_agent_test` database to `docker/postgres/init/03_create_test_databases.sql`
- [x] Create `docker/dev-agent/Dockerfile` (PHP 8.5 + Apache + git + gh CLI)
- [x] Create `docker/dev-agent/entrypoint.sh` (migrations + background worker)
- [x] Create `compose.agent-dev.yaml` with agent + e2e services

## 2. Scaffold Agent

- [x] Create `apps/dev-agent/` with Symfony 7 skeleton
- [x] Set up `composer.json` with Symfony 7, Doctrine DBAL, Twig, Monolog
- [x] Create config files (framework, doctrine, twig, monolog, routing, cache)
- [x] Copy Logging infrastructure from dev-reporter (OpenSearch handler, TraceContext, etc.)
- [x] Add health endpoint (`GET /health → {"status": "ok"}`)
- [x] Add manifest endpoint (`GET /api/v1/manifest`) with 4 skills declared
- [x] Create `.env`, `.env.test`, `.gitignore`, `phpstan.neon`, `.php-cs-fixer.dist.php`, `codeception.yml`

## 3. Database & Migrations

- [x] Create migration `Version20260309000001` for `dev_tasks` table
- [x] Create migration `Version20260309000002` for `dev_task_logs` table
- [x] Create `DevTaskRepository` with DBAL (insert, findById, findRecent, updateStatus, getStats)
- [x] Create `DevTaskLogRepository` with DBAL (append, findByTaskId, countByTaskId)

## 4. A2A Handler & Skills

- [x] Create `DevAgentA2AHandler` with intent routing
- [x] Implement `dev.create_task` — validate payload, store to DB
- [x] Implement `dev.run_pipeline` — validate task state, set status to pending
- [x] Implement `dev.get_status` — query task + log count
- [x] Implement `dev.list_tasks` — query recent tasks with optional filter
- [x] Create `A2AController` (POST `/api/v1/a2a`)

## 5. Services

- [x] Create `LlmService` — LiteLLM chat completions client with system prompt
- [x] Create `PipelineRunner` — wraps pipeline.sh via proc_open, captures output line-by-line
- [x] Create `GitHubService` — git push + gh pr create
- [x] Create `PipelineWorkerCommand` — background worker polling for pending tasks

## 6. Admin Panel

- [x] Create `TaskAdminController` with index, create, detail routes
- [x] Create `TaskApiController` with refine, create, start, SSE logs endpoints
- [x] Create `index.html.twig` — task list with status badges, filters, stats
- [x] Create `create.html.twig` — task creation form with Opus chat refinement UI
- [x] Create `detail.html.twig` — task detail with SSE live log viewer

## 7. SSE Live Logs

- [x] Implement SSE endpoint `GET /admin/tasks/api/{id}/logs/stream`
- [x] StreamedResponse with 1s poll interval, 15s heartbeat
- [x] Frontend EventSource with auto-reconnect and `last_id` tracking
- [x] `complete` event when task reaches terminal status

## 8. Makefile

- [x] Add targets: `dev-agent-setup`, `dev-agent-install`, `dev-agent-migrate`
- [x] Add targets: `dev-agent-test`, `dev-agent-analyse`, `dev-agent-cs-check`, `dev-agent-cs-fix`
- [x] Include `dev-agent-setup` in `make setup`

## 9. Tests

- [x] Unit test for `DevAgentA2AHandler` (all 4 intents + unknown)
- [x] Unit test for `LlmService` (instantiation)
- [x] Test support files (bootstrap, UnitTester, FunctionalTester)

## 10. Documentation

- [x] Create `docs/agents/ua/dev-agent.md`
- [x] Create `docs/agents/en/dev-agent.md`

## 11. Quality Checks

- [x] `make dev-agent-setup` — container builds, composer install succeeds
- [x] `make dev-agent-analyse` — zero PHPStan errors
- [x] `make dev-agent-cs-check` — no CS violations
- [x] `make dev-agent-test` — all tests pass
