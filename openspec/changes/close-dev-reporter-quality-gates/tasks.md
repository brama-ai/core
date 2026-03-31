# Tasks: Close Dev Reporter Agent Quality Gates and Add E2E Tests

## 1. Static Analysis (PHPStan)

- [x] 1.1 Run `make dev-reporter-analyse` and capture all errors
- [x] 1.2 Fix all PHPStan errors in `apps/dev-reporter-agent/` (level 8, zero errors required)
- [x] 1.3 Re-run `make dev-reporter-analyse` and confirm zero errors

## 2. Code Style (PHP CS Fixer)

- [x] 2.1 Run `make dev-reporter-cs-check` and capture all violations
- [x] 2.2 Run `make dev-reporter-cs-fix` to auto-fix violations
- [x] 2.3 Review any remaining manual fixes needed
- [x] 2.4 Re-run `make dev-reporter-cs-check` and confirm zero violations

## 3. Unit & Functional Tests

- [x] 3.1 Run `make dev-reporter-test` and capture results
- [x] 3.2 Fix any failing tests
- [x] 3.3 Re-run `make dev-reporter-test` and confirm all tests pass

## 4. Agent Convention Compliance

- [x] 4.1 Run `make conventions-test` and capture results
- [x] 4.2 Fix any convention violations for dev-reporter-agent
- [x] 4.3 Re-run `make conventions-test` and confirm all tests pass

## 5. E2E Test Implementation

- [x] 5.1 Create page object `tests/e2e/support/pages/DevReporterPage.js` with selectors for reports list table, status filter, and report detail elements
- [x] 5.2 Register `devReporterPage` in `tests/e2e/codecept.conf.js` include section
- [x] 5.3 Create `tests/e2e/tests/admin/dev_reporter_admin_test.js` with scenarios:
  - Agent health endpoint returns ok (via Traefik, with edge auth)
  - Agent manifest is valid (via Traefik, with edge auth, verifies 3 skills)
  - Admin reports list page loads with table and expected columns
  - Admin reports list page supports status filter (all/passed/failed)
- [x] 5.4 Follow existing patterns from `hello_agent_test.js` and `knowledge_admin_test.js`:
  - Use `isDevReporterAvailable()` guard with graceful skip
  - Use `DEV_REPORTER_URL` env var (default `http://localhost:18087`)
  - Tag with `@admin` and `@dev-reporter`
  - Use `Before` hook with `loginPage.loginAsAdmin()`

## 6. E2E Verification

- [x] 6.1 Run `make e2e` and confirm all E2E tests pass (including new dev-reporter tests)
- [x] 6.2 Run `make e2e-smoke` and confirm smoke tests pass

## 7. Documentation

- [x] 7.1 Add CUJ entries to `docs/agent-requirements/e2e-cuj-matrix.md`:
  - CUJ-23: Dev Reporter → admin → reports list → filter by status
  - CUJ-24: Dev Reporter → health + manifest via Traefik
- [x] 7.2 Update `docs/agent-requirements/e2e-testing.md` environment variables table with `DEV_REPORTER_URL`

## 8. Archive Completion

- [x] 8.1 Mark all section 9 tasks as `[x]` in `openspec/changes/archive/2026-03-21-add-dev-reporter-agent/tasks.md`
