## ADDED Requirements

### Requirement: Telegram Bot List Page

The admin panel SHALL provide a paginated list of all configured Telegram bots at `GET /admin/telegram/bots`.

Each row SHALL display: bot username, community assignment, enabled/disabled badge, webhook status indicator, last update received timestamp, and action buttons.

The list SHALL include both enabled and disabled bots, sorted by `created_at DESC`.

#### Scenario: Admin opens bot list page

- **WHEN** an authenticated admin navigates to `/admin/telegram/bots`
- **THEN** the page SHALL display a table of all configured bots from the `telegram_bots` table
- **AND** each row SHALL show bot_username, community_id, enabled badge, webhook status, last_update_id timestamp, and action links (Edit, Test, Webhook, Delete)

#### Scenario: Empty state when no bots configured

- **WHEN** no bots exist in the `telegram_bots` table
- **THEN** the page SHALL display an empty state message with a link to add a new bot

#### Scenario: Unauthenticated access redirects to login

- **WHEN** an unauthenticated user navigates to `/admin/telegram/bots`
- **THEN** the response SHALL redirect to `/admin/login` with HTTP 302

### Requirement: Add Telegram Bot

The admin panel SHALL provide a form at `GET /admin/telegram/bots/create` to register a new Telegram bot.

The form SHALL accept: bot username (required), bot token (required), and community ID (optional).

On successful creation, the system SHALL generate a webhook secret automatically and redirect to the bot list.

#### Scenario: Admin creates a new bot with valid data

- **WHEN** an admin submits the create form with bot_username `test_bot` and a valid bot_token
- **THEN** the system SHALL create a new record in `telegram_bots` with the provided data
- **AND** SHALL generate a random webhook_secret
- **AND** SHALL redirect to `/admin/telegram/bots` with a success flash message

#### Scenario: Admin submits create form with missing required fields

- **WHEN** an admin submits the create form with an empty bot_username or bot_token
- **THEN** the page SHALL re-render the form with an error message indicating the missing field

#### Scenario: Admin submits create form with duplicate bot username

- **WHEN** an admin submits the create form with a bot_username that already exists
- **THEN** the page SHALL re-render the form with an error message indicating the username is taken

### Requirement: Edit Telegram Bot

The admin panel SHALL provide a form at `GET /admin/telegram/bots/{id}/edit` to update an existing bot's configuration.

The form SHALL allow editing: bot username, privacy mode, enabled state, role overrides, and config.

The bot token SHALL NOT be displayed or editable in the edit form for security.

#### Scenario: Admin edits bot configuration

- **WHEN** an admin submits the edit form with updated privacy_mode and enabled state
- **THEN** the system SHALL update the bot record in `telegram_bots`
- **AND** SHALL redirect to `/admin/telegram/bots` with a success flash message

#### Scenario: Admin opens edit form for non-existent bot

- **WHEN** an admin navigates to `/admin/telegram/bots/{id}/edit` with an invalid bot ID
- **THEN** the response SHALL return HTTP 404

### Requirement: Test Bot Connection

The admin panel SHALL provide a "Test Connection" action at `POST /admin/telegram/bots/{id}/test` that verifies the bot token is valid by calling the Telegram `getMe` API.

#### Scenario: Successful connection test

- **WHEN** an admin triggers "Test Connection" for a bot with a valid token
- **THEN** the system SHALL call `TelegramApiClient::getMe()` with the bot's decrypted token
- **AND** SHALL redirect to `/admin/telegram/bots` with a success flash message containing the bot's display name and username from Telegram

#### Scenario: Failed connection test

- **WHEN** an admin triggers "Test Connection" for a bot with an invalid or revoked token
- **THEN** the system SHALL redirect to `/admin/telegram/bots` with an error flash message describing the connection failure

### Requirement: Set Webhook from Admin

The admin panel SHALL provide a "Set Webhook" action at `POST /admin/telegram/bots/{id}/set-webhook` that registers the webhook URL with Telegram.

The webhook URL SHALL be auto-generated from the current request host and the known webhook route pattern.

#### Scenario: Successful webhook registration

- **WHEN** an admin triggers "Set Webhook" for a bot
- **THEN** the system SHALL call `TelegramApiClient::setWebhook()` with the auto-generated URL and the bot's webhook_secret
- **AND** SHALL update the bot's `webhook_url` field in the database
- **AND** SHALL redirect to `/admin/telegram/bots` with a success flash message

#### Scenario: Failed webhook registration

- **WHEN** the Telegram API rejects the webhook URL (e.g., not HTTPS, unreachable)
- **THEN** the system SHALL redirect to `/admin/telegram/bots` with an error flash message describing the failure

### Requirement: Webhook Status Indicator

The bot list page SHALL display a webhook status indicator for each bot showing the current webhook health.

The indicator SHALL show: webhook URL (if set), pending update count, and last error (if any).

#### Scenario: Bot with healthy webhook

- **WHEN** a bot has an active webhook with zero pending updates and no errors
- **THEN** the status indicator SHALL display a green health badge and the webhook URL

#### Scenario: Bot with webhook errors

- **WHEN** a bot's webhook has a non-zero `last_error_date` and `last_error_message`
- **THEN** the status indicator SHALL display a red error badge with the error message

#### Scenario: Bot with no webhook configured

- **WHEN** a bot has no `webhook_url` set
- **THEN** the status indicator SHALL display a grey "No webhook" badge

### Requirement: Delete Telegram Bot

The admin panel SHALL provide a "Delete Bot" action at `POST /admin/telegram/bots/{id}/delete` that removes a bot and all its associated data.

The delete action SHALL require confirmation via a browser `confirm()` dialog.

#### Scenario: Admin deletes a bot with confirmation

- **WHEN** an admin clicks "Delete" and confirms the browser dialog
- **THEN** the system SHALL delete the bot record from `telegram_bots` (cascading to `telegram_chats`)
- **AND** SHALL redirect to `/admin/telegram/bots` with a success flash message

#### Scenario: Admin cancels bot deletion

- **WHEN** an admin clicks "Delete" but cancels the browser confirmation dialog
- **THEN** no deletion SHALL occur and the page SHALL remain unchanged

#### Scenario: Admin attempts to delete non-existent bot

- **WHEN** a POST request is sent to `/admin/telegram/bots/{id}/delete` with an invalid bot ID
- **THEN** the response SHALL return HTTP 404
