# Change: Close Dev Reporter Agent Quality Gates and Add E2E Tests

## Why

The dev-reporter-agent implementation (archived as `2026-03-21-add-dev-reporter-agent`) is 95% complete. All functional code, database migrations, A2A skills, admin panel, pipeline integration, unit tests, functional tests, and documentation are done. However, the quality gate tasks (section 9 of the original tasks.md) were never closed: PHPStan analysis, CS Fixer checks, unit/functional test execution, and agent convention compliance were not verified. Additionally, the agent has no E2E test coverage beyond basic health/manifest smoke checks in `services_accessibility_test.js`. The CUJ matrix has no entry for dev-reporter admin flows.

This proposal closes the remaining quality gates and adds dedicated E2E tests to bring the dev-reporter-agent to full production readiness.

## What Changes

### Quality Gate Closure
- Run `make dev-reporter-analyse` (PHPStan level 8) and fix all errors
- Run `make dev-reporter-cs-check` (PHP CS Fixer) and fix all violations
- Run `make dev-reporter-test` (Codeception) and ensure all tests pass
- Run `make conventions-test` and ensure agent compliance tests pass

### New E2E Tests
- Add `tests/e2e/tests/admin/dev_reporter_admin_test.js` covering:
  - Agent health endpoint verification via Traefik
  - Admin panel reports list page (table, columns, sorting)
  - Admin panel report detail/filter page (status filter)
- Add `tests/e2e/support/pages/DevReporterPage.js` page object
- Register page object in `codecept.conf.js`
- Add CUJ entries to `docs/agent-requirements/e2e-cuj-matrix.md`
- Run `make e2e` to verify all E2E tests pass

### Archived Task Completion
- Mark all section 9 tasks as done in `openspec/changes/archive/2026-03-21-add-dev-reporter-agent/tasks.md`

## Impact

- Affected specs: `dev-reporter` (MODIFIED — adds E2E testing requirement), `e2e-testing` (ADDED — dev-reporter CUJ coverage)
- Affected code:
  - `apps/dev-reporter-agent/` — PHPStan/CS Fixer fixes (code quality only, no behavior changes)
  - `tests/e2e/tests/admin/dev_reporter_admin_test.js` — new E2E test file
  - `tests/e2e/support/pages/DevReporterPage.js` — new page object
  - `tests/e2e/codecept.conf.js` — page object registration
  - `docs/agent-requirements/e2e-cuj-matrix.md` — new CUJ entries
  - `openspec/changes/archive/2026-03-21-add-dev-reporter-agent/tasks.md` — mark tasks done
- No breaking changes
- No new dependencies
- No database migrations
