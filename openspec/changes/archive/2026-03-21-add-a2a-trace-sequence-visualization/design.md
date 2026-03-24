# Design: add-a2a-trace-sequence-visualization

## Context

The platform already has:
- distributed correlation (`trace_id`, `request_id`)
- cross-service logs in OpenSearch
- a trace details page with waterfall + grouped logs

However, trace reconstruction is still message-based and inconsistent. The main missing pieces are:
- canonical step semantics across OpenClaw/brama-core/agents
- reliable discovery snapshot visibility
- drill-down input/output context for each step
- sequence-graph rendering for who-called-whom analysis

## Goals / Non-Goals

### Goals

- Standardize trace-step logging across OpenClaw/brama-core/agents.
- Make discovery state debuggable by logging catalog snapshots.
- Provide safe but detailed input/output drill-down context.
- Render trace execution as a sequence diagram in admin UI.

### Non-Goals

- Replacing Langfuse or changing OTEL ingestion architecture.
- Introducing distributed tracing vendors beyond existing stack.
- Logging raw secrets or unbounded payloads.

## Event Contract

Each step-level record uses existing top-level log envelope plus structured fields:

- `event_name`: stable event identifier (`core.a2a.outbound.started`, `openclaw.discovery.snapshot`, ...)
- `step`: normalized step key (`discovery_fetch`, `tool_resolve`, `a2a_outbound`, `a2a_inbound`, `llm_call`)
- `source_app`, `target_app`
- `trace_id`, `request_id`, optional `agent_run_id`
- `tool`, `intent`
- `status`: `started|completed|failed|skipped`
- `duration_ms`
- `sequence_order` (monotonic ordering hint per request/step)
- `error_code` (stable machine code for failures)
- `context.step_input` / `context.step_output` (sanitized)
- `context.capture_meta` (`is_truncated`, size fields, redaction counters)

## Redaction and Payload Capture

Rules:
- redact secrets by key pattern (`token`, `authorization`, `api_key`, `secret`, `password`, `cookie`)
- redact credential-like header values
- keep structural shape of payloads after redaction
- cap stored payload size with explicit truncation metadata

This gives deterministic debugging context without credential leakage.

## Sequence Projection

1. Query logs by `trace_id`.
2. Keep only records with `event_name` from the structured contract.
3. Build participants from `source_app`/`target_app`.
4. Build edges ordered by `@timestamp` + `sequence_order`.
5. Render each edge with operation label (`tool`, `intent`, endpoint step), status color, duration.
6. On click, open detail panel with step metadata and sanitized input/output.

Fallback behavior:
- if structured fields are missing, keep current waterfall/timeline rendering only.

## UI Changes

`/admin/logs/trace/{traceId}` becomes a multi-panel trace inspector:
- `Sequence` (primary)
- `Waterfall` (existing)
- `Grouped Logs` (existing)

Sequence interactions:
- click edge -> detail panel/modal
- detail sections: request headers, step input, step output, status, error, timing, origin log id

## Risks / Trade-offs

- More log volume due to payload capture.
  - Mitigation: truncation limits + optional sampling knobs.
- Event schema drift between services.
  - Mitigation: shared contract doc + tests for required fields.
- UI complexity for long traces.
  - Mitigation: collapse/filter by participant and status.

## Migration Plan

1. Introduce additive fields and projection logic first.
2. Keep existing trace page blocks intact.
3. Roll out structured events per service incrementally (OpenClaw -> core -> hello-agent).
4. Enable sequence panel after minimum event coverage is reached.
