# Tasks: add-e2e-cuj-coverage

## Phase 1 — High Priority

### 1. Locale Switching (CUJ-07)
- [ ] 1.1 Create `tests/e2e/support/pages/LocalePage.js` — switcher dropdown, language selection, current language check
- [ ] 1.2 Create `tests/e2e/tests/admin/locale_switch_test.js` — switch UA→EN, verify UI translates, cookie persists across pages
- [ ] 1.3 Register `localePage` in `tests/e2e/codecept.conf.js`

### 2. Settings Page (CUJ-14)
- [ ] 2.1 Create `tests/e2e/support/pages/SettingsPage.js` — log level selector, retention input, save button
- [ ] 2.2 Create `tests/e2e/tests/admin/settings_test.js` — open settings, change log level, save, verify persisted
- [ ] 2.3 Register `settingsPage` in `tests/e2e/codecept.conf.js`

### 3. Coder Dashboard (CUJ-15)
- [ ] 3.1 Create `tests/e2e/support/pages/CoderPage.js` — task list, create button, task detail, status badges, worker panel
- [ ] 3.2 Create `tests/e2e/tests/admin/coder_dashboard_test.js` — navigate to coder, see task list, verify empty state
- [ ] 3.3 Register `coderPage` in `tests/e2e/codecept.conf.js`

### 4. Coder Task Detail (CUJ-16)
- [ ] 4.1 Create `tests/e2e/tests/admin/coder_detail_test.js` — open task, see detail sections (logs, artifacts, timeline)

### 5. Coder Events SSE (CUJ-17)
- [ ] 5.1 Create `tests/e2e/tests/admin/coder_events_test.js` — open task detail, verify SSE connection established, events rendered

### 6. Agent Settings (CUJ-18)
- [ ] 6.1 Create `tests/e2e/support/pages/AgentSettingsPage.js` — iframe handling, settings form
- [ ] 6.2 Create `tests/e2e/tests/admin/agent_settings_test.js` — open agent, click settings, verify iframe loads
- [ ] 6.3 Register `agentSettingsPage` in `tests/e2e/codecept.conf.js`

### 7. Log Trace Visualization (CUJ-19)
- [ ] 7.1 Create `tests/e2e/support/pages/LogTracePage.js` — sequence diagram container, span details
- [ ] 7.2 Create `tests/e2e/tests/admin/log_trace_test.js` — open trace, verify sequence diagram renders, spans visible
- [ ] 7.3 Register `logTracePage` in `tests/e2e/codecept.conf.js`

### 8. Dashboard Metrics (CUJ-22)
- [ ] 8.1 Update `tests/e2e/tests/admin/dashboard_test.js` — verify A2A stats card, agent activity card, scheduler stats card visible

## Phase 2 — Medium Priority

### 9. Scheduler Delivery (CUJ-20)
- [ ] 9.1 Update `tests/e2e/tests/admin/scheduler_test.js` — add scenario: create job with delivery channel selected

### 10. Scheduler Job Logs (CUJ-21)
- [ ] 10.1 Create `tests/e2e/tests/admin/scheduler_logs_test.js` — open job, click logs, verify execution history visible

## Phase 3 — Finalize

### 11. CUJ Matrix Update
- [ ] 11.1 Update `docs/agent-requirements/e2e-cuj-matrix.md` — add CUJ-14 through CUJ-22 with test file references
- [ ] 11.2 Mark CUJ-07 as covered after locale test created

### 12. Validation
- [ ] 12.1 Run `make e2e-prepare && make e2e` — all new tests pass
- [ ] 12.2 Verify all Page Objects registered in codecept.conf.js
- [ ] 12.3 Verify CUJ matrix has no MISSING entries
