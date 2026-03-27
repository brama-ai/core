## 1. Topology and Configuration
- [x] 1.1 Add Core E2E runtime configuration (separate service/overlay) with `DATABASE_URL` pointing to `ai_community_platform_e2e`.
- [x] 1.2 Ensure local Postgres provisioning supports creating `ai_community_platform_e2e` idempotently.
- [x] 1.3 Add explicit E2E base URL wiring for test execution against Core E2E runtime.

## 2. Migration-First E2E Flow
- [x] 2.1 Add `make` target (or script) that prepares Core E2E DB and runs Doctrine migrations against it.
- [x] 2.2 Update `make e2e` to run the migration-prepare step before launching E2E tests.
- [x] 2.3 Ensure the E2E command fails fast when DB creation/migration fails.

## 3. Verification
- [x] 3.1 Add/adjust automated checks to validate the E2E runtime uses E2E DB (not default DB).
- [x] 3.2 Verify a config-mutating admin E2E scenario changes only E2E Core DB state.
- [x] 3.3 Verify REST/console E2E flow (`tests/openclaw/a2a_bridge_test.js`) targets Core E2E DB state.
- [x] 3.4 Verify default Core DB state remains unchanged after E2E run.

## 4. Documentation
- [x] 4.1 Update `docs/agent-requirements/e2e-testing.md` with isolated Core E2E DB flow and commands.
- [x] 4.2 Update local developer runbook (`docs/local-dev.md`) with E2E topology notes and troubleshooting.
- [x] 4.3 Document current boundary: Core DB is isolated; agent-owned DBs remain follow-up scope.

## 5. Quality Checks
- [x] 5.1 Run `openspec validate add-core-e2e-test-database-isolation --strict`.
- [x] 5.2 Run targeted E2E tests against the isolated Core E2E runtime.
- [x] 5.3 Run core quality gates (`phpstan`, `php-cs-fixer`, `codecept`) after topology changes.
