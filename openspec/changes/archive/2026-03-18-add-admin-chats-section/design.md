## Context
The platform already stores A2A invocation data in two places:
1. **`a2a_message_audit`** (Postgres) — structured audit rows per skill invocation with `trace_id`, `request_id`, `skill`, `agent`, `status`, `duration_ms`, `error_code`, `http_status_code`, `actor`, `created_at`
2. **OpenSearch** (`platform_logs_*`) — full log entries with payloads (`step_input`, `step_output`), trace context, and event metadata

The Chats section combines these sources: Postgres for the list/index (fast, paginated, filterable), OpenSearch for conversation detail (rich payloads).

## Goals / Non-Goals
- **Goals**: Give operators a conversation-centric view of A2A traffic; quick navigation to trace details; filter by agent/skill/status/date
- **Non-Goals**: Real-time chat interface; user-facing chat history; storing additional message data beyond what audit + logs already capture

## Decisions

### Data model: no new tables
The `a2a_message_audit` table already captures one row per invocation. Each row's `trace_id` identifies the conversation thread. We group by `trace_id` to build the chat list, and join OpenSearch data for message content in the detail view.

**Alternatives considered**: Creating a `chat_sessions` table — rejected because it duplicates data already in audit table, and we don't yet have a concept of multi-turn conversations beyond individual invocations.

### Chat list = grouped audit rows
Each "chat" in the list is a distinct `trace_id` from `a2a_message_audit`. The list shows:
- trace_id (clickable → trace view)
- agent name
- skill name
- status (completed / failed)
- duration_ms
- created_at
- message count per trace (if multiple invocations share a trace)

### Chat detail = audit row + OpenSearch enrichment
Clicking a chat row opens a detail panel that shows:
- Input payload (from OpenSearch `step_input` on `invoke_receive` event)
- Agent response (from OpenSearch `step_output` on `invoke_complete` event)
- Agent name, skill, status, duration, timestamps
- Link to full trace view

### Pagination
Use cursor-based pagination on `created_at DESC` for the chat list (same pattern as logs).

## Risks / Trade-offs
- **OpenSearch dependency for payloads**: If OpenSearch is down, chat list still works (from Postgres), but detail view will lack message content → show graceful fallback message
- **Large trace_id groups**: A single trace may have many invocations; the detail view should handle this with collapsible sections

## Open Questions
- Should we add a "conversation" concept for multi-turn chats in the future? (out of scope for this change)
