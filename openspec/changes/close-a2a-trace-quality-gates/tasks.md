# Tasks: close-a2a-trace-quality-gates

## 1. Integration Test Coverage (archived task 5.3)

- [x] 1.1 Verify `src/tests/Integration/Logging/DiscoverySnapshotEventFieldsTest.php` passes under `vendor/bin/codecept run -- --filter DiscoverySnapshotEventFieldsTest` inside `brama-core/src/`. Fix any failures.
- [x] 1.2 Verify `src/tests/Integration/Logging/InvokeStepEventFieldsTest.php` passes under `vendor/bin/codecept run -- --filter InvokeStepEventFieldsTest` inside `brama-core/src/`. Fix any failures.

## 2. Codeception Suite Green Run

- [x] 2.1 Run `vendor/bin/codecept run` in `brama-core/src/` (unit + functional + integration suites). Fix any failures until the full suite is green.

## 3. E2E Tests for Trace Sequence Visualization

Follow existing patterns in `tests/e2e/tests/admin/log_trace_test.js` (CodeceptJS + Playwright helper, OpenSearch seed/cleanup, `loginPage.loginAsAdmin()` before each scenario).

- [x] 3.1 Add E2E scenario: **Admin logs trace view page loads** — navigate to `/admin/logs`, click a trace link, verify the trace detail page renders with `.trace-sequence` container and `.trace-timeline` section visible.
- [x] 3.2 Add E2E scenario: **Trace sequence diagram renders for a traced A2A call** — seed a multi-step trace (discovery + invoke + A2A outbound/inbound events) into OpenSearch, navigate to its trace page, verify `.sequence-diagram` is present, at least two `.sequence-participant` elements exist (e.g. `core`, `hello-agent`), and at least one `.sequence-arrow-label` is visible.
- [x] 3.3 Add E2E scenario: **Step detail drill-down works** — on the seeded trace page, click `.sequence-detail-icon`, verify `.sequence-detail-panel.active` appears and contains step metadata (sanitized input/output or status text).
- [x] 3.4 Ensure new E2E scenarios use `@admin @logs @trace` tags and follow the OpenSearch seed/cleanup pattern from the existing `log_trace_test.js`.

## 4. E2E Green Run

- [x] 4.1 Run `make e2e` and verify all E2E tests pass (including the new trace scenarios).

## 5. Close Archived Tasks

- [x] 5.1 Mark task 5.3 as done (`- [x]`) in `openspec/changes/archive/2026-03-21-add-a2a-trace-sequence-visualization/tasks.md`.
- [x] 5.2 Mark tasks 7.1, 7.2, 7.3 as done (`- [x]`) in the same file after quality checks pass.

## 6. Documentation

- [x] 6.1 No new docs required — this change adds tests only. Verify existing `docs/features/logging.md` still accurately describes the trace UI behavior covered by the new E2E tests.
