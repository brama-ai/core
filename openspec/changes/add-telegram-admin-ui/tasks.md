# Tasks: add-telegram-admin-ui

## 1. Repository Enhancements
- [ ] 1.1 Add `findAll(): array` method to `TelegramBotRepository` — returns all bots (enabled and disabled), ordered by `created_at DESC`
- [ ] 1.2 Add `findAllByBot(string $botId): array` method to `TelegramChatRepository` — returns all chats including left ones, ordered by `last_message_at DESC`
- [ ] 1.3 Add `countAll(): array` method to `TelegramBotRepository` — returns `['total' => int, 'enabled' => int]`
- [ ] 1.4 Add `getActivityCounts(string $botId): array` method to `TelegramChatRepository` — returns per-chat message counts for last 24h and 7d
- [ ] 1.5 Add `countActiveChats(): int` method to `TelegramChatRepository` — returns count of chats with `left_at IS NULL`

## 2. Bot Management Controller & Templates
- [ ] 2.1 Create `TelegramBotsController` with `list()` method at `GET /admin/telegram/bots` — renders bot list with status indicators
- [ ] 2.2 Create `templates/admin/telegram/bots.html.twig` — table with columns: username, community, enabled, webhook status, last update, actions
- [ ] 2.3 Add `create()` method at `GET|POST /admin/telegram/bots/create` — add bot form with fields: bot_username, bot_token, community_id
- [ ] 2.4 Create `templates/admin/telegram/bot_create.html.twig` — create form following Tenants create pattern
- [ ] 2.5 Add `edit()` method at `GET|POST /admin/telegram/bots/{id}/edit` — edit bot form with fields: bot_username, privacy_mode, enabled, role_overrides, config
- [ ] 2.6 Create `templates/admin/telegram/bot_edit.html.twig` — edit form following Tenants edit pattern
- [ ] 2.7 Add `delete()` method at `POST /admin/telegram/bots/{id}/delete` — delete bot with confirmation, redirect with flash message
- [ ] 2.8 Add `testConnection()` method at `POST /admin/telegram/bots/{id}/test` — calls `TelegramApiClient::getMe()`, redirects with flash showing bot info or error
- [ ] 2.9 Add `setWebhook()` method at `POST /admin/telegram/bots/{id}/set-webhook` — calls `TelegramApiClient::setWebhook()` with auto-generated URL, redirects with flash
- [ ] 2.10 Add `webhookStatus()` method at `POST /admin/telegram/bots/{id}/webhook-status` — calls `TelegramApiClient::getWebhookInfo()`, stores result in flash for display
- [ ] 2.11 Add webhook status indicator to bot list — show pending_update_count, last_error_date, last_error_message from cached webhook info

## 3. Chat Monitoring Controller & Templates
- [ ] 3.1 Create `TelegramChatsController` with `__invoke()` at `GET /admin/telegram/chats` — renders chat list
- [ ] 3.2 Create `templates/admin/telegram/chats.html.twig` — table with columns: title, type, bot, has_threads badge, member_count, last_message_at, activity (24h/7d)
- [ ] 3.3 Add bot filter dropdown — filter chats by selected bot
- [ ] 3.4 Add thread/topic indicator — visual badge for chats with `has_threads = true`
- [ ] 3.5 Add activity counts display — show message counts for last 24h and 7d per chat

## 4. Dashboard Integration
- [ ] 4.1 Inject `TelegramBotRegistry` and `TelegramChatRepository` into `DashboardController`
- [ ] 4.2 Pass Telegram metrics to dashboard template: total_bots, enabled_bots, total_active_chats, messages_today
- [ ] 4.3 Add Telegram status `glass-card` widget to `dashboard.html.twig` in the metrics grid — show bot count, chat count, webhook health summary

## 5. Sidebar Navigation
- [ ] 5.1 Add "Telegram" link to `layout.html.twig` sidebar between Settings and "Інструменти" section divider
- [ ] 5.2 Use `current_route starts with 'admin_telegram'` for active state detection
- [ ] 5.3 Add Telegram paper-plane SVG icon matching existing sidebar icon style

## 6. Translations
- [ ] 6.1 Add Ukrainian translation keys to `translations/messages.uk.yaml` for all new UI labels (nav, page titles, form labels, buttons, flash messages, dashboard widget)
- [ ] 6.2 Add English translation keys to `translations/messages.en.yaml` for all new UI labels

## 7. Testing
- [ ] 7.1 Write unit tests for new `TelegramBotRepository::findAll()` and `countAll()` methods
- [ ] 7.2 Write unit tests for new `TelegramChatRepository::findAllByBot()` and `getActivityCounts()` methods
- [ ] 7.3 Write functional tests for `TelegramBotsController` — list, create, edit, delete, test connection, set webhook flows
- [ ] 7.4 Write functional tests for `TelegramChatsController` — list with filters, empty state
- [ ] 7.5 Write functional test for dashboard Telegram widget — verify widget renders with bot/chat counts

## 8. Documentation
- [ ] 8.1 Update or create `docs/admin/en/telegram-admin.md` — admin UI usage guide for bot management and chat monitoring
- [ ] 8.2 Create `docs/admin/ua/telegram-admin.md` — Ukrainian mirror

## 9. Quality
- [ ] 9.1 Run `phpstan analyse` — zero errors at level 8
- [ ] 9.2 Run `php-cs-fixer check` — no violations
- [ ] 9.3 Run `codecept run` — all suites pass
