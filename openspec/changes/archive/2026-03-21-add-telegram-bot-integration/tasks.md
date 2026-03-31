# Tasks: add-telegram-bot-integration

## 0. Foundation & Data Model
- [x] 0.1 Create Doctrine migration for `telegram_bots` table (id, bot_username, bot_token_encrypted, webhook_secret, community_id, privacy_mode, polling_mode, role_overrides, config, enabled, last_update_id, webhook_url, created_at, updated_at)
- [x] 0.2 Create Doctrine migration for `telegram_chats` table (id, bot_id, title, type, has_threads, member_count, joined_at, left_at, metadata, last_message_at)
- [ ] 0.2a Create Doctrine migration for `telegram_drafts` table (id, bot_id, author_user_id, target_chat_id, target_thread_id, flow_type, content_text, content_media, content_markup, status, moderator_user_id, moderator_chat_message_id, rejection_reason, published_message_id, created_at, moderated_at, published_at)
- [x] 0.3 Create `TelegramBotRepository` (DBAL-based CRUD for `telegram_bots`, token encryption/decryption via platform secret key)
- [x] 0.4 Create `TelegramChatRepository` (DBAL-based CRUD for `telegram_chats`, upsert on chat metadata changes)
- [x] 0.5 Add `TELEGRAM_ENCRYPTION_KEY` env variable to `.env` and `docker-compose` configs
- [x] 0.6 Create `TelegramBotRegistry` service — loads bot configs from DB, caches in memory, provides `getBot(botId)`, `getEnabledBots()`

## 1. Telegram Webhook Receiver (Inbound)
- [x] 1.1 Create `TelegramWebhookController` at `POST /api/v1/webhook/telegram/{bot_id}` — receives raw Telegram Update JSON
- [x] 1.2 Implement webhook secret verification (`X-Telegram-Bot-Api-Secret-Token` header validation against `telegram_bots.webhook_secret`)
- [x] 1.3 Implement `update_id` deduplication — track per bot in DB, skip already-processed updates
- [x] 1.4 Implement `update_id` gap detection — log warning if incoming update_id is not sequential
- [x] 1.5 Return `200 OK` immediately, process sync in MVP
- [ ] 1.6 Add rate limiting per `chat_id` on inbound webhook (prevent flood attacks)
- [ ] 1.7 Add Traefik route for webhook endpoint (public access, no admin auth)

## 2. Telegram Update Normalizer
- [x] 2.1 Create `TelegramUpdateNormalizer` service — transforms raw Telegram Update into platform `NormalizedEvent` DTO
- [x] 2.2 Handle `message` updates → `message_created` event (text, caption, forwarded, reply)
- [x] 2.3 Handle `edited_message` updates → `message_edited` event
- [x] 2.4 Handle `message` with `left_chat_member` / `new_chat_members` → `member_left` / `member_joined` events
- [x] 2.5 Handle `message` with bot command entities → `command_received` event (parse command name + arguments)
- [x] 2.6 Handle `callback_query` updates → `callback_query` event (for inline keyboard interactions)
- [x] 2.7 Extract thread context: `message_thread_id`, `is_topic_message`, `forum_topic_created`
- [x] 2.8 Extract reply context: `reply_to_message` chain (one level deep)
- [x] 2.9 Extract media metadata: `photo`, `document`, `video`, `voice`, `sticker` → `has_media: true`, `media_type: "photo"` (no file download)
- [x] 2.10 Create `NormalizedEvent` DTO with fields: `event_type`, `platform`, `bot_id`, `chat`, `sender`, `message`, `trace_id`, `request_id`, `raw_update_id`
- [x] 2.11 Create `NormalizedChat` DTO: `id`, `title`, `type`, `thread_id`
- [x] 2.12 Create `NormalizedSender` DTO: `id`, `username`, `first_name`, `role`, `is_bot`
- [x] 2.13 Create `NormalizedMessage` DTO: `id`, `text`, `reply_to_message_id`, `has_media`, `media_type`, `forward_from`, `timestamp`

## 3. Telegram Chat Tracker
- [x] 3.1 Create `TelegramChatTracker` service — updates `telegram_chats` table on every incoming update
- [x] 3.2 On first message from new chat: insert chat record with type, title, thread support detection
- [x] 3.3 On `member_joined` where new member is the bot: record `joined_at` timestamp
- [x] 3.4 On `member_left` where left member is the bot: record `left_at` timestamp (soft delete, stop processing)
- [x] 3.5 On chat title change: update `title` field
- [ ] 3.6 Periodic `member_count` refresh via `getChatMemberCount` API (scheduled task, not per-message)
- [x] 3.7 Track `last_message_at` for activity monitoring

