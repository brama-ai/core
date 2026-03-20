<!-- batch: 20260312_160247 | status: pass | duration: 766s | branch: pipeline/implement-change-add-openclaw-push-endpoint -->
<!-- priority: 2 -->
# Implement change: add-openclaw-push-endpoint

Add push message endpoint to OpenClaw Gateway plugin so Core can deliver messages to Telegram chats proactively. Includes authentication, idempotency, payload validation, and OpenSearch logging.

## OpenSpec

- Proposal: openspec/changes/add-openclaw-push-endpoint/proposal.md
- Tasks: openspec/changes/add-openclaw-push-endpoint/tasks.md
- Spec delta: openspec/changes/add-openclaw-push-endpoint/specs/openclaw-push/spec.md

## Context

- Depends on: `add-delivery-channels` must exist so `OpenClawAdapter` in Core can call this endpoint
- Modifies the existing `platform-tools/index.js` OpenClaw plugin
- Must use a separate `OPENCLAW_PUSH_TOKEN` (not reuse gateway token) for blast radius containment
- OpenClaw is Node.js — push handler is JavaScript, not PHP
- Must integrate with existing `osLog()` OpenSearch logging infrastructure in the plugin
- Bot must be a member of target chat/group for push to work

## Key files to create/update

### In docker/openclaw/:
- `plugins/platform-tools/index.js` (modified — add push route handler)
- `.env` (modified — add OPENCLAW_PUSH_TOKEN)

### In docker compose:
- `compose.openclaw.yaml` (modified — pass OPENCLAW_PUSH_TOKEN env var)
- `compose.openclaw.multi-bot.yaml` (modified — pass OPENCLAW_PUSH_TOKEN env var)

### In apps/core/:
- `.env` (modified — add OPENCLAW_PUSH_TOKEN for OpenClawAdapter)

### In docs/:
- `docker/openclaw/README.md` (modified — add push endpoint docs)

### In tests/:
- `tests/e2e/tests/openclaw/push_endpoint_test.js` (new)

## Validation

- Manual curl test: push a message to a real Telegram chat
- E2E tests pass
- OpenSearch logs show push events
