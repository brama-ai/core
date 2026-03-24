# Change: Add Step-Level A2A Trace Logging and Sequence Visualization

## Why

The current trace view is useful for timeline inspection but does not reliably answer three operator questions during incident debugging:
1. Which exact step failed in the OpenClaw -> core -> agent chain?
2. What exact input was accepted and what output was returned at that step?
3. What discovery catalog was actually visible to OpenClaw at invocation time?

Without a canonical step log format and drill-down payload view, operators must infer failures from partial messages.

## What Changes

- Introduce a canonical structured trace-event envelope for OpenClaw, core, and agent logging around discovery, invoke, routing, outbound A2A, inbound A2A, and completion/failure steps.
- Add mandatory discovery snapshot logging that records the full discovered tools list (tool name, agent, description, schema fingerprint) so operators can reconstruct what OpenClaw actually received.
- Capture sanitized input/output context per step with explicit redaction and truncation metadata for safe debugging.
- Extend `/admin/logs/trace/{traceId}` with a sequence-diagram visualization (participants + directed calls + status badges).
- Add click-to-inspect behavior on each sequence edge/step to show detailed context (headers, sanitized input, sanitized output, error object, timing metadata).

## Impact

- Affected specs: `observability-integration`
- Affected code:
  - `docker/openclaw/plugins/platform-tools/*`
  - `apps/brama-core/src/AgentDiscovery/*`
  - `apps/brama-core/src/Controller/Admin/LogTraceController.php`
  - `apps/brama-core/templates/admin/log_trace.html.twig`
  - `apps/brama-core/src/Logging/*`
  - `apps/hello-agent/src/Controller/Api/A2AController.php`
  - `apps/hello-agent/src/A2A/HelloA2AHandler.php`
- Backward compatibility:
  - Existing log records remain readable.
  - New structured fields are additive.
