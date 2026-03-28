# Change: Close A2A Trace Sequence Visualization Quality Gates and Add E2E Tests

## Why

The `add-a2a-trace-sequence-visualization` feature was implemented and archived, but three quality-gate tasks remain open: integration test coverage for discovery snapshot and invoke step event fields (task 5.3), Codeception suite green-run verification, and E2E coverage for the trace sequence UI. Without closing these gates the feature lacks automated regression protection for its most operator-visible surfaces — the trace page, sequence diagram, and step drill-down.

## What Changes

- Complete integration test coverage for discovery snapshot event fields and invoke step event fields (already scaffolded in `src/tests/Integration/Logging/`; verify they pass under `vendor/bin/codecept run`).
- Fix any Codeception failures surfaced by running the full suite in `brama-core/src/`.
- Add E2E tests in `brama-core/tests/e2e/tests/admin/` covering:
  - Admin logs trace view page loads with seeded trace data.
  - Trace sequence diagram renders participants and directed call arrows for a traced A2A call.
  - Step detail drill-down opens and displays sanitized input/output context.
- Verify all E2E tests pass via `make e2e`.
- Mark all remaining tasks done in the archived `tasks.md`.

## Impact

- Affected specs: `observability-integration`
- Affected code:
  - `brama-core/src/tests/Integration/Logging/DiscoverySnapshotEventFieldsTest.php`
  - `brama-core/src/tests/Integration/Logging/InvokeStepEventFieldsTest.php`
  - `brama-core/tests/e2e/tests/admin/log_trace_test.js` (extend or add companion file)
  - `brama-core/tests/e2e/support/pages/LogTracePage.js`
  - `brama-core/openspec/changes/archive/2026-03-21-add-a2a-trace-sequence-visualization/tasks.md`
- Backward compatibility: No production code changes — tests only.