## 4. Event Bus Integration
- [x] 4.1 Create `TelegramEventPublisher` service — bridges `NormalizedEvent` to `EventBus::dispatch()`
- [x] 4.2 Modify `EventBus::dispatch()` — replace placeholder comment with actual A2A HTTP calls to agent endpoints
- [x] 4.3 Add trace context generation: `trace_id` and `request_id` for every dispatched event
- [ ] 4.4 Add async dispatch support — enqueue event to `agent_invoke` transport for parallel agent processing
- [x] 4.5 Handle A2A call failures gracefully — log error, continue dispatching to remaining agents
- [x] 4.6 Add event dispatch metrics — log event type, number of agents notified, total dispatch duration
- [x] 4.7 Update existing EventBus unit tests for new constructor signature
- [x] 4.8 Write unit tests for TelegramEventPublisher

## 5. Chat Commands Router
- [x] 5.1 Create `TelegramCommandRouter` service — receives `command_received` events, routes to handlers
- [x] 5.2 Create `TelegramRoleResolver` — maps Telegram chat member status to platform role (`creator` → `admin`, `administrator` → `moderator`, `member` → `user`), with override lookup from `telegram_bots.role_overrides`
- [x] 5.3 Implement `/help` command handler — returns list of available commands with descriptions
- [x] 5.4 Implement `/agents` command handler — queries `AgentRegistry::findAll()`, formats agent list with enabled/disabled status
- [x] 5.5 Implement `/agent enable <name>` command handler — validates admin/moderator role, enables agent in registry, confirms in chat
- [x] 5.6 Implement `/agent disable <name>` command handler — validates admin/moderator role, disables agent in registry, confirms in chat
- [x] 5.7 Route agent-declared commands — check `manifest.commands[]` for matching command, forward to agent via A2A with command context
- [x] 5.8 Handle unknown commands — reply with "Unknown command. Use /help to see available commands."
- [x] 5.9 Handle unauthorized commands — reply with "You don't have permission to use this command."
- [x] 5.10 Write unit tests for CommandRouter (role validation, routing logic)
- [x] 5.11 Write unit tests for each built-in command handler

