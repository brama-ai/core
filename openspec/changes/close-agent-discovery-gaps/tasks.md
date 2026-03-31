# Tasks: close-agent-discovery-gaps

## 1. Agent Manifest JSON Schema

- [x] 1.1 Create `config/agent-manifest-schema.json` — JSON Schema defining required fields (`name`, `version`), optional fields (`a2a_endpoint`, `skills`, `description`, `permissions`, `commands`, `events`), and format constraints (semver for `version`, URL for `a2a_endpoint`)
- [x] 1.2 Verify schema validates correctly against existing agent manifests (knowledge-agent, hello-agent, news-maker-agent)
- [x] 1.3 Create `tests/agent-conventions/support/manifest-schema.json` — copy of core schema for test-side validation

## 2. Scheduled Discovery Polling

- [x] 2.1 Identify the platform's existing scheduler configuration (check `config/packages/scheduler.yaml`, `scheduler:run` command, or cron-based scheduling)
- [x] 2.2 Register `agent:discovery` as a scheduled task with 60-second interval using the platform's existing scheduling mechanism
- [x] 2.3 Verify scheduled execution: confirm `agent:discovery` runs automatically when the scheduler is active

## 3. Admin UI: "Add by URL" Placeholder

- [x] 3.1 Add "Додати за URL" / "Add by URL" button to `src/templates/admin/agents.html.twig` next to the existing "Run Discovery" button
- [x] 3.2 Create Bootstrap modal with message: "Функціонал в розробці. Щоб додати нового агента, додайте його сервіс до compose.yaml з міткою ai.platform.agent=true та перезапустіть стек."
- [x] 3.3 Verify modal opens correctly and does not trigger any backend action

## 4. AgentConventionVerifier Unit Tests

- [x] 4.1 Add test: `testVerifyReturnsErrorWhenNameMissing` — manifest without `name` field returns `error` status with "Required field missing: name" violation
- [x] 4.2 Add test: `testVerifyReturnsErrorWhenVersionMissing` — manifest without `version` field returns `error` status with "Required field missing: version" violation
- [x] 4.3 Add test: `testVerifyReturnsDegradedWhenA2aEndpointMissingWithCapabilities` — manifest with non-empty `skills`/`capabilities` but no `a2a_endpoint` returns `degraded` status
- [x] 4.4 Add test: `testVerifyReturnsErrorForNullInput` — null manifest input returns `error` status
- [x] 4.5 Add test: `testVerifyReturnsHealthyForValidManifest` — complete valid manifest returns `healthy` status with empty violations
- [x] 4.6 Add test: `testVerifyReturnsDegradedForNonSemverVersion` — manifest with non-semver version string returns `degraded` status with warning

## 5. Quality Checks

- [x] 5.1 Run `phpstan analyse` on `src/A2AGateway/` — 0 errors at level 8
- [x] 5.2 Run `php-cs-fixer check` on `src/A2AGateway/` — 0 violations
- [x] 5.3 Run `phpstan analyse` on `tests/Unit/A2AGateway/` — 0 errors at level 8
- [x] 5.4 Run Codeception unit suite — all discovery-related tests pass (381 tests total, 0 failures)
- [x] 5.5 Run `make conventions-test` — TC-01, TC-02, TC-03 pass for available agents (requires running Docker stack)

## 6. Documentation

- [x] 6.1 Verify `docs/agent-requirements/conventions.md` accuracy against current implementation (AgentCardFetcher naming, 4-state model, Docker labels)
- [x] 6.2 Verify `docs/agent-requirements/test-cases.md` TC IDs match convention test implementation
- [x] 6.3 Update `docs/setup/local-dev/en/local-dev.md` — added "Adding a new agent" section with checklist referencing `docs/agent-requirements/conventions.md`
- [x] 6.4 Update root `AGENTS.md` — added "Agent Contract Reference" section with pointer to `docs/agent-requirements/conventions.md`

## 7. Completion Reconciliation

- [x] 7.1 Update `refactor-agent-discovery/tasks.md` — checked off all implemented tasks, added deviation notes (AgentCardFetcher vs AgentManifestFetcher, .js vs .ts, npm install vs npm ci, SchedulerRunCommand vs scheduler.yaml)
- [x] 7.2 Verify `refactor-agent-discovery` is ready for archival (all tasks checked or documented as intentional deviations)
