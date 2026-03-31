## Context

The delivery-channels abstraction defines a `ChannelAdapterInterface` with `send(DeliveryPayload, array $channelConfig): DeliveryResult` and `supports(string $type): bool`. The Telegram integration already has a working `TelegramSender` service that wraps the Telegram Bot API. This adapter bridges the two: it receives a generic `DeliveryPayload` from `DeliveryService` and translates it into a `TelegramSender::send()` call.

The adapter is the "last mile" for proactive Telegram delivery — scheduled digests, agent-initiated notifications, and any future push workflow that targets a Telegram chat or forum topic.

## Goals / Non-Goals

- Goals:
  - Implement `ChannelAdapterInterface` for Telegram transport
  - Parse composite address format (`chat_id` or `chat_id:thread_id`) from `DeliveryTarget`
  - Map content types to Telegram parse modes correctly
  - Return structured `DeliveryResult` with Telegram's `message_id`
  - Register as a tagged service so `DeliveryService` discovers it automatically
  - Full unit test coverage

- Non-Goals:
  - Media delivery (photos, documents) — text-only in this adapter; media adapters are a separate concern
  - Rate limiting — handled by `DeliveryService` at the channel level, not inside the adapter
  - Idempotency — handled by `DeliveryService` before calling the adapter
  - Creating the `ChannelAdapterInterface` or `DeliveryPayload`/`DeliveryResult` VOs — those belong to the `add-delivery-channels` proposal

## Decisions

### 1. Bot ID from channel metadata
- **Decision**: The adapter reads `bot_id` from `$channelConfig['metadata']['bot_id']`. Each Telegram delivery channel record in `delivery_channels` table MUST include `{"bot_id": "<uuid>"}` in its `metadata` JSONB column.
- **Why**: The platform supports multiple bots. The adapter needs to know which bot token to use. Storing `bot_id` in channel metadata keeps the delivery-channels schema generic while allowing Telegram-specific config.
- **Alternatives considered**: Hardcoded default bot (breaks multi-bot), bot_id in address string (overloads the address format), separate `telegram_channel_config` table (over-engineered for one field).

### 2. Address format: `chat_id` or `chat_id:thread_id`
- **Decision**: `DeliveryTarget.address` uses colon-separated format. If no colon is present, the entire string is the `chat_id` and no thread targeting is applied. If a colon is present, the part before is `chat_id` and the part after is `thread_id`.
- **Why**: Simple, unambiguous, human-readable. Matches the format already specified in the archived `telegram-outbound-messaging` spec.
- **Alternatives considered**: JSON object in address (harder to configure in admin UI), separate fields (requires schema change to `delivery_channels`).

### 3. Content type to parse mode mapping
- **Decision**: `markdown` maps to `MarkdownV2`, `text` maps to no parse mode (plain text), `card` maps to `HTML`. Unknown content types default to no parse mode.
- **Why**: Aligns with Telegram's three parse modes. `card` uses HTML because structured card-like formatting (bold headers, lists, links) is more reliably rendered in HTML than MarkdownV2 (which has strict escaping rules). The `TelegramSender` already handles MarkdownV2-to-HTML fallback internally.

### 4. Adapter placement in Telegram namespace
- **Decision**: `App\Telegram\Delivery\TelegramDeliveryAdapter` — under the existing `Telegram` namespace, in a `Delivery` sub-namespace.
- **Why**: Keeps all Telegram-related code together. The adapter depends on `TelegramSender` and `TelegramBotRegistry`, both in `App\Telegram\Service`.
- **Alternatives considered**: `App\Delivery\Adapter\TelegramAdapter` (splits Telegram code across two top-level namespaces).

## Risks / Trade-offs

- `ChannelAdapterInterface` and delivery VOs do not exist in the codebase yet (the `add-delivery-channels` proposal is archived but not implemented). This adapter cannot be implemented until those are created.
  - Mitigation: tasks are ordered so that delivery-channels foundation is a prerequisite. The adapter can be implemented immediately after the interface and VOs land.
- `TelegramSender::send()` returns a raw Telegram API response array, not a typed result. The adapter must interpret `$result['ok']` and extract `$result['result']['message_id']`.
  - Mitigation: straightforward array access with null-safe defaults. Unit tests cover all response shapes.
- Multi-message splitting (>4096 chars) returns only the last message's ID. The adapter returns that as `external_message_id`.
  - Mitigation: acceptable for delivery logging. The full message was delivered; the ID references the final chunk.
