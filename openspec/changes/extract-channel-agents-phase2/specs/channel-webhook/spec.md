## ADDED Requirements

### Requirement: Channel Webhook Controller

The platform SHALL provide `App\Controller\Api\Webhook\ChannelWebhookController` that serves as the unified inbound webhook endpoint for all channel types. The controller MUST:

- Accept POST requests at `/api/v1/webhook/{channelType}/{channelId}`
- Resolve the channel agent via `ChannelRegistry`
- Validate the webhook by calling `channel.validateWebhook` on the agent via A2A
- Normalize the raw payload by calling `channel.normalizeInbound` on the agent via A2A
- Track the conversation via `ConversationTracker`
- Route platform commands via `PlatformCommandRouter`
- Publish remaining events via `ChannelEventPublisher`
- Return HTTP 200 with body `"ok"` for valid webhooks
- Return HTTP 403 for failed webhook validation
- Log and return HTTP 500 for internal errors

#### Scenario: Successful inbound message processing

- **WHEN** a POST request arrives at `/api/v1/webhook/telegram/bot-123` with a valid Telegram update payload
- **AND** `ChannelRegistry` resolves `"telegram"` to `"telegram-channel-agent"`
- **AND** the agent's `channel.validateWebhook` returns `{valid: true}`
- **AND** the agent's `channel.normalizeInbound` returns a valid `NormalizedEvent` with `eventType: "message_received"`
- **THEN** `ConversationTracker::track("telegram", $event)` is called
- **AND** `ChannelEventPublisher::publish($event)` is called
- **AND** the response is HTTP 200 with body `"ok"`

#### Scenario: Webhook validation failure

- **WHEN** a POST request arrives at `/api/v1/webhook/telegram/bot-123`
- **AND** the agent's `channel.validateWebhook` returns `{valid: false}`
- **THEN** the response is HTTP 403
- **AND** no normalization, tracking, or event publishing occurs

#### Scenario: Platform command is handled

- **WHEN** a POST request arrives at `/api/v1/webhook/telegram/bot-123`
- **AND** the normalized event has `eventType: "command_received"` with `commandName: "/help"`
- **THEN** `PlatformCommandRouter::route($event)` is called
- **AND** the command response is sent back through `ChannelManager`
- **AND** the response is HTTP 200 with body `"ok"`
- **AND** `ChannelEventPublisher::publish()` is NOT called for handled platform commands

#### Scenario: Unregistered channel type

- **WHEN** a POST request arrives at `/api/v1/webhook/unknown/instance-1`
- **AND** `ChannelRegistry` has no agent for `"unknown"`
- **THEN** the response is HTTP 404
- **AND** no A2A calls are made

#### Scenario: A2A call failure returns 500

- **WHEN** a POST request arrives at `/api/v1/webhook/telegram/bot-123`
- **AND** the A2A call to the channel agent fails with an exception
- **THEN** the error is logged
- **AND** the response is HTTP 500

### Requirement: Legacy Telegram Webhook Alias

The platform SHALL maintain a legacy webhook route at `/api/v1/webhook/telegram/{botId}` that maps to the generic `ChannelWebhookController` with `channelType = "telegram"`. This MUST:

- Accept the same request format as the existing Telegram webhook endpoint
- Route through the same controller logic as `/api/v1/webhook/{channelType}/{channelId}`
- Ensure existing Telegram webhook registrations continue to work without URL changes

#### Scenario: Legacy Telegram webhook URL works

- **WHEN** a POST request arrives at `/api/v1/webhook/telegram/bot-123`
- **THEN** it is handled by `ChannelWebhookController` with `channelType = "telegram"` and `channelId = "bot-123"`
- **AND** the processing is identical to a request at `/api/v1/webhook/telegram/bot-123` via the generic route

#### Scenario: Generic route also works for Telegram

- **WHEN** a POST request arrives at `/api/v1/webhook/telegram/bot-123` via the generic `{channelType}/{channelId}` route pattern
- **THEN** it is handled identically to the legacy alias route

### Requirement: Channel Webhook Schema Additions

The platform SHALL add the following columns to support multi-channel routing:

- `telegram_bots.channel_type` — `VARCHAR(50) NOT NULL DEFAULT 'telegram'` with index
- `telegram_bots.agent_name` — `VARCHAR(255) DEFAULT NULL`
- `telegram_chats.channel_type` — `VARCHAR(50) NOT NULL DEFAULT 'telegram'` with index

All existing rows MUST be backfilled with `channel_type = 'telegram'` via the column default. The migration MUST be non-breaking — existing queries that do not reference the new columns MUST continue to work.

#### Scenario: Existing telegram_bots rows get default channel_type

- **WHEN** the migration runs on a database with existing `telegram_bots` rows
- **THEN** all existing rows have `channel_type = 'telegram'`
- **AND** all existing rows have `agent_name = NULL`

#### Scenario: Existing telegram_chats rows get default channel_type

- **WHEN** the migration runs on a database with existing `telegram_chats` rows
- **THEN** all existing rows have `channel_type = 'telegram'`

#### Scenario: Existing queries are unaffected

- **WHEN** existing code queries `SELECT * FROM telegram_bots WHERE id = :id`
- **THEN** the query succeeds and returns the same data as before the migration (plus the new columns)
