# Tasks: add-a2a-trace-sequence-visualization

## 1. Structured Trace Event Contract

- [x] 1.1 Define canonical trace-event fields shared across services (`event_name`, `step`, `source_app`, `target_app`, `tool`, `intent`, `trace_id`, `request_id`, `agent_run_id`, `status`, `duration_ms`, `error_code`).
- [x] 1.2 Introduce a payload sanitizer utility with deterministic redaction rules for secrets/tokens and payload truncation metadata (`is_truncated`, `original_size_bytes`, `captured_size_bytes`).
- [x] 1.3 Update log index mapping to support sequence/query fields (`event_name`, `step`, `source_app`, `target_app`, `status`, `error_code`, `sequence_order`).

## 2. Discovery and Invoke Step Logging

- [x] 2.1 OpenClaw `platform-tools` plugin: log discovery lifecycle (`started`, `completed`, `failed`) and catalog snapshot with full tools list and descriptions.
- [x] 2.2 Core `DiscoveryController`: emit structured response snapshot metadata (`tool_count`, `generated_at`, payload hash).
- [x] 2.3 Core `InvokeController` + `AgentInvokeBridge`: emit step logs for request receive, tool resolve, disabled/unknown tool branch, outbound A2A start/completion/failure.
- [x] 2.4 Hello-agent A2A path: emit structured inbound request, intent handling, LLM call, and final response events with correlation IDs.

## 3. Context Capture for Drill-Down

- [x] 3.1 Persist sanitized step input/output blobs in log context (or referenced artifact records) for discovery, invoke, and A2A steps.
- [x] 3.2 Ensure exception paths capture structured error payload and stack summary without leaking credentials.
- [x] 3.3 Add regression tests for redaction and truncation behavior.

## 4. Trace Sequence Visualization UI

- [x] 4.1 Extend trace aggregation logic to build sequence edges from structured events (`from`, `to`, `operation`, `status`, `started_at`, `ended_at`, `duration_ms`).
- [x] 4.2 Add sequence diagram block to `/admin/logs/trace/{traceId}` with participant lanes and directional arrows.
- [x] 4.3 Implement click-to-inspect panel/modal that shows per-step sanitized input/output, headers, and error metadata.
- [x] 4.4 Keep existing waterfall/timeline view available as secondary panels.

## 5. Tests

- [x] 5.1 Add unit tests for sequence projection from log events.
- [x] 5.2 Add functional tests for trace page rendering sequence nodes/edges and detail inspector payload visibility.
- [x] 5.3 Add integration coverage for discovery snapshot and invoke step event fields.

## 6. Documentation

- [x] 6.1 Update `docs/features/logging.md` with the canonical event schema, redaction policy, and sequence-view operator workflow.
- [x] 6.2 Update `docs/agent-requirements/observability-requirements.md` with required trace-event fields for every agent.
- [x] 6.3 Update `docs/specs/ua/a2a-protocol.md` and `docs/specs/en/a2a-protocol.md` to document step-level observability expectations.

## 7. Quality Checks

- [x] 7.1 `vendor/bin/phpstan analyse` (core + hello-agent) passes with zero errors.
- [x] 7.2 `vendor/bin/php-cs-fixer check --diff --allow-risky=yes` (core + hello-agent) passes.
- [x] 7.3 `vendor/bin/codecept run` (core + hello-agent functional + unit) passes.
