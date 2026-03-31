## ADDED Requirements

### Requirement: Channel Registry

The platform SHALL provide `App\Channel\ChannelRegistry` that maintains a mapping of channel types to their handling agent names. The registry MUST:

- Read channel_type → agent_name mappings from the `telegram_bots` table (using `channel_type` and `agent_name` columns)
- Maintain an in-memory cache with a 300-second TTL
- Invalidate the cache when a new mapping is registered
- Throw `App\Channel\Exception\ChannelNotFoundException` when a channel type has no registered agent

#### Scenario: Resolve agent for registered channel type

- **WHEN** `ChannelRegistry::resolveAgent("telegram")` is called
- **AND** a row in `telegram_bots` has `channel_type = "telegram"` and `agent_name = "telegram-channel-agent"`
- **THEN** the method returns `"telegram-channel-agent"`

#### Scenario: Resolve agent for unregistered channel type

- **WHEN** `ChannelRegistry::resolveAgent("discord")` is called
- **AND** no row in `telegram_bots` has `channel_type = "discord"` with a non-null `agent_name`
- **THEN** the method throws `ChannelNotFoundException` with message containing `"discord"`

#### Scenario: Register a new channel mapping

- **WHEN** `ChannelRegistry::register("viber", "viber-channel-agent")` is called
- **THEN** the mapping is persisted to the database
- **AND** subsequent calls to `resolveAgent("viber")` return `"viber-channel-agent"` without waiting for cache TTL

#### Scenario: Cache TTL expiry refreshes mappings

- **WHEN** `resolveAgent("telegram")` is called and the cache is populated
- **AND** the `agent_name` is updated in the database
- **AND** more than 300 seconds have elapsed since the last cache refresh
- **THEN** the next call to `resolveAgent("telegram")` returns the updated agent name

#### Scenario: List all channel mappings

- **WHEN** `ChannelRegistry::listChannels()` is called
- **AND** two channel types are registered: `telegram → telegram-channel-agent` and `viber → viber-channel-agent`
- **THEN** the method returns an array with both mappings

### Requirement: Channel Credential Vault

The platform SHALL provide `App\Channel\ChannelCredentialVault` that handles encryption and decryption of channel instance credentials. The vault MUST:

- Use sodium secretbox encryption (same algorithm as `TelegramBotRepository`)
- Read the encryption key from `CHANNEL_ENCRYPTION_KEY` env var with fallback to `TELEGRAM_ENCRYPTION_KEY`
- Decrypt credentials by reading `bot_token_encrypted` from `telegram_bots` table by instance ID
- Be independent of `TelegramBotRepository` (own DBAL queries)

#### Scenario: Encrypt and decrypt roundtrip

- **WHEN** `ChannelCredentialVault::encrypt("my-secret-token")` is called
- **AND** the result is stored in the database
- **AND** `ChannelCredentialVault::decrypt($instanceId)` is called for that instance
- **THEN** the decrypted value equals `"my-secret-token"`

#### Scenario: Decrypt existing Telegram bot token

- **WHEN** a `telegram_bots` row has a `bot_token_encrypted` value encrypted by `TelegramBotRepository`
- **AND** `ChannelCredentialVault::decrypt($botId)` is called with the same encryption key
- **THEN** the decrypted token matches the original plaintext token

#### Scenario: Decrypt with missing instance ID

- **WHEN** `ChannelCredentialVault::decrypt("nonexistent-id")` is called
- **AND** no row with that ID exists in `telegram_bots`
- **THEN** a `\RuntimeException` is thrown with a message indicating the instance was not found

#### Scenario: Get credential reference

- **WHEN** `ChannelCredentialVault::getCredentialRef("bot-123")` is called
- **THEN** the method returns `"bot-123"` (the instance ID itself, for future credential-ref pattern)

### Requirement: Channel Manager Outbound Routing

The platform SHALL provide `App\Channel\ChannelManager` that routes outbound messages to channel agents via A2A. The manager MUST:

- Resolve the channel agent via `ChannelRegistry::resolveAgent()`
- Decrypt the credential via `ChannelCredentialVault::decrypt()`
- Invoke `channel.sendOutbound` on the resolved agent via `A2AClientInterface`
- Return a `DeliveryResult` from the A2A response

#### Scenario: Successful outbound send

- **WHEN** `ChannelManager::send("telegram", $target, $payload)` is called
- **AND** `ChannelRegistry` resolves `"telegram"` to `"telegram-channel-agent"`
- **AND** `ChannelCredentialVault` decrypts the credential for the payload's `channelInstanceId`
- **AND** the A2A call to `channel.sendOutbound` succeeds
- **THEN** a `DeliveryResult` is returned with the agent's response data

#### Scenario: Send to unregistered channel type

- **WHEN** `ChannelManager::send("unknown", $target, $payload)` is called
- **AND** `ChannelRegistry` has no agent for `"unknown"`
- **THEN** `ChannelNotFoundException` is thrown

#### Scenario: A2A invocation failure propagates

- **WHEN** `ChannelManager::send("telegram", $target, $payload)` is called
- **AND** the A2A call to the channel agent fails with an exception
- **THEN** the exception propagates to the caller (no silent swallowing)

### Requirement: Conversation Tracker

The platform SHALL provide `App\Channel\ConversationTracker` that tracks conversations across all channel types. The tracker MUST:

- Accept `channelType` as a parameter to all methods
- Read/write the `telegram_chats` table using `channel_type` + `bot_id` + `chat_id` as composite lookup key
- Handle bot join and leave events by updating join/leave timestamps
- Update conversation title when it changes
- Detect thread support from incoming events
- Update `last_message_at` on regular message events
- Be independent of `TelegramChatRepository` (own DBAL queries)

#### Scenario: Track new conversation from message

- **WHEN** `ConversationTracker::track("telegram", $event)` is called
- **AND** no conversation exists for the event's `channel_type + bot_id + chat_id`
- **THEN** a new row is created in `telegram_chats` with `channel_type = "telegram"`, the event's chat metadata, and `last_message_at` set to now

#### Scenario: Track existing conversation updates title

- **WHEN** `ConversationTracker::track("telegram", $event)` is called
- **AND** a conversation exists but the event's chat title differs from the stored title
- **THEN** the stored title is updated to match the event's chat title

#### Scenario: Track bot join event

- **WHEN** `ConversationTracker::track("telegram", $event)` is called
- **AND** `$event->eventType` is `"member_joined"` and `$event->sender->isBot` is `true`
- **THEN** the conversation is upserted with `joined_at` set to now and `left_at` set to null

#### Scenario: Track bot leave event

- **WHEN** `ConversationTracker::track("telegram", $event)` is called
- **AND** `$event->eventType` is `"member_left"` and `$event->sender->isBot` is `true`
- **THEN** the conversation's `left_at` is set to now

#### Scenario: Find existing conversation

- **WHEN** `ConversationTracker::findConversation("telegram", "12345")` is called
- **AND** a conversation exists with `channel_type = "telegram"` and `chat_id = 12345`
- **THEN** the conversation data array is returned

#### Scenario: Find non-existent conversation

- **WHEN** `ConversationTracker::findConversation("discord", "99999")` is called
- **AND** no conversation exists with those parameters
- **THEN** `null` is returned
