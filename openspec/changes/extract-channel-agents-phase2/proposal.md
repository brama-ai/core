# Change: Extract Channel Agents Phase 2 — Core Services

## Why

Phase 1 moved platform-level abstractions (DTOs, interfaces, command router, event publisher) from `Telegram/` to `Channel/`. The abstractions are in place, but core still has no way to **route** messages through channel agents via A2A. There is no generic channel registry, no credential vault separated from `TelegramBotRepository`, no outbound routing service, no generic webhook controller, and no channel-agnostic conversation tracker.

Phase 2 builds the five core services that form the backbone of the multi-channel architecture: `ChannelRegistry`, `ChannelCredentialVault`, `ChannelManager`, `ChannelWebhookController`, and `ConversationTracker`. These services route inbound and outbound traffic through A2A to channel agents, making core channel-agnostic.

## What Changes

- **New service** `App\Channel\ChannelRegistry` — maps `channel_type` to `agent_name`, reads from `telegram_bots` table (with added `channel_type` + `agent_name` columns), in-memory cache with TTL
- **New service** `App\Channel\ChannelCredentialVault` — extracts encryption/decryption logic from `TelegramBotRepository` into a generic credential storage service keyed by channel instance ID
- **New service** `App\Channel\ChannelManager` — outbound routing: `send(channelType, target, payload)` resolves agent via `ChannelRegistry`, gets credential via `ChannelCredentialVault`, calls `channel.sendOutbound` via `A2AClientInterface`
- **New controller** `App\Controller\Api\Webhook\ChannelWebhookController` at `/api/v1/webhook/{channelType}/{channelId}` — validates via agent, normalizes via agent, tracks conversation, routes platform commands, publishes to EventBus. Legacy alias for `/api/v1/webhook/telegram/{botId}`
- **New service** `App\Channel\ConversationTracker` — extracted from `TelegramChatTracker`, made channel-agnostic, works with `channel_conversations` table (currently `telegram_chats` with added `channel_type` column)
- **Schema additions** — `channel_type` and `agent_name` columns on `telegram_bots`, `channel_type` column on `telegram_chats` (table renames deferred to Phase 3)
- **Env var** — `CHANNEL_ENCRYPTION_KEY` with fallback to `TELEGRAM_ENCRYPTION_KEY`

## Impact

- Affected specs: `channel-abstractions` (ADDED: ChannelRegistry, ChannelCredentialVault, ChannelManager, ConversationTracker), new `channel-webhook` capability
- Affected code:
  - `src/src/Channel/ChannelRegistry.php` (new)
  - `src/src/Channel/ChannelCredentialVault.php` (new)
  - `src/src/Channel/ChannelManager.php` (new)
  - `src/src/Channel/ConversationTracker.php` (new)
  - `src/src/Controller/Api/Webhook/ChannelWebhookController.php` (new)
  - `src/config/routes/webhook.yaml` (new routes)
  - `src/config/services.yaml` (service wiring)
  - Symfony migration for schema additions
- Dependencies: `A2AClientInterface` (existing), `EventBusInterface` (existing), `ChannelEventPublisher` (Phase 1), `PlatformCommandRouter` (Phase 1)
- Related changes: `extract-channel-agents-phase1` (prerequisite, completed), `add-telegram-delivery-adapter`, `add-telegram-admin-ui`
- Parent spec: `agentic-development/openspec/changes/extract-channel-agents/` (Phase 2 of 6)
