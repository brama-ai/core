# E2E Critical User Journey (CUJ) Matrix

This matrix defines the critical user journeys that MUST have E2E test coverage.
Pipeline agents (tester, auditor) use this matrix to verify coverage.

## What is a CUJ?

A Critical User Journey is a complete user flow from action to visible result.
E2E tests cover CUJs — not individual components (that's what unit tests do).

## Coverage Rules

- Every UI feature MUST map to at least one CUJ
- Every CUJ MUST have an E2E test file + Page Object (if UI-based)
- New UI features without CUJ coverage get flagged as WARN by auditor
- CUJ tests run via `make e2e` (Codecept + Playwright)

## Matrix

| ID | Journey | Test File | Page Object | Status |
|----|---------|-----------|-------------|--------|
| CUJ-01 | Login → see dashboard | `tests/e2e/tests/admin/dashboard_test.js` | `LoginPage.js`, `DashboardPage.js` | covered |
| CUJ-02 | Agents → discover → install → enable | `tests/e2e/tests/admin/agents_test.js` | `AgentsPage.js` | covered |
| CUJ-03 | Agent → disable → delete | `tests/e2e/tests/admin/agent_delete_test.js` | `AgentsPage.js` | covered |
| CUJ-04 | Agent → toggle enabled/disabled | `tests/e2e/tests/admin/agent_toggle_test.js` | `AgentsPage.js` | covered |
| CUJ-05 | Tenant → create → switch → context persists | `tests/e2e/tests/admin/tenant_switch_test.js` | `TenantsPage.js` | covered |
| CUJ-06 | Tenant → manage (CRUD) | `tests/e2e/tests/admin/tenant_management_test.js` | `TenantsPage.js` | covered |
| CUJ-07 | Locale → switch language → UI translates | `tests/e2e/tests/admin/locale_switch_test.js` | `LocalePage.js` | covered |
| CUJ-08 | Scheduler → view jobs list | `tests/e2e/tests/admin/scheduler_test.js` | `SchedulerPage.js` | covered |
| CUJ-09 | Logs → search → view trace | `tests/e2e/tests/admin/logs_test.js` | `LogsPage.js` | covered |
| CUJ-10 | Chats → view list → open detail | `tests/e2e/tests/admin/chats_test.js` | `ChatsPage.js` | covered |
| CUJ-11 | Health endpoints → all services respond | `tests/e2e/tests/smoke/health_test.js` | — (API) | covered |
| CUJ-12 | Knowledge admin → settings page | `tests/e2e/tests/admin/knowledge_admin_test.js` | — | covered |
| CUJ-13 | News maker admin → digest pipeline | `tests/e2e/tests/admin/news_maker_admin_test.js` | — | covered |
| CUJ-14 | Settings → log level → retention → save | `tests/e2e/tests/admin/settings_test.js` | `SettingsPage.js` | covered |
| CUJ-15 | Coder → dashboard → stats → workers | `tests/e2e/tests/admin/coder_dashboard_test.js` | `CoderPage.js` | covered |
| CUJ-16 | Coder → task detail → logs → timeline | `tests/e2e/tests/admin/coder_detail_test.js` | `CoderPage.js` | covered |
| CUJ-17 | Coder → events SSE → real-time updates | `tests/e2e/tests/admin/coder_events_test.js` | `CoderPage.js` | covered |
| CUJ-18 | Agent → settings → iframe config | `tests/e2e/tests/admin/agent_settings_test.js` | `AgentSettingsPage.js` | covered |
| CUJ-19 | Logs → trace → sequence diagram → spans | `tests/e2e/tests/admin/log_trace_test.js` | `LogTracePage.js` | covered |
| CUJ-20 | Scheduler → create job → delivery channel | `tests/e2e/tests/admin/scheduler_test.js` | `SchedulerPage.js` | **skipped** (requires add-scheduler-delivery) |
| CUJ-21 | Scheduler → job → view logs | `tests/e2e/tests/admin/scheduler_logs_test.js` | `SchedulerPage.js` | covered |
| CUJ-22 | Dashboard → metrics cards → A2A/agent/scheduler | `tests/e2e/tests/admin/dashboard_test.js` | `DashboardPage.js` | covered |
| CUJ-23 | Dev Reporter → admin → reports list → filter by status | `tests/e2e/tests/admin/dev_reporter_admin_test.js` | `DevReporterPage.js` | covered |
| CUJ-24 | Dev Reporter → health + manifest via Traefik | `tests/e2e/tests/admin/dev_reporter_admin_test.js` | — (API) | covered |

## How to Add a New CUJ

1. Add a row to the matrix above
2. Create Page Object in `tests/e2e/support/pages/` (if UI-based)
3. Create test file in `tests/e2e/tests/admin/` (or appropriate subdirectory)
4. Register Page Object in `tests/e2e/codecept.conf.js` → `include` section
5. Tag test with `@admin` (UI) or `@smoke` (API)

## E2E Test Conventions

- **Framework**: Codecept.js + Playwright
- **Config**: `tests/e2e/codecept.conf.js`
- **Page Objects**: `tests/e2e/support/pages/*.js` — encapsulate selectors and actions
- **Test naming**: `*_test.js`, use `Feature()` + `Scenario()` pattern
- **Tags**: `@admin` for UI tests, `@smoke` for API, feature-specific tags (e.g., `@tenant`, `@locale`)
- **Run**: `make e2e` (full), `make e2e-smoke` (API only)
