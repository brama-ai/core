## ADDED Requirements

### Requirement: Telegram Delivery Adapter
The platform SHALL include a `TelegramDeliveryAdapter` implementing `ChannelAdapterInterface` that delivers messages to Telegram chats and forum topics via the Telegram Bot API, using the existing `TelegramSender` service.

#### Scenario: Successful delivery to Telegram group
- **WHEN** `DeliveryService` dispatches a delivery to a channel of type `telegram` with address `chat_id` and channel metadata containing `bot_id`
- **THEN** the adapter SHALL resolve the bot via `TelegramBotRegistry`
- **AND** call `TelegramSender::send()` with the resolved `bot_id`, `chat_id`, payload body, and mapped parse mode
- **AND** return a `DeliveryResult` with `status: "delivered"` and Telegram's `message_id` as `externalMessageId`

#### Scenario: Delivery to forum topic via thread_id
- **WHEN** the delivery target address uses format `chat_id:thread_id` (colon-separated)
- **THEN** the adapter SHALL parse the address into `chat_id` and `thread_id`
- **AND** pass `thread_id` in the `TelegramSender` options as `message_thread_id`

#### Scenario: Delivery to group without thread
- **WHEN** the delivery target address contains only `chat_id` (no colon separator)
- **THEN** the adapter SHALL send the message to the group-level chat without thread targeting

#### Scenario: Content type mapped to parse mode
- **WHEN** the `DeliveryPayload.content_type` is `markdown`
- **THEN** the adapter SHALL set parse mode to `MarkdownV2`
- **WHEN** the `DeliveryPayload.content_type` is `text`
- **THEN** the adapter SHALL send with no parse mode (plain text)
- **WHEN** the `DeliveryPayload.content_type` is `card`
- **THEN** the adapter SHALL set parse mode to `HTML`

#### Scenario: Bot not found in registry
- **WHEN** the channel metadata `bot_id` does not match any registered bot in `TelegramBotRegistry`
- **THEN** the adapter SHALL return a `DeliveryResult` with `status: "failed"` and error message indicating the bot was not found

#### Scenario: Missing bot_id in channel config
- **WHEN** the channel metadata does not contain a `bot_id` key
- **THEN** the adapter SHALL return a `DeliveryResult` with `status: "failed"` and error message indicating missing bot configuration

#### Scenario: Telegram API returns error
- **WHEN** the Telegram Bot API returns an error response (e.g., bot removed from chat, chat not found, insufficient permissions)
- **THEN** the adapter SHALL return a `DeliveryResult` with `status: "failed"` and the Telegram error description

#### Scenario: Network failure during send
- **WHEN** the HTTP call to Telegram Bot API throws a network exception
- **THEN** the adapter SHALL catch the exception and return a `DeliveryResult` with `status: "failed"` and the exception message

#### Scenario: Adapter registered as tagged service
- **WHEN** the Symfony service container is compiled
- **THEN** `TelegramDeliveryAdapter` SHALL be registered with tag `delivery.adapter` and attribute `type: telegram`
- **AND** `DeliveryService` SHALL discover it via tagged service iteration
