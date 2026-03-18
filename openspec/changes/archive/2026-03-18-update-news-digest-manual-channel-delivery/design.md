## Context

`news-maker-agent` already generates and persists digest records, while the platform routing contract for cross-agent/tool invocation is `Core A2A`.

The missing piece is a production-grade operator action that couples manual digest generation with channel delivery through the same platform boundary used by other tool invocations.

## Goals / Non-Goals

- Goals:
  - Enable one-click manual digest generation and publish flow from admin UI.
  - Use platform-owned invocation boundary (`/api/v1/a2a/send-message`) instead of direct Telegram calls.
  - Avoid duplicate manual publishes caused by concurrent button presses.
  - Preserve digest persistence if outbound delivery fails.
- Non-Goals:
  - Personalized per-user digest fan-out.
  - Replacing scheduled digest behavior.
  - Building a generic outbound notification framework in this change.

## Decisions

- Decision: manual trigger is executed asynchronously in a background thread with a dedicated lock.
  - Rationale: keep admin request latency low and prevent duplicate concurrent digest runs.
- Decision: delivery is performed after digest commit, not in the same DB transaction.
  - Rationale: publication to channel is side-effect I/O and must not rollback persisted digest data.
- Decision: delivery path uses Core A2A invoke endpoint with tool payload:
  - `tool`: `openclaw.send_message`
  - `input`: message body/format + digest metadata
  - auth header: `Authorization: Bearer <gateway token>`
  - Rationale: keeps OpenClaw replaceable and preserves Core-owned audit/routing policy.
- Decision: if delivery fails, the run result is `completed_with_delivery_warning` (or equivalent warning state in logs) instead of `failed`.
  - Rationale: digest generation succeeded; only transport side effect failed.

## Message Contract

Manual digest publish message sent to Core A2A should include:

- digest title
- digest body (or truncated body with source link if platform imposes limits)
- digest id
- item count
- publication timestamp

Suggested envelope:

```json
{
  "tool": "openclaw.send_message",
  "input": {
    "message": "<formatted digest text>",
    "format": "html",
    "metadata": {
      "digest_id": "<uuid>",
      "item_count": 6,
      "source": "news-maker-agent.manual-trigger"
    }
  },
  "trace_id": "...",
  "request_id": "..."
}
```

## Risks / Trade-offs

- Risk: `openclaw.send_message` may be missing/disabled in registry -> `unknown_tool` / `agent_disabled`.
  - Mitigation: log actionable warning, keep digest persisted, surface status in admin run history.
- Risk: duplicate clicks around lock boundaries may still create operator confusion.
  - Mitigation: explicit skip/accepted logging and optional UI flash message.
- Risk: sharing gateway token with trusted internal service broadens credential exposure.
  - Mitigation: scope token via env management, avoid logging secrets, and keep least-privilege runtime boundaries.

## Migration Plan

1. Add config fields for Core invoke auth in `news-maker-agent`.
2. Add manual digest trigger route and admin button.
3. Add scheduler/service helper for single-flight manual digest run.
4. Extend digest service with post-commit Core A2A publish step.
5. Add tests for success, no-items, and delivery-failure scenarios.
6. Update docs/runbook for operator workflow and required env vars.

## Open Questions

- Should message formatting include full body every time, or switch to summary+link if body exceeds Telegram-friendly size?
- Should admin UI show synchronous flash feedback for accepted/skipped trigger states in this change, or leave logs-only feedback?