## 6. Outbound Messaging (Telegram Sender)
- [x] 6.1 Create `TelegramApiClient` — thin HTTP client wrapper for Telegram Bot API calls (sendMessage, editMessageText, deleteMessage, getChatMember, getChatMemberCount, sendPhoto, sendMediaGroup, copyMessage, answerCallbackQuery, pinChatMessage, setWebhook, deleteWebhook, getWebhookInfo, getUpdates)
- [x] 6.2 Create `TelegramSender` service — high-level send interface: `send(botId, chatId, text, options)` where options include `thread_id`, `reply_to_message_id`, `parse_mode`, `reply_markup`
- [ ] 6.3 Implement MarkdownV2 formatting with proper character escaping (Telegram's MarkdownV2 has strict escaping rules)
- [x] 6.4 Implement HTML parse mode as fallback when MarkdownV2 fails
- [x] 6.5 Implement message splitting for text >4096 characters (split at paragraph/sentence boundaries, send as multiple messages)
- [ ] 6.6 Implement rate limiting — token-bucket per chat_id (30 msg/sec global, 20 msg/min per group) with queue backpressure
- [x] 6.7 TelegramSender builds reply payloads with `reply_to_message_id`, inline keyboard buttons, thread targeting
- [x] 6.8 Write unit tests for TelegramSender (formatting, splitting, rate limiting)

## 7. Delivery Channel Adapter
- [x] 7.1 Create `TelegramDeliveryAdapter` implementing `ChannelAdapterInterface` — maps `DeliveryPayload` to `TelegramSender::send()` call
- [x] 7.2 Map `DeliveryTarget.address` to Telegram `chat_id` + optional `thread_id` (format: `chat_id` or `chat_id:thread_id`)
- [x] 7.3 Map `DeliveryPayload.content_type` to Telegram parse mode (`markdown` → MarkdownV2, `text` → plain, `card` → HTML with formatting)
- [x] 7.4 Return `DeliveryResult` with Telegram's `message_id` as `external_message_id`
- [x] 7.5 Register adapter in `services.yaml` with tag `delivery.adapter` and type `telegram`
- [x] 7.6 Write unit tests for TelegramDeliveryAdapter

## 8. OpenClaw Bridge Enhancement
- [ ] 8.1 Modify `platform-tools` plugin — pass Telegram context (`chat_id`, `thread_id`, `sender`, `message_id`) through tool invocation payload
- [ ] 8.2 Update frontdesk `AGENTS.md` — add event routing rules for passive `message_created` processing (call `knowledge.store_message` for every group message)
- [ ] 8.3 Update frontdesk `TOOLS.md` — document Telegram context fields in invoke payload
- [ ] 8.4 Update frontdesk `SOUL.md` — add rule for thread-aware replies (always reply in same thread as user message)
- [ ] 8.5 Ensure OpenClaw's Telegram channel config does not conflict with Core's webhook — OpenClaw uses polling or separate webhook path

## 9. Local Development (Polling Mode)
- [x] 9.1 Create `app:telegram:poll` CLI command — long-polling via `getUpdates` API, feeds into same `TelegramUpdateNormalizer` pipeline
- [x] 9.2 Implement graceful shutdown on SIGINT/SIGTERM
- [x] 9.3 Implement configurable polling interval (default 1 second)
- [ ] 9.4 Document local dev setup: create bot via BotFather, disable privacy mode, add token to `.env`, run `app:telegram:poll`

## 10. Webhook Management
- [x] 10.1 Create `app:telegram:set-webhook` CLI command — calls Telegram `setWebhook` API with URL, secret_token, allowed_updates, max_connections
- [x] 10.2 Create `app:telegram:delete-webhook` CLI command — calls `deleteWebhook` API
- [x] 10.3 Create `app:telegram:webhook-info` CLI command — calls `getWebhookInfo` API, displays status
- [ ] 10.4 Auto-register webhook on bot creation via admin UI (optional, with confirmation)

## 11. Admin UI — Bot Management
- [x] 11.1 Create `/admin/telegram/bots` page — list all configured bots with status (enabled, webhook active, last update received)
- [x] 11.2 Create bot add form — fields: bot_id, bot_username, bot_token, community assignment
- [x] 11.3 Create bot edit form — update config, privacy mode, role overrides
- [x] 11.4 Add "Test Connection" button — calls `getMe` API, shows bot info and connection status
- [x] 11.5 Add "Set Webhook" button — triggers `setWebhook` with auto-generated URL
- [x] 11.6 Add webhook status indicator — shows last `getWebhookInfo` result (pending_update_count, last_error)
- [x] 11.7 Add "Delete Bot" with confirmation dialog

## 12. Admin UI — Chat Monitoring
- [x] 12.1 Create `/admin/telegram/chats` page — list tracked chats with title, type, member count, last message time
- [x] 12.2 Show thread/topic indicators for supergroups with forum mode
- [x] 12.3 Add message activity sparkline or count per chat (last 24h/7d)
- [ ] 12.4 Add link to chat detail — show recent events, active agents, command usage stats

## 13. Admin UI — Dashboard Integration
- [x] 13.1 Add Telegram status widget to admin dashboard — connection health, total bots, total chats, messages today
- [x] 13.2 Add "Telegram Events" link to admin sidebar navigation
- [ ] 13.3 Modify `/admin/agents` page — show which Telegram events each agent subscribes to

## 14. Security
- [ ] 14.1 Implement bot token encryption/decryption using `TELEGRAM_ENCRYPTION_KEY` (AES-256-GCM)
- [ ] 14.2 Implement webhook secret generation on bot creation (random 64-char hex)
- [ ] 14.3 Implement inbound rate limiter per `(bot_id, chat_id)` — configurable max updates/minute (default 120)
- [ ] 14.4 Ensure bot tokens never appear in logs, error messages, or admin UI (masked display)
- [ ] 14.5 Add audit logging for all admin bot management actions (create, edit, delete, enable, disable)

## 15. Observability
- [ ] 15.1 Log all inbound Telegram updates to OpenSearch with `channel: "telegram"`, `event_type`, `bot_id`, `chat_id`, `trace_id`
- [ ] 15.2 Log all outbound Telegram messages to OpenSearch with delivery status, duration_ms, message_id
- [ ] 15.3 Log command executions with command name, sender, role, result
- [ ] 15.4 Add Langfuse traces for Event Bus dispatch chains (Telegram event → agent invocations → responses)
- [ ] 15.5 Add health check for Telegram webhook status in `/health` endpoint (optional component)

## 16. Channel Posting & Read-only Channels
- [ ] 16.1 Create `TelegramChannelManager` service — manages channels where bot is admin, tracks channel metadata in `telegram_chats` with `type: "channel"`
- [ ] 16.2 Handle `channel_post` webhook updates — normalize as `channel_post_created` event
- [ ] 16.3 Handle `edited_channel_post` webhook updates — normalize as `channel_post_edited` event
- [ ] 16.4 Implement `sendPhoto` in `TelegramApiClient` — single photo with caption (up to 1024 chars)
- [ ] 16.5 Implement `sendMediaGroup` in `TelegramApiClient` — album of 2-10 `InputMediaPhoto`/`InputMediaDocument` items
- [ ] 16.6 Implement `copyMessage` in `TelegramApiClient` — forward message without "forwarded from" label
- [ ] 16.7 Create pinned welcome message with `InlineKeyboardMarkup` for channel entry-point buttons (e.g., "Розмістити оголошення", "Запропонувати тему")
- [ ] 16.8 Support `editMessageReplyMarkup` — update button counters on existing posts (e.g., proposal count)
- [ ] 16.9 Auto-split long captions: if caption >1024 chars, send photo with short caption + follow-up text message
- [ ] 16.10 Write unit tests for channel post normalization and media sending

## 17. Callback Routing & Interactive Buttons
- [ ] 17.1 Create `TelegramCallbackRouter` service — routes `callback_query` events by pattern matching on `callback_data` (format: `action:param1:param2`)
- [ ] 17.2 Register built-in callback handlers: `flow:*` (conversation flow triggers), `moderate:*` (moderation approve/reject), `navigate:*` (pagination)
- [ ] 17.3 Implement `answerCallbackQuery` — acknowledge button press with optional `text`, `show_alert`, and `url`
- [ ] 17.4 Implement callback → DM redirect: when callback source is channel/group, bot sends DM to user to start interactive flow
- [ ] 17.5 Handle "user hasn't started DM" case — fallback to button with `url: "t.me/bot?start=payload"` deep-link
- [ ] 17.6 Implement inline keyboard builder DSL — fluent API for constructing `InlineKeyboardMarkup` with rows and buttons
- [ ] 17.7 Support `callback_data` with payload encoding (max 64 bytes) — encode flow_type + target IDs within limit
- [ ] 17.8 Write unit tests for callback routing and pattern matching

## 18. Conversational DM Flows
- [ ] 18.1 Create `TelegramConversationManager` service — state machine for multi-step DM conversations, states: `idle → collecting_text → collecting_media → preview → confirm → submitted → published`
- [ ] 18.2 Implement conversation state persistence in Redis — hash key `conv:{bot_id}:{user_id}:{flow_type}`, TTL 30 min (configurable)
- [ ] 18.3 Create `ConversationFlow` interface — contract for flow implementations: `getSteps()`, `handleStep(state, message)`, `buildPreview(state)`, `buildDraft(state)`
- [ ] 18.4 Implement `CreateAnnouncementFlow` — steps: collect text → collect photo (optional, /skip) → preview → confirm
- [ ] 18.5 Implement `SubmitProposalFlow` — steps: collect text → preview → confirm (links to parent post)
- [ ] 18.6 Implement deep-link `/start` parameter parsing — extract `flow_type` and `target` from start payload, initialize conversation
- [ ] 18.7 Handle `/cancel` command in active conversation — clear state, confirm cancellation
- [ ] 18.8 Handle conversation timeout — TTL expiry sends "Сесія завершена, почніть заново" on next user message
- [ ] 18.9 Per-user rate limiting — max 3 concurrent active flows per user, reject with helpful message
- [ ] 18.10 Write unit tests for ConversationManager state transitions
- [ ] 18.11 Write unit tests for each ConversationFlow implementation

## 19. Moderation Pipeline
- [ ] 19.1 Create `TelegramModerationService` — receives completed drafts, sends to moderation chat with preview + action buttons
- [ ] 19.2 Create `TelegramDraftRepository` — DBAL-based CRUD for `telegram_drafts` table
- [ ] 19.3 Implement moderation chat message — format draft as preview with author info + [✅ Опублікувати] [❌ Відхилити] [✏️ Повернути на редагування] buttons
- [ ] 19.4 Implement "Approve" callback handler — publish draft to target channel/chat/thread, update draft status, notify author
- [ ] 19.5 Implement "Reject" callback handler — prompt moderator for optional reason, notify author with reason, update draft status
- [ ] 19.6 Implement "Edit" callback handler — send content back to author DM for revision, re-enter conversation flow at text step
- [ ] 19.7 Implement auto-expiry — scheduled job rejects drafts older than configurable TTL (default 48h), notifies author
- [ ] 19.8 Clean up moderation chat buttons after action — `editMessageReplyMarkup` to remove/replace buttons with action taken
- [ ] 19.9 Configure moderation chat per bot — `telegram_bots.config` JSONB field `moderation_chat_id`
- [ ] 19.10 Write unit tests for moderation service (approve, reject, edit, expire flows)

## 20. Mini App Support (optional)
- [ ] 20.1 Create `TelegramMiniAppController` at `GET /telegram/mini-app/{app_id}` — serves HTML forms for Telegram Mini App
- [ ] 20.2 Create base Mini App HTML template with Telegram WebApp JS SDK integration (`telegram-web-app.js`)
- [ ] 20.3 Create "create announcement" Mini App form — text input, photo upload, category select, preview, submit via `WebApp.sendData()`
- [ ] 20.4 Handle `web_app_data` message type in normalizer — extract JSON payload, produce `mini_app_submitted` event
- [ ] 20.5 Route Mini App submissions into same moderation pipeline as DM flows
- [ ] 20.6 Add `WebAppInfo` button type to inline keyboard builder — opens Mini App URL in Telegram
- [ ] 20.7 Document HTTPS setup for Mini Apps (Traefik TLS config, ngrok for local dev)
- [ ] 20.8 Write functional tests for Mini App controller and data submission

## 21. Testing
- [ ] 21.1 Unit tests for `TelegramUpdateNormalizer` — cover all Update types (message, edited_message, channel_post, member events, commands, callbacks, forum topics, web_app_data)
- [ ] 21.2 Unit tests for `TelegramBotRegistry` — config loading, caching, multi-bot
- [ ] 21.3 Unit tests for `TelegramChatTracker` — upsert logic, soft delete on bot removal
- [ ] 21.4 Unit tests for `TelegramApiClient` — request formatting, error handling, rate limit detection
- [ ] 21.5 Functional tests for `TelegramWebhookController` — valid/invalid webhook secret, duplicate update_id, unknown bot_id
- [ ] 21.6 Functional tests for command handlers — role validation, agent enable/disable flow
- [ ] 21.7 Functional tests for `TelegramSender` — message formatting, splitting, media sending, error handling
- [ ] 21.8 Unit tests for `TelegramCallbackRouter` — pattern matching, unknown callbacks, answerCallbackQuery
- [ ] 21.9 Unit tests for `TelegramConversationManager` — state transitions, timeout, cancellation, concurrent flow limit
- [ ] 21.10 Unit tests for `TelegramModerationService` — approve/reject/edit/expire flows
- [ ] 21.11 Unit tests for `TelegramDraftRepository` — CRUD, status transitions
- [ ] 21.12 Integration test: Telegram update → normalize → Event Bus → agent A2A mock → verify dispatch
- [ ] 21.13 Integration test: callback_query → DM flow → moderation → publish cycle
- [ ] 21.14 E2E test: send simulated Telegram update to webhook → verify agent receives A2A call (requires running stack)
- [ ] 21.15 E2E test: channel button press → DM flow → moderation approve → channel post appears

## 22. Documentation
- [ ] 22.1 Create `docs/agents/en/telegram-integration.md` — architecture overview, setup guide, configuration reference
- [ ] 22.2 Create `docs/agents/ua/telegram-integration.md` — Ukrainian mirror
- [ ] 22.3 Update `docs/templates/openclaw/frontdesk/README.md` — add section on Telegram + Core dual-path architecture
- [ ] 22.4 Update platform README — add Telegram setup section to quickstart
- [ ] 22.5 Document BotFather setup: create bot, disable privacy mode, get token, set commands
- [ ] 22.6 Document local dev workflow: polling mode, testing with real Telegram
- [ ] 22.7 Document channel setup: create channel, add bot as admin, configure entry-point buttons
- [ ] 22.8 Document DM flow creation: how to add new ConversationFlow implementations
- [ ] 22.9 Document moderation pipeline: setup moderation chat, configure per-flow moderation rules

## 23. Quality
- [ ] 23.1 Run `phpstan analyse` — zero errors at level 8
- [ ] 23.2 Run `php-cs-fixer check` — no violations
- [ ] 23.3 Run `codecept run` — all suites pass
- [ ] 23.4 Run E2E tests with Telegram webhook simulation
- [ ] 23.5 Manual smoke test: send message in real Telegram group → verify agent receives event
- [ ] 23.6 Manual smoke test: press button in channel → complete DM flow → moderator approves → post published
