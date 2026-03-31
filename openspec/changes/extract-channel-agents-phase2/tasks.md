# Tasks: Extract Channel Agents Phase 2 — Core Services

**Change ID:** `extract-channel-agents-phase2`
**Parent:** `agentic-development/openspec/changes/extract-channel-agents/` (Phase 2)
**Prerequisite:** `extract-channel-agents-phase1` (completed)

## 1. Database Schema Additions

- [ ] 1.1 Create Symfony migration to add columns to `telegram_bots`
  - Add `channel_type VARCHAR(50) NOT NULL DEFAULT 'telegram'`
  - Add `agent_name VARCHAR(255) DEFAULT NULL`
  - Add index `idx_telegram_bots_channel_type` on `channel_type`
  - **Verify:** migration runs clean, existing queries unaffected, all existing rows have `channel_type = 'telegram'`
  - **Impl:** `src/migrations/Version2026MMDD000001.php`

- [ ] 1.2 Create Symfony migration to add `channel_type` column to `telegram_chats`
  - Add `channel_type VARCHAR(50) NOT NULL DEFAULT 'telegram'`
  - Add index `idx_telegram_chats_channel_type` on `channel_type`
  - **Verify:** migration runs clean, existing queries unaffected, all existing rows have `channel_type = 'telegram'`
  - **Impl:** `src/migrations/Version2026MMDD000002.php`

## 2. ChannelRegistry

- [ ] 2.1 Create `App\Channel\ChannelRegistry`
  - Constructor: `Connection $connection`, `LoggerInterface $logger`
  - Method: `resolveAgent(string $channelType): string` — returns agent_name, throws `ChannelNotFoundException` if not found
  - Method: `register(string $channelType, string $agentName): void` — upserts mapping in DB
  - Method: `listChannels(): array` — returns all channel_type → agent_name mappings
  - In-memory cache with 300s TTL (same pattern as `TelegramBotRegistry`)
  - Cache invalidated on `register()` calls
  - **Verify:** unit test covers resolve success, resolve missing channel (exception), cache TTL expiry, register + resolve roundtrip
  - **Impl:** `src/src/Channel/ChannelRegistry.php`

- [ ] 2.2 Create `App\Channel\Exception\ChannelNotFoundException`
  - Extends `\RuntimeException`
  - Constructor accepts `string $channelType`
  - Message: `Channel type "{channelType}" is not registered`
  - **Verify:** PHPStan passes
  - **Impl:** `src/src/Channel/Exception/ChannelNotFoundException.php`

## 3. ChannelCredentialVault

- [ ] 3.1 Create `App\Channel\ChannelCredentialVault`
  - Constructor: `Connection $connection`, `string $encryptionKey`
  - Method: `encrypt(string $plainCredential): string` — sodium secretbox encryption (extracted from `TelegramBotRepository`)
  - Method: `decrypt(string $channelInstanceId): string` — reads `bot_token_encrypted` from `telegram_bots` by ID, decrypts
  - Method: `getCredentialRef(string $channelInstanceId): string` — returns the instance ID (placeholder for future credential-ref pattern)
  - Uses `CHANNEL_ENCRYPTION_KEY` env var with fallback to `TELEGRAM_ENCRYPTION_KEY`
  - **Verify:** existing encrypted tokens decrypt correctly via vault, new tokens encrypt/decrypt roundtrip, missing instance ID throws exception
  - **Impl:** `src/src/Channel/ChannelCredentialVault.php`

- [ ] 3.2 Register `ChannelCredentialVault` in Symfony services
  - Bind `$encryptionKey` to `%env(default:TELEGRAM_ENCRYPTION_KEY:CHANNEL_ENCRYPTION_KEY)%`
  - **Verify:** service container compiles, vault is injectable
  - **Impl:** `src/config/services.yaml`

## 4. ChannelManager

