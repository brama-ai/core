# Change: Build Telegram Admin UI for bot management, chat monitoring, and dashboard integration

## Why

The Telegram bot integration backend is implemented (webhook receiver, normalizer, chat tracker, command router, sender, bot registry) but has no admin UI. Operators cannot manage bots, monitor chats, or check Telegram health without CLI commands or direct database access. The admin panel already has patterns for CRUD management (Tenants), list views (Chats, Agents), and dashboard widgets — the Telegram integration needs equivalent UI coverage to be operationally viable.

Reference: `openspec/changes/archive/2026-03-21-add-telegram-bot-integration/tasks.md` sections 11, 12, 13.

## What Changes

### 1. Bot Management Page (`/admin/telegram/bots`)
- **New admin page** listing all configured Telegram bots with status indicators (enabled/disabled, webhook active, last update time)
- **Add bot form** — fields: bot username, bot token, community assignment
- **Edit bot form** — update config, privacy mode, role overrides, enabled state
- **"Test Connection" button** — calls Telegram `getMe` API, displays bot info and connection status
- **"Set Webhook" button** — triggers `setWebhook` with auto-generated URL and confirmation
- **Webhook status indicator** — displays `getWebhookInfo` result (pending updates, last error, URL)
- **"Delete Bot" button** — with confirmation dialog, cascades to remove associated chats

### 2. Chat Monitoring Page (`/admin/telegram/chats`)
- **New admin page** listing tracked Telegram chats per bot with title, type, member count, last message time
- **Thread/topic indicators** — visual badge for supergroups with forum mode enabled
- **Activity counts** — message activity per chat (last 24h/7d)
- **Filter by bot** — dropdown to filter chats by bot

### 3. Dashboard Integration
- **New Telegram status widget** on admin dashboard — connection health, total bots, total active chats, messages today
- **Sidebar navigation link** — "Telegram" entry in the main navigation section (before "Інструменти")

### 4. Sidebar Navigation Update
- **Modified** admin sidebar — add "Telegram" link between Settings and the "Інструменти" section divider

## Impact

- Affected specs: new capabilities `telegram-admin-bots`, `telegram-admin-chats`; modifies `admin-tools-navigation` (new sidebar link)
- Affected code:
  - `src/Controller/Admin/TelegramBotsController.php` (new)
  - `src/Controller/Admin/TelegramChatsController.php` (new)
  - `src/Controller/Admin/DashboardController.php` (modified — inject Telegram metrics)
  - `templates/admin/telegram/bots.html.twig` (new)
  - `templates/admin/telegram/bot_create.html.twig` (new)
  - `templates/admin/telegram/bot_edit.html.twig` (new)
  - `templates/admin/telegram/chats.html.twig` (new)
  - `templates/admin/layout.html.twig` (modified — sidebar link)
  - `templates/admin/dashboard.html.twig` (modified — Telegram widget)
  - `translations/messages.uk.yaml` (new keys)
  - `translations/messages.en.yaml` (new keys)
- Dependencies: existing `TelegramBotRegistry`, `TelegramBotRepository`, `TelegramChatRepository`, `TelegramApiClient` services
- No breaking changes
- No database migrations required (tables already exist)
