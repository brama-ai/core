# Change: Add TelegramDeliveryAdapter for delivery-channels integration

## Why

The delivery-channels abstraction (`add-delivery-channels`) provides a channel-agnostic outbound push system, and the Telegram outbound messaging spec (`telegram-outbound-messaging`) defines a `TelegramDeliveryAdapter` requirement — but no adapter implementation exists yet. Without this adapter, the scheduler, agents, and any proactive push workflow cannot deliver messages to Telegram chats through the standard `DeliveryService` pipeline. This is the bridge between the delivery-channels abstraction and the already-implemented `TelegramSender` service.

Reference: `brama-core/openspec/changes/archive/2026-03-21-add-telegram-bot-integration/tasks.md` section 7 (tasks 7.1–7.6).

## What Changes

- **New class** `App\Telegram\Delivery\TelegramDeliveryAdapter` implementing `ChannelAdapterInterface` — bridges `DeliveryPayload` to `TelegramSender::send()` for Telegram-specific delivery
- **Address parsing** — `DeliveryTarget.address` parsed as `chat_id` or `chat_id:thread_id` format to resolve Telegram chat and optional forum topic
- **Content type mapping** — `DeliveryPayload.content_type` mapped to Telegram parse mode: `markdown` to MarkdownV2, `text` to plain text (no parse mode), `card` to HTML
- **Result mapping** — Telegram API response mapped to `DeliveryResult` with `message_id` as `external_message_id`
- **Service registration** — adapter registered in `config/services.yaml` with `delivery.adapter` tag and `type: telegram` attribute
- **Bot resolution** — adapter requires a `bot_id` from channel config metadata to resolve the correct bot token via `TelegramBotRegistry`
- **Unit tests** — full test coverage for address parsing, content type mapping, success/failure result mapping, and bot-not-found handling

## Impact

- Affected specs: modifies `delivery-channels` (adds Telegram adapter requirement)
- Affected code:
  - `src/src/Telegram/Delivery/TelegramDeliveryAdapter.php` (new)
  - `src/config/services.yaml` (adapter registration)
  - `src/tests/Unit/Telegram/Delivery/TelegramDeliveryAdapterTest.php` (new)
- Dependencies: requires `TelegramSender` (implemented), `TelegramBotRegistry` (implemented), `ChannelAdapterInterface` (defined in `add-delivery-channels` spec, not yet implemented — this proposal assumes the interface exists or is created first)
