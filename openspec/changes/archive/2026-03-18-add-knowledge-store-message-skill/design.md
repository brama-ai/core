## Context

OpenClaw-first MVP needs a reliable storage operation for Telegram message events without running extraction on every message. The operation must preserve full event context for future processing and auditability.

## Goals / Non-Goals

- Goals:
  - Add `knowledge.store_message` skill for raw message persistence.
  - Persist normalized metadata + original raw payload.
  - Keep ingestion idempotent for repeated Telegram delivery.
- Non-Goals:
  - Real-time extraction on every stored message.
  - New moderation or routing logic in core event bus.

## Data Model

Create table `knowledge_source_messages` with:
- `id` UUID PK
- source/event fields: `source_platform`, `event_type`
- channel/message identity: `chat_id`, `chat_title`, `chat_type`, `channel`, `message_id`, `thread_id`
- sender fields: `sender_id`, `sender_username`, `sender_display_name`
- content/time: `message_text`, `message_timestamp`
- correlation: `trace_id`, `request_id`
- `metadata` JSONB
- `raw_payload` JSONB
- `created_at` timestamp

Unique key: `(source_platform, chat_id, message_id)` for idempotent upsert.

## Skill Contract

`intent: knowledge.store_message`

Input:
- `message` object (preferred) or flat payload
- optional `metadata` / `meta`

Output:
- `status: completed`
- `result.stored: true`
- `result.id`: persisted row id

## Compatibility

- Keep `knowledge.upload` mapped to existing extraction queue behavior.
- Accept both dot and snake style intent aliases in `KnowledgeA2AHandler`.

## Test Plan

- Unit: repository upsert behavior and metadata persistence.
- Functional: `/api/v1/knowledge/a2a` with `knowledge.store_message` returns completed result.
- E2E: `/api/v1/a2a/send-message` with `knowledge.store_message` succeeds and writes into `knowledge_source_messages`.
