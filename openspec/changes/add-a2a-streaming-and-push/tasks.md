## 1. Phase R1 — Minimum Async Delivery (Streaming + Polling)

- [ ] 1.1 Confirm/adjust spec deltas for `SendStreamingMessage` + `GetTask` as R1 scope
- [ ] 1.2 Add/extend task state model for R1 (`submitted`, `working`, `input_required`, terminal states)
- [ ] 1.3 Add migration for `a2a_tasks` snapshot persistence (task_id/request_id/trace_id/status/result/timestamps)
- [ ] 1.4 Implement `GET /api/v1/a2a/tasks/{taskId}` (polling recovery endpoint)
- [ ] 1.5 Implement `POST /api/v1/a2a/send-streaming-message` with `text/event-stream` via Symfony `StreamedResponse`
- [ ] 1.6 Emit R1 SSE events (`task_status_update`, `task_artifact_update`) with correlation fields
- [ ] 1.7 Keep `POST /api/v1/a2a/send-message` fully backward compatible

## 2. Phase R1 — Agent Contract + Observability

- [ ] 2.1 Extend Agent Card capability schema for `capabilities.streaming`
- [ ] 2.2 Update brama-core/agent observability mapping for stream lifecycle + task correlation
- [ ] 2.3 Ensure idempotent handling by `request_id` when present (resume existing task snapshot)
- [ ] 2.4 Add functional tests: stream opens, emits final event, and client can recover via `GetTask`

## 3. Phase R2 — Push Notifications (Follow-up)

- [ ] 3.1 Extend Agent Card capability schema for `capabilities.pushNotifications`
- [ ] 3.2 Add push notification config endpoint and storage
- [ ] 3.3 Implement authenticated push dispatcher with retry/backoff and idempotency keys
- [ ] 3.4 Add webhook URL validation + SSRF controls + replay protection
- [ ] 3.5 Add delivery telemetry and dead-letter handling for failed callbacks

## 4. Documentation

- [ ] 4.1 Update `docs/specs/ua/a2a-protocol.md` with R1 stream/poll behavior and R2 push plan
- [ ] 4.2 Update `docs/specs/en/a2a-protocol.md` mirror
- [ ] 4.3 Update `docs/agent-requirements/` contracts for async modes and push security profile

## 5. Quality Checks

- [ ] 5.1 `make analyse`
- [ ] 5.2 `make cs-check`
- [ ] 5.3 `make test`
- [ ] 5.4 Targeted functional tests for SSE stream lifecycle and `GetTask` recovery