- [ ] 4.1 Create `App\Channel\ChannelManager`
  - Constructor: `ChannelRegistry $registry`, `A2AClientInterface $a2a`, `ChannelCredentialVault $vault`
  - Method: `send(string $channelType, DeliveryTarget $target, DeliveryPayload $payload): DeliveryResult`
    - Resolves agent via `ChannelRegistry::resolveAgent($channelType)`
    - Decrypts credential via `ChannelCredentialVault::decrypt($payload->channelInstanceId)`
    - Invokes `{agentName}/channel.sendOutbound` via `A2AClientInterface::invoke()`
    - Returns `DeliveryResult::fromArray()` from A2A response
  - **Verify:** unit test with mocked A2A client — successful send, channel resolution failure (`ChannelNotFoundException`), A2A invocation failure
  - **Impl:** `src/src/Channel/ChannelManager.php`

## 5. ChannelWebhookController

- [ ] 5.1 Create `App\Controller\Api\Webhook\ChannelWebhookController`
  - Route: `POST /api/v1/webhook/{channelType}/{channelId}`
  - Legacy alias route: `POST /api/v1/webhook/telegram/{botId}` (maps to `channelType=telegram`)
  - Flow:
    1. Resolve agent via `ChannelRegistry::resolveAgent($channelType)`
    2. Call `channel.validateWebhook` via A2A — return 403 if invalid
    3. Call `channel.normalizeInbound` via A2A — get `NormalizedEvent`
    4. Track conversation via `ConversationTracker::track($channelType, $event)`
    5. If command event, route via `PlatformCommandRouter::route($event)` — if handled, return 200
    6. Publish to EventBus via `ChannelEventPublisher::publish($event)`
    7. Return 200 "ok"
  - Error handling: catch exceptions, log, return 500
  - **Verify:** integration test with mocked A2A: raw payload in → NormalizedEvent dispatched; validation failure → 403; legacy alias works
  - **Impl:** `src/src/Controller/Api/Webhook/ChannelWebhookController.php`

- [ ] 5.2 Configure routes
  - Add route definitions for the new controller
  - Ensure legacy alias route is registered
  - **Verify:** `php bin/console debug:router | grep webhook` shows both routes
  - **Impl:** `src/config/routes/webhook.yaml` or controller annotations/attributes

## 6. ConversationTracker

- [ ] 6.1 Create `App\Channel\ConversationTracker`
  - Constructor: `Connection $connection`, `LoggerInterface $logger`
  - Method: `track(string $channelType, NormalizedEvent $event): void`
    - Upserts conversation in `telegram_chats` table using `channel_type` + `bot_id` + `chat_id` as composite key
    - Handles bot join/leave events (same logic as `TelegramChatTracker`)
    - Updates title if changed, detects thread support
    - Updates `last_message_at` on regular messages
  - Method: `findConversation(string $channelType, string $chatId): ?array`
    - Finds conversation by `channel_type` and `chat_id`
  - Uses own DBAL queries (does NOT depend on `TelegramChatRepository`)
  - **Verify:** unit test covers: track new conversation, track existing (update title), bot join, bot leave, find conversation, find missing conversation returns null
  - **Impl:** `src/src/Channel/ConversationTracker.php`

## 7. Service Wiring

- [ ] 7.1 Register all new services in Symfony container
  - `ChannelRegistry` — autowired
  - `ChannelCredentialVault` — with `$encryptionKey` binding
  - `ChannelManager` — autowired
  - `ConversationTracker` — autowired
  - `ChannelWebhookController` — autowired (controller auto-registration)
  - **Verify:** `php bin/console debug:container | grep Channel` shows all services
  - **Impl:** `src/config/services.yaml`

## 8. Documentation

- [ ] 8.1 Update or create `docs/channel-routing.md` documenting the new services
  - Describe `ChannelRegistry`, `ChannelCredentialVault`, `ChannelManager`, `ConversationTracker`
  - Include inbound and outbound data flow diagrams
  - Document the webhook endpoint and legacy alias
  - **Verify:** document exists and is accurate
  - **Impl:** `docs/channel-routing.md`

- [ ] 8.2 Update `docs/agent-requirements/conventions.md` if channel agent A2A contract details are affected
  - **Verify:** conventions doc reflects any new A2A skill expectations
  - **Impl:** `docs/agent-requirements/conventions.md`

## 9. Quality Checks

- [ ] 9.1 PHPStan passes at configured level with zero errors
- [ ] 9.2 PHP CS Fixer reports no violations
- [ ] 9.3 All existing unit and functional tests pass (no regressions)
- [ ] 9.4 New unit tests for all five services pass
