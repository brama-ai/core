# Design: Extract Channel Agents Phase 2 — Core Services

## Context

Phase 1 established the `App\Channel\` namespace with DTOs, `ChannelAdapterInterface`, `PlatformCommandRouter`, and `ChannelEventPublisher`. All are channel-agnostic abstractions. However, the actual message routing still goes through Telegram-specific code paths (`TelegramBotRegistry`, `TelegramBotRepository` encryption, `TelegramChatTracker`, direct `TelegramSender` calls).

Phase 2 introduces five new core services that bridge the gap between the channel-agnostic abstractions and channel agents via A2A. After Phase 2, core has a complete inbound+outbound routing layer that works with any channel agent.

### Stakeholders

- Core platform — gains multi-channel routing capability
- Business agents — will use `ChannelManager` for outbound messages (future phases)
- Channel agents — will be called via A2A by `ChannelManager` and `ChannelWebhookController`

## Goals / Non-Goals

### Goals

- `ChannelRegistry` provides a single source of truth for channel_type → agent_name mapping
- `ChannelCredentialVault` decouples encryption from `TelegramBotRepository`, making it reusable for any channel
- `ChannelManager` provides a single outbound API: `send(channelType, target, payload)`
- `ChannelWebhookController` provides a single inbound endpoint for all channels
- `ConversationTracker` tracks conversations across all channels in a unified table
- All new services work alongside existing Telegram code — no breaking changes

### Non-Goals

- Replacing `TelegramBotRepository` or `TelegramBotRegistry` (they continue to work for Telegram-specific admin operations)
- Renaming database tables (deferred to Phase 3)
- Creating the telegram-channel-agent (Phase 4)
- Switching traffic from old to new paths (Phase 5)

## Decisions

### 1. ChannelRegistry reads from existing `telegram_bots` table

Rather than creating a new `channel_instances` table now, we add `channel_type` (VARCHAR(50), default 'telegram') and `agent_name` (VARCHAR(255), nullable) columns to the existing `telegram_bots` table. This avoids a table rename migration in Phase 2 and keeps the existing admin UI working.

The `ChannelRegistry` reads from this table using a simple DBAL query filtered by `channel_type`. It does NOT depend on `TelegramBotRepository` — it has its own read path.

**Cache strategy:** In-memory array cache with 300s TTL, same pattern as `TelegramBotRegistry`. The cache stores `channel_type → agent_name` mappings. Cache is invalidated on `register()` calls.

**Why not Redis cache?** The mapping is small (one row per channel type), changes rarely, and the in-memory TTL approach is already proven in `TelegramBotRegistry`. Redis adds complexity without benefit at this scale.

### 2. ChannelCredentialVault extracts encryption, does not replace repository

`TelegramBotRepository` currently owns both CRUD operations and encryption. `ChannelCredentialVault` extracts only the encryption/decryption concern:

```php
class ChannelCredentialVault
{
    public function __construct(private readonly Connection $connection, private readonly string $encryptionKey) {}

    public function encrypt(string $plainCredential): string;
    public function decrypt(string $channelInstanceId): string;
    public function getCredentialRef(string $channelInstanceId): string; // returns the instance ID itself
}
```

The `decrypt()` method reads `bot_token_encrypted` (later `credential_encrypted`) directly from the database by instance ID. This means `ChannelCredentialVault` has a read dependency on the `telegram_bots` table but does NOT go through `TelegramBotRepository`.

**Env var:** `CHANNEL_ENCRYPTION_KEY` with fallback to `TELEGRAM_ENCRYPTION_KEY`. This allows gradual migration without breaking existing deployments.

**Why not pass decrypted token through ChannelManager?** We do — Option A from the parent design. The vault decrypts the token and `ChannelManager` passes it in the A2A call payload. The `getCredentialRef()` method exists for future Option B (credential-ref pattern) but currently just returns the instance ID.

### 3. ChannelManager — thin A2A routing layer

```php
class ChannelManager
{
    public function send(string $channelType, DeliveryTarget $target, DeliveryPayload $payload): DeliveryResult
    {
        $agentName = $this->registry->resolveAgent($channelType);
        $token = $this->vault->decrypt($payload->channelInstanceId);

        return DeliveryResult::fromArray(
            $this->a2a->invoke(
                tool: $agentName . '/channel.sendOutbound',
                input: [
                    'target' => $target->toArray(),
                    'payload' => $payload->toArray(),
                    'token' => $token,
                ],
                traceId: $payload->traceId ?? '',
                requestId: uniqid('cm-', true),
            )
        );
    }
}
```

The manager is intentionally thin — it resolves the agent, gets the credential, and delegates to A2A. No retry logic, no circuit breaker (those belong in `A2AClient`).

**Error handling:** If `resolveAgent()` fails (unknown channel), throw `ChannelNotFoundException`. If A2A call fails, let the `A2AClientInterface` exception propagate — the caller decides how to handle it.

### 4. ChannelWebhookController — two A2A calls per request

The controller handles: validate → normalize → track → route commands → publish events.

```
POST /api/v1/webhook/{channelType}/{channelId}
  1. resolveAgent(channelType)
  2. A2A: channel.validateWebhook(channelId, headers, body) → {valid: bool}
  3. A2A: channel.normalizeInbound(rawPayload, channelId) → NormalizedEvent
  4. ConversationTracker::track(channelType, event)
  5. PlatformCommandRouter::route(event) — if command, handle + return
  6. ChannelEventPublisher::publish(event)
