# Change: Add Knowledge Message Store Skill

## Why

For Telegram-first MVP flows we need a low-cost, deterministic path to persist incoming chat messages with rich metadata before any expensive extraction. Today `knowledge-agent` can queue extraction but has no dedicated A2A skill for raw message persistence.

## What Changes

- Add a new A2A skill `knowledge.store_message` in `knowledge-agent`.
- Persist message payloads and normalized metadata into Postgres table `knowledge_source_messages`.
- Store both structured fields (author/chat/time/channel/message id) and full raw payload JSON.
- Add idempotent upsert by `(source_platform, chat_id, message_id)`.
- Keep compatibility aliases for existing ingestion intent names where possible.
- Add unit, functional, and E2E tests for the new skill through the core A2A gateway.

## Impact

- Affected specs: `knowledge-ingestion`
- Affected code:
  - `apps/knowledge-agent/` (migration, repository, A2A handler, manifest)
  - `tests/e2e/` (A2A bridge scenario)
  - `Makefile` (E2E knowledge-agent registration skill list)
