# Proposal: Add E2E CUJ Coverage

## Status: approved

## Problem

15 admin UI features lack E2E test coverage. The platform has 27 admin UI pages but only 12 have E2E tests. Critical features like Coder Agent (5 pages), Locale Switching, Settings, and Log Trace Visualization are completely untested at the browser level.

The tester and auditor pipeline agents were recently updated to check CUJ coverage, but the CUJ matrix only tracks 13 journeys — many existing features are not listed.

## Proposed Solution

Add E2E tests for all missing Critical User Journeys. Create Page Objects for untested UI components. Update CUJ matrix to track all admin UI features.

### Scope

**Phase 1 — High Priority (8 CUJs)**

| CUJ | Journey | Page Object | Test File |
|-----|---------|-------------|-----------|
| CUJ-07 | Locale switch → UI translates | `LocalePage.js` | `locale_switch_test.js` |
| CUJ-14 | Settings → change log level → save | `SettingsPage.js` | `settings_test.js` |
| CUJ-15 | Coder → create task → see in list | `CoderPage.js` | `coder_dashboard_test.js` |
| CUJ-16 | Coder → open task → see detail + logs | `CoderPage.js` | `coder_detail_test.js` |
| CUJ-17 | Coder → task events (SSE live updates) | `CoderPage.js` | `coder_events_test.js` |
| CUJ-18 | Agent → open settings → configure | `AgentSettingsPage.js` | `agent_settings_test.js` |
| CUJ-19 | Logs → open trace → see sequence diagram | `LogTracePage.js` | `log_trace_test.js` |
| CUJ-22 | Dashboard → see all metric cards | `DashboardPage.js` (existing) | update `dashboard_test.js` |

**Phase 2 — Medium Priority (2 CUJs)**

| CUJ | Journey | Page Object | Test File |
|-----|---------|-------------|-----------|
| CUJ-20 | Scheduler → create job with delivery | `SchedulerPage.js` (existing) | update `scheduler_test.js` |
| CUJ-21 | Scheduler → view job execution logs | `SchedulerPage.js` (existing) | `scheduler_logs_test.js` |

### Files to Create

**Page Objects (5 new):**
- `tests/e2e/support/pages/LocalePage.js`
- `tests/e2e/support/pages/SettingsPage.js`
- `tests/e2e/support/pages/CoderPage.js`
- `tests/e2e/support/pages/LogTracePage.js`
- `tests/e2e/support/pages/AgentSettingsPage.js`

**Test Files (8 new):**
- `tests/e2e/tests/admin/locale_switch_test.js`
- `tests/e2e/tests/admin/settings_test.js`
- `tests/e2e/tests/admin/coder_dashboard_test.js`
- `tests/e2e/tests/admin/coder_detail_test.js`
- `tests/e2e/tests/admin/coder_events_test.js`
- `tests/e2e/tests/admin/agent_settings_test.js`
- `tests/e2e/tests/admin/log_trace_test.js`
- `tests/e2e/tests/admin/scheduler_logs_test.js`

**Files to Update:**
- `tests/e2e/codecept.conf.js` — register new Page Objects
- `tests/e2e/tests/admin/dashboard_test.js` — add metrics verification
- `tests/e2e/tests/admin/scheduler_test.js` — add delivery channel scenarios
- `docs/agent-requirements/e2e-cuj-matrix.md` — add CUJ-14..CUJ-22

### Constraints

- Follow existing Page Object patterns (see `TenantsPage.js` as reference)
- Use Codecept.js Feature/Scenario format
- Tag all tests: `@admin` + feature tag (e.g., `@coder`, `@locale`)
- E2E tests must be idempotent — no leftover state between runs
- Coder SSE test (CUJ-17) needs special handling for streaming events
- Agent Settings (CUJ-18) uses iframe — may need Playwright frame switching

## Impact

- 10 new CUJ entries in matrix (total: 23 from 13)
- 5 new Page Objects (total: 12 from 7)
- 8 new test files + 2 updated
- E2E coverage: 27/27 admin pages (from 12/27)

## Alternatives Considered

1. **Only unit/functional tests** — rejected: browser interactions (dropdowns, SSE, iframes) can only be tested via E2E
2. **Manual QA checklist** — rejected: not reproducible, not automated
3. **Cypress instead of Codecept** — rejected: project already uses Codecept + Playwright
