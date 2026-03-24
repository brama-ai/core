# Change: Add A2A Streaming and Push Notification Modes

## Why

The current platform A2A flow is request/response-first, which is sufficient for short tasks but weak for long-running workflows and incremental output.

To align with official A2A async interaction patterns and improve UX/operability, we need first-class support for SSE streaming and webhook push notifications while keeping polling as a reliable fallback.

## What Changes

- Add async interaction support to the A2A Gateway contract:
  - `SendStreamingMessage` (SSE stream)
  - task status polling (`GetTask`-style retrieval)
  - webhook push notifications for major task state changes
- Deliver in phases:
  - Phase R1 (minimum): `SendStreamingMessage` + `GetTask` polling
  - Phase R2: webhook push notifications
- Extend Agent Card capability declaration with `streaming` and `pushNotifications` flags.
- Define a platform-owned task lifecycle model for long-running A2A operations (including terminal states and correlation fields).
- Add security requirements for push callbacks (auth, signature/token checks, replay protection).
- Keep existing synchronous `send-message` behavior as the baseline path and compatibility fallback.

## Impact

- Affected specs: `a2a-server`, `observability-integration`
- Affected code:
  - `apps/brama-core/src/A2AGateway/` (new async endpoints + task orchestration)
  - `apps/brama-core/src/Controller/Api/A2AGateway/` (SSE + task APIs)
  - agent A2A controllers/handlers in `apps/hello-agent` and `apps/knowledge-agent`
  - task persistence/migrations in `apps/brama-core/migrations`
  - docs: `docs/specs/ua/a2a-protocol.md`, `docs/specs/en/a2a-protocol.md`, `docs/agent-requirements/*`
