## ADDED Requirements

### Requirement: Channel Post Publishing
The platform SHALL support Telegram channels where the bot is an admin with `can_post_messages` permission, publishing content via `sendMessage`, `sendPhoto`, and `sendMediaGroup` Bot API methods.

#### Scenario: Bot publishes text post to channel
- **WHEN** the platform sends a text post to a channel where the bot is admin
- **THEN** `TelegramSender` calls `sendMessage` with the channel's `chat_id` and the formatted content
- **AND** the resulting `message_id` is stored for future reference (edits, reply buttons)

#### Scenario: Bot publishes photo with caption
- **WHEN** the platform sends a post with a single image to a channel
- **THEN** `TelegramSender` calls `sendPhoto` with `photo` (URL or file_id) and `caption` (up to 1024 chars)
- **AND** if the caption exceeds 1024 characters, the photo is sent with a short caption and a follow-up text message with the remaining content

#### Scenario: Bot publishes media album
- **WHEN** the platform sends a post with multiple images (2-10)
- **THEN** `TelegramSender` calls `sendMediaGroup` with an array of `InputMediaPhoto` items, each with optional individual caption

#### Scenario: Bot copies message to channel
- **WHEN** the platform republishes an existing message (e.g., approved user announcement)
- **THEN** `TelegramSender` calls `copyMessage` to forward content without "Forwarded from" label

---

### Requirement: Channel Post Buttons
Each published channel post SHALL support an optional `InlineKeyboardMarkup` with interactive buttons that trigger callback queries when pressed by channel subscribers.

#### Scenario: Post with action buttons
- **WHEN** the platform publishes a post with `reply_markup` containing inline keyboard buttons
- **THEN** the Telegram post displays the buttons below the content
- **AND** when a subscriber presses a button, a `callback_query` update is sent to the bot's webhook

#### Scenario: Pinned entry-point message
- **WHEN** the bot creates a channel's pinned welcome/entry-point message
- **THEN** the message includes persistent buttons (e.g., "Розмістити оголошення", "Запропонувати тему")
- **AND** the message is pinned via `pinChatMessage` API call

#### Scenario: Button counter update
- **WHEN** a post accumulates interactions (e.g., 3 proposals submitted)
- **THEN** the platform calls `editMessageReplyMarkup` to update button text with the counter (e.g., "Написати пропозицію (3)")

---

### Requirement: Channel Post Event Handling
The platform SHALL receive and normalize `channel_post` and `edited_channel_post` webhook updates from channels where the bot is admin.

#### Scenario: Channel post received
- **WHEN** the bot (or another admin) publishes a post in the channel
- **THEN** the webhook receives a `channel_post` update
- **AND** the normalizer produces a `channel_post_created` event with chat context and message content

#### Scenario: Channel post edited
- **WHEN** a channel post is edited
- **THEN** the webhook receives an `edited_channel_post` update
- **AND** the normalizer produces a `channel_post_edited` event

---

### Requirement: Read-only Channel Configuration
The platform SHALL document and support the Telegram channel configuration pattern where only the bot can post, achieved by adding the bot as a channel administrator with `can_post_messages` permission while keeping other members as subscribers (no posting rights).

#### Scenario: Channel setup documented
- **WHEN** an admin follows the channel setup documentation
- **THEN** they create a Telegram channel, add the bot as admin with `can_post_messages`, and register the channel in the platform admin UI
- **AND** the bot can publish content while subscribers can only read and interact via buttons