```

**Legacy alias:** `/api/v1/webhook/telegram/{botId}` maps to `channelType=telegram`, `channelId={botId}`. This is a Symfony route alias, not a redirect.

**Why two A2A calls (validate + normalize) instead of one?** Separation of concerns: validation can reject the request before any normalization work. In practice, both calls go to the same agent and the overhead is ~5ms total on local network. A combined call can be added later as an optimization.

**Response:** Always returns `200 OK` with body `"ok"` for valid webhooks (Telegram expects this). Returns `403` for failed validation. Returns `500` for internal errors (logged, not exposed).

### 5. ConversationTracker — channel-agnostic chat tracking

Extracted from `TelegramChatTracker` with these changes:

- Accepts `string $channelType` as first parameter instead of being Telegram-implicit
- Reads/writes `telegram_chats` table (with added `channel_type` column; table rename deferred to Phase 3)
- Uses `channel_instance_id` (currently `bot_id`) + `channel_type` + `chat_id` as the composite lookup key
- Bot join/leave detection remains the same logic, just parameterized by channel type

```php
class ConversationTracker
{
    public function track(string $channelType, NormalizedEvent $event): void;
    public function findConversation(string $channelType, string $chatId): ?array;
}
```

The tracker does NOT depend on `TelegramChatRepository` — it has its own DBAL queries against the same table. This avoids circular dependencies and allows the table to be renamed in Phase 3 without affecting this service.

### 6. Schema additions (not renames)

Phase 2 adds columns only — no table renames:

```sql
ALTER TABLE telegram_bots ADD COLUMN channel_type VARCHAR(50) NOT NULL DEFAULT 'telegram';
ALTER TABLE telegram_bots ADD COLUMN agent_name VARCHAR(255);
ALTER TABLE telegram_chats ADD COLUMN channel_type VARCHAR(50) NOT NULL DEFAULT 'telegram';

CREATE INDEX idx_telegram_bots_channel_type ON telegram_bots(channel_type);
CREATE INDEX idx_telegram_chats_channel_type ON telegram_chats(channel_type);
```

Existing rows get `channel_type = 'telegram'` via the DEFAULT. `agent_name` is nullable because the telegram-channel-agent doesn't exist yet — it will be populated in Phase 4.

Table renames (`telegram_bots` → `channel_instances`, `telegram_chats` → `channel_conversations`) and column renames (`bot_token_encrypted` → `credential_encrypted`) are deferred to Phase 3.

## Risks / Trade-offs

| Risk | Impact | Mitigation |
|------|--------|------------|
| New services duplicate some `TelegramBotRepository` read logic | Minor code duplication | Intentional — avoids coupling new services to Telegram-specific repository. Duplication removed when `TelegramBotRepository` is deprecated in Phase 5 |
| `ChannelWebhookController` won't work until a channel agent exists | Controller is dead code until Phase 4 | Acceptable — controller is tested with mocked A2A client. Integration testing happens in Phase 4 |
| `agent_name` column is nullable (no agent registered yet) | `ChannelRegistry::resolveAgent()` may return null | Throw `ChannelNotFoundException` with clear message. Telegram path continues to work through existing code |
| Two A2A calls per webhook add latency | ~10ms on local network | Negligible vs Telegram's 100-500ms API latency. Can be optimized to single call later |

## Migration Plan

1. Run Symfony migration to add columns (non-breaking, all have defaults)
2. Deploy new services (they're unused until Phase 4 wires them)
3. Write unit tests with mocked dependencies
4. Write integration tests with mocked A2A client

**Rollback:** Drop the added columns. Remove new service files. No data loss.

## Open Questions

- None — all architectural decisions are inherited from the parent design doc (`agentic-development/openspec/changes/extract-channel-agents/design.md`). Phase 2 is a straightforward implementation of those decisions.
