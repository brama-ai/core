## Context

The platform currently executes A2A calls as synchronous HTTP request/response exchanges. This is simple and robust for short tasks, but it does not expose incremental progress, stream partial artifacts, or notify disconnected clients when long tasks advance.

Official A2A guidance supports three complementary interaction modes: polling, SSE streaming, and webhook push notifications. For Symfony/PHP workloads, a hybrid model is operationally safer than an SSE-only architecture.

## Goals / Non-Goals

- Goals:
  - Add first-class SSE streaming for real-time task progress and partial outputs.
  - Add webhook push for disconnected or very long-running tasks.
  - Preserve request/response + polling as default and fallback behavior.
  - Keep strong correlation and observability across all three modes.
- Non-Goals:
  - Full federation across clusters.
  - Replacing the existing synchronous path.
  - Introducing proprietary transport protocols outside HTTP/SSE/webhook.

## Decisions

- Decision: Hybrid interaction model (sync + polling + SSE + push)
  - Why: Best fit for Symfony/PHP operations and mixed client connectivity profiles.
  - Alternatives considered: SSE-only (fragile for disconnected clients), push-only (poor for interactive UX).

- Decision: Task object as source of truth for async state
  - Why: Enables consistent status retrieval (`queued`, `working`, terminal states) regardless of delivery mode.

- Decision: Push notifications are event hints, polling remains authoritative
  - Why: Push delivery may fail/retry; clients can always recover full state through `GetTask`-style polling.

- Decision: Security-first webhook delivery
  - Why: Push introduces SSRF and spoofing risks; require URL validation, auth scheme declaration, and replay defenses.

## Minimal Technical Plan (Phase R1)

### API Surface (Core A2A Gateway)

- `POST /api/v1/a2a/send-streaming-message`
  - Starts/continues a task and returns `text/event-stream`.
  - Emits ordered task events until a terminal state or connection close.
- `GET /api/v1/a2a/tasks/{taskId}`
  - Returns the latest persisted task snapshot for polling/recovery.
- Existing `POST /api/v1/a2a/send-message` stays unchanged for backward compatibility.

### Task State Model

- Non-terminal: `submitted`, `working`, `input_required`
- Terminal: `completed`, `failed`, `canceled`, `rejected`
- A task record stores: `task_id`, `request_id`, `trace_id`, `agent`, `skill`, `status`, `result_json`, timestamps.

### Persistence (Minimum)

- Add `a2a_tasks` table in `apps/brama-core/migrations` for current task snapshot.
- Optional in R1: keep event history in logs only (no separate `a2a_task_events` table yet).
- Enforce idempotent resume by `request_id` when present (reuse existing task instead of creating duplicates).

### Streaming Behavior in R1

- On stream open:
  - Resolve agent/skill.
  - Persist task as `submitted` -> `working`.
  - Emit initial status event.
- Execute existing agent invocation path (`A2AClient->invoke(...)`).
  - In R1 this remains a single downstream call; no token-level pass-through from agents.
- On completion:
  - Persist final status + result snapshot.
  - Emit final status/artifact event and close stream.
- If client disconnects early:
  - Task processing continues to completion when feasible.
  - Client recovers with `GET /api/v1/a2a/tasks/{taskId}`.

### SSE Event Envelope (R1)

- Event types:
  - `task_status_update`
  - `task_artifact_update`
- Every event includes `task_id`, `trace_id`, `request_id`, `status`, and event timestamp.
- Send keepalive comments periodically to reduce idle timeout disconnects.

### Symfony Runtime Notes

- Use `StreamedResponse` for SSE in controller.
- Disable proxy buffering for SSE responses where applicable.
- Set conservative stream timeout/heartbeat defaults suitable for PHP-FPM workloads.

## Risks / Trade-offs

- Long-lived SSE connections can stress PHP worker pools.
  - Mitigation: define connection limits, idle timeout, and recommend runtime profile for SSE workloads.
- Webhook delivery complexity (retries, deduplication, auth drift).
  - Mitigation: idempotency keys, bounded retries, signed/authenticated callbacks.
- More moving parts than pure polling.
  - Mitigation: polling remains default baseline; SSE/push are opt-in capabilities.

## Migration Plan

1. Phase R1: spec deltas for streaming + polling, task model, observability updates.
2. Phase R1: implement `a2a_tasks` persistence + `GET /api/v1/a2a/tasks/{taskId}`.
3. Phase R1: implement `POST /api/v1/a2a/send-streaming-message` with SSE events.
4. Phase R1: add tests (stream open/final event/recovery via polling) and docs.
5. Phase R2: add push config + authenticated webhook delivery + retry policy.

## Open Questions

- Should SSE transport be served directly by app workers or by a dedicated runtime profile?
- Which push auth schemes are mandatory in R2 (Bearer only vs Bearer + HMAC)?
