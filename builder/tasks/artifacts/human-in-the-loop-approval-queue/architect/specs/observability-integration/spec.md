## ADDED Requirements

### Requirement: Canonical Cross-Service Trace Step Events
The platform SHALL emit canonical structured trace-step events for OpenClaw, core, and A2A agents so each call chain can be reconstructed deterministically from logs.

Each step event SHALL include at least:
- `event_name`
- `step`
- `source_app`
- `target_app` (when applicable)
- `trace_id`
- `request_id`
- `status`
- `duration_ms` (for completed or failed terminal step events)

#### Scenario: Core routes OpenClaw tool call to an agent
- **WHEN** core receives `POST /api/v1/agents/invoke` and performs tool resolution and outbound A2A call
- **THEN** logs SHALL contain step events for request receive, tool resolve, outbound A2A start, and outbound A2A completion or failure
- **AND** each event SHALL contain the same `trace_id` and correlated `request_id`

### Requirement: Discovery Catalog Snapshot Logging
The platform SHALL log discovery snapshots with the exact tool catalog returned to OpenClaw so operators can verify discovery mismatches.

The snapshot SHALL include:
- `generated_at`
- `tool_count`
- tools array with `name`, `agent`, `description`
- schema fingerprint for each tool input schema

#### Scenario: OpenClaw fetches discovery catalog
- **WHEN** OpenClaw requests `GET /api/v1/agents/discovery`
- **THEN** the resulting logs SHALL include a discovery snapshot event with every returned tool name and description
- **AND** operators SHALL be able to inspect this snapshot in trace/log UI

### Requirement: Sanitized Step Input and Output Capture
The platform SHALL capture sanitized step input/output context for discovery, invoke, and A2A execution steps to support root-cause analysis.

The capture mechanism SHALL:
- redact sensitive fields and credential-like headers
- preserve payload structure after redaction
- expose truncation metadata when payload size limits are applied

#### Scenario: Invoke payload contains sensitive fields
- **WHEN** an invoke or A2A payload includes keys such as `token`, `authorization`, `api_key`, `secret`, or `password`
- **THEN** stored step context SHALL redact these values
- **AND** trace detail UI SHALL display redacted values rather than raw secrets

### Requirement: Sequence Diagram Visualization for Trace Detail
The admin trace page SHALL render trace execution as a sequence diagram with interactive drill-down per step.

The sequence view SHALL:
- show participants (OpenClaw, core, agents)
- show directed edges for each step event with status and timing
- support click-to-open detail panel for sanitized input/output and error context

#### Scenario: Operator inspects failed trace
- **WHEN** an operator opens `/admin/logs/trace/{traceId}` for a failed request chain
- **THEN** the page SHALL show a sequence diagram with the failed step visibly marked
- **AND** clicking that step SHALL reveal sanitized input, output (if any), and structured error details
