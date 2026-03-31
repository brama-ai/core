## ADDED Requirements

### Requirement: Telegram Chat List Page

The admin panel SHALL provide a list of tracked Telegram chats at `GET /admin/telegram/chats`.

Each row SHALL display: chat title, chat type (group, supergroup, channel), associated bot username, thread/topic indicator, member count, last message timestamp, and activity counts.

The list SHALL be sorted by `last_message_at DESC` (most recently active first).

#### Scenario: Admin opens chat list page

- **WHEN** an authenticated admin navigates to `/admin/telegram/chats`
- **THEN** the page SHALL display a table of tracked chats from the `telegram_chats` table
- **AND** each row SHALL show title, type badge, bot username, thread indicator, member_count, last_message_at, and activity counts (24h/7d)

#### Scenario: Empty state when no chats tracked

- **WHEN** no chats exist in the `telegram_chats` table
- **THEN** the page SHALL display an empty state message indicating no Telegram chats have been tracked yet

#### Scenario: Unauthenticated access redirects to login

- **WHEN** an unauthenticated user navigates to `/admin/telegram/chats`
- **THEN** the response SHALL redirect to `/admin/login` with HTTP 302

### Requirement: Chat List Thread Indicators

The chat list SHALL display a visual thread/topic indicator for supergroups that have forum mode enabled.

#### Scenario: Supergroup with forum topics enabled

- **WHEN** a chat record has `type = 'supergroup'` and `has_threads = true`
- **THEN** the row SHALL display a "Topics" badge next to the chat type

#### Scenario: Regular group without forum topics

- **WHEN** a chat record has `has_threads = false`
- **THEN** no thread indicator SHALL be displayed

### Requirement: Chat Activity Counts

The chat list SHALL display message activity counts per chat for the last 24 hours and last 7 days.

#### Scenario: Chat with recent activity

- **WHEN** a chat has received messages within the last 24 hours
- **THEN** the row SHALL display the 24h message count and the 7d message count

#### Scenario: Chat with no recent activity

- **WHEN** a chat has not received any messages in the last 7 days
- **THEN** the activity counts SHALL display "0" for both 24h and 7d

### Requirement: Chat List Bot Filter

The chat list SHALL support filtering by bot to show only chats associated with a specific bot.

#### Scenario: Admin filters chats by bot

- **WHEN** an admin selects a bot from the filter dropdown
- **THEN** the list SHALL show only chats associated with the selected bot

#### Scenario: Admin clears bot filter

- **WHEN** an admin selects "All bots" from the filter dropdown
- **THEN** the list SHALL show chats from all bots

### Requirement: Left Chat Visibility

The chat list SHALL distinguish between active chats and chats where the bot has left.

#### Scenario: Bot has left a chat

- **WHEN** a chat record has a non-null `left_at` timestamp
- **THEN** the row SHALL display a "Left" badge and the row SHALL be visually dimmed

#### Scenario: Bot is active in a chat

- **WHEN** a chat record has `left_at IS NULL`
- **THEN** the row SHALL display normally without a "Left" badge
