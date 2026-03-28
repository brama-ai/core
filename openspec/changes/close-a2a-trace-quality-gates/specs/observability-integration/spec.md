## ADDED Requirements

### Requirement: E2E Test Coverage for Trace Sequence Visualization
The platform SHALL have end-to-end test coverage verifying that the admin trace sequence visualization UI renders correctly and supports interactive drill-down for traced A2A calls.

E2E tests SHALL:
- seed realistic multi-step trace data (discovery, invoke, A2A outbound/inbound events) into OpenSearch
- verify the trace detail page loads with sequence diagram container
- verify participant lanes and directed call arrows render for a multi-service trace
- verify step detail drill-down opens and displays sanitized context metadata
- clean up seeded data after test execution

#### Scenario: Admin trace view page loads with sequence container
- **WHEN** an admin navigates to `/admin/logs` and clicks a trace link for a seeded trace
- **THEN** the trace detail page SHALL render with `.trace-sequence` container visible
- **AND** the `.trace-timeline` section SHALL be present

#### Scenario: Sequence diagram renders participants and call arrows for A2A trace
- **WHEN** an admin opens the trace detail page for a seeded multi-step A2A trace
- **THEN** the page SHALL display `.sequence-diagram` with at least two `.sequence-participant` elements
- **AND** at least one `.sequence-arrow-label` SHALL be visible representing a directed call

#### Scenario: Step detail drill-down displays sanitized context
- **WHEN** an admin clicks a `.sequence-detail-icon` on the trace sequence diagram
- **THEN** a `.sequence-detail-panel.active` SHALL appear
- **AND** the panel SHALL contain step metadata such as sanitized input/output or status information

### Requirement: Integration Test Coverage for Discovery and Invoke Trace Events
The platform SHALL have integration test coverage verifying that discovery snapshot and invoke step trace events contain all canonical fields defined by the structured trace-event contract.

Integration tests SHALL verify:
- discovery snapshot events include `event_name`, `step`, `source_app`, `status`, `target_app`, `sequence_order`, `tool_count`, `step_output` (with tools array), and `capture_meta`
- invoke step events include `event_name`, `step`, `source_app`, `status`, `target_app`, `tool`, `trace_id`, `request_id`, `sequence_order`, `step_input`, and `capture_meta`
- sensitive fields in step input/output are redacted by `PayloadSanitizer`
- `sequence_order` values are monotonically increasing across consecutive events

#### Scenario: Discovery snapshot event contains canonical fields
- **WHEN** a `TraceEvent::build()` call produces a discovery snapshot event with sanitized tool catalog
- **THEN** the event SHALL contain `event_name`, `step`, `source_app`, `status`, `target_app`, `sequence_order`, `tool_count`, `step_output`, and `capture_meta` fields
- **AND** `capture_meta` SHALL include `is_truncated`, `original_size_bytes`, `captured_size_bytes`, and `redacted_fields_count`

#### Scenario: Invoke step event redacts sensitive input fields
- **WHEN** a `TraceEvent::build()` call produces an invoke received event with input containing `token` and `api_key` fields
- **THEN** the event `step_input` SHALL contain `[REDACTED]` for those fields
- **AND** `capture_meta.redacted_fields_count` SHALL equal the number of redacted fields
