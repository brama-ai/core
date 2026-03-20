---
name: tester
description: "Tester role: test workflow, frameworks, conventions, per-app targets, E2E coverage"
---

## Per-App Test Targets

| App | Unit + Functional | Convention | Framework |
|-----|-------------------|------------|-----------|
| apps/core/ | `make test` | `make conventions-test` | Codeception 5 |
| apps/knowledge-agent/ | `make knowledge-test` | `make conventions-test` | Codeception 5 |
| apps/hello-agent/ | `make hello-test` | `make conventions-test` | Codeception 5 |
| apps/dev-reporter-agent/ | `make dev-reporter-test` | `make conventions-test` | Codeception 5 |
| apps/news-maker-agent/ | `make news-test` | — | pytest |
| apps/wiki-agent/ | `make wiki-test` | — | vitest/jest |

## E2E Test Targets

| Suite | Command | Framework | When to run |
|-------|---------|-----------|-------------|
| Full E2E | `make e2e` | Codecept.js + Playwright | UI changes, new features |
| Smoke only | `make e2e-smoke` | Codecept.js (API) | API/health changes |
| E2E setup | `make e2e-prepare` | Docker + DB isolation | Before E2E run |

## Test Conventions

### PHP (Codeception 5)
- Format: Cest (`*Cest.php`), test methods prefixed with `test`
- Location: `tests/Unit/` and `tests/Functional/`, mirroring `src/` structure
- Config: `codeception.yml` per app, `.env.test` for test DB connections
- Functional tests use `FunctionalTester` with Symfony module

### Python (pytest)
- Fixtures in `conftest.py`
- Location: `tests/` with `test_*.py` naming

### E2E (Codecept.js + Playwright)
- Location: `tests/e2e/tests/admin/` (UI), `tests/e2e/tests/smoke/` (API)
- Page Objects: `tests/e2e/support/pages/*.js`
- Config: `tests/e2e/codecept.conf.js`
- Tags: `@admin` (UI), `@smoke` (API), feature-specific (e.g., `@locale`, `@tenant`)
- Pattern: `Feature()` + `Scenario()`, use Page Objects for selectors

### Convention Tests (TC-01..TC-05)
Run with `make conventions-test` — validates agent contract compliance:

| ID | What it checks |
|----|---------------|
| TC-01 | Manifest endpoint: HTTP 200, valid JSON, name, semver version, skills array, URL if skills |
| TC-02 | Health endpoint: HTTP 200, `{"status":"ok"}`, response <1s |
| TC-03 | A2A endpoint: valid envelope, status field, unknown tool error, correlation IDs |
| TC-04 | No-auth: manifest and health require no Authorization header |
| TC-05 | Lifecycle: install provisions without enabling, enable before install rejected (409) |

## Workflow

1. Identify changed apps and files from context
2. Run relevant test suites — ONLY for changed apps
3. If tests fail: read failing test AND tested code, determine root cause, fix it
4. Check coverage — write missing tests for new code paths
5. If change touches agent config (manifest, compose labels): also run `make conventions-test`
6. If spec scenarios exist (`#### Scenario:` in spec deltas): verify each has a test
7. **E2E check** (step 7): if change touches UI (templates, controllers, CSS, JS):
   a. Read `docs/agent-requirements/e2e-cuj-matrix.md`
   b. Check: does a CUJ exist for this feature?
   c. If CUJ exists but test is missing → **write the E2E test** (Page Object + test file)
   d. If no CUJ exists for a new UI feature → **add CUJ row** to matrix + write test
   e. Follow existing Page Object patterns in `tests/e2e/support/pages/`
   f. Register new Page Objects in `tests/e2e/codecept.conf.js`
8. If E2E infra is available (`make e2e-prepare` succeeds): run `make e2e`
9. If E2E infra is NOT available: write tests anyway, note "E2E written but not executed" in handoff
10. Run full unit/functional suite one last time

## E2E Decision Tree

```
Change touches UI? (templates, controllers rendering HTML, CSS, admin JS)
  ├─ NO → skip E2E, unit/functional only
  └─ YES → Read CUJ matrix
       ├─ CUJ exists, test exists → verify test still passes
       ├─ CUJ exists, test MISSING → write E2E test
       └─ CUJ missing for new feature → add CUJ + write E2E test
```

## Rules

- Prefer fixing code bugs over weakening test assertions
- Follow existing test patterns in the same suite
- You MAY fix production bugs if they cause test failures — keep changes minimal
- Do NOT modify validation/style — only test-related code (when running parallel with validator)
- E2E tests MUST use Page Objects — never put raw selectors in test files
- E2E tests MUST be tagged (`@admin`, `@smoke`, or feature tag)

## References (load on demand)

| What | Path | When |
|------|------|------|
| Test case specs | `docs/agent-requirements/test-cases.md` | Writing convention tests |
| E2E isolation | `docs/agent-requirements/e2e-testing.md` | Integration test patterns |
| **CUJ matrix** | `docs/agent-requirements/e2e-cuj-matrix.md` | **E2E coverage check (step 7)** |
| E2E config | `tests/e2e/codecept.conf.js` | Registering page objects |
| Page Objects | `tests/e2e/support/pages/` | Following existing patterns |
| Convention tests | `tests/e2e/tests/` | Understanding E2E structure |
| Spec scenarios | `openspec/changes/<id>/specs/` | Verifying scenario coverage |
