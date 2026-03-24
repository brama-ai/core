# Change: Add full Telegram bot integration with group/channel support, interactive buttons, conversational DM flows, and outbound messaging

## Why

The platform's PRD defines Telegram as the single MVP delivery channel, yet no Telegram integration exists. The entire architecture — Event Bus, Agent Registry, A2A Gateway, OpenClaw frontdesk — is built and waiting, but the critical "first mile" (receiving Telegram events) and "last mile" (sending messages to chats and threads) are missing. Without this, agents cannot observe community conversations, respond to commands, or proactively push content. This is the #1 blocker for MVP launch.

## What Changes

### 1. Telegram Bot Adapter (Core — inbound)
- **New service** `TelegramWebhookController` — receives Telegram Bot API webhook updates at `POST /api/v1/webhook/telegram/{bot_id}`
- **New service** `TelegramUpdateNormalizer` — transforms raw Telegram Update objects into platform-normalized events (`message_created`, `message_edited`, `message_deleted`, `command_received`, `member_joined`, `member_left`, `callback_query`)
- **New service** `TelegramBotRegistry` — stores bot configurations (token, webhook secret, assigned community, persona) in database; supports multiple bots
- **New migration** — `telegram_bots` table for bot registry, `telegram_chats` table for tracked chats/supergroups/threads
- **New CLI command** `app:telegram:set-webhook` — registers webhook URL with Telegram API for a configured bot
- **New CLI command** `app:telegram:poll` — long-polling mode for local development (no public URL needed)

### 2. Telegram Message Reader (passive group observation)
- **New service** `TelegramGroupObserver` — processes all incoming group/supergroup messages passively (bot reads all messages when added to group with appropriate permissions)
- **New service** `TelegramChatTracker` — tracks joined chats, stores chat metadata (title, type, thread support), maintains member count
- **Privacy mode handling** — documentation and configuration for BotFather privacy mode (disabled = bot reads all messages; enabled = bot reads only commands and mentions)
- **Thread/topic support** — normalizes Telegram forum topics (`message_thread_id`) into platform thread model
- **Media handling** — extracts text from captions, forwards, replies; stores media type metadata without downloading files

### 3. Event Bus Integration (dispatch to agents)
- **Modified** `EventBus::dispatch()` — completes the A2A call stub (currently placeholder comment) to actually invoke agent endpoints via `A2AClient`
- **New service** `TelegramEventPublisher` — bridges normalized Telegram events to EventBus with full context (chat_id, thread_id, sender metadata, reply chain)
- **Event types dispatched**: `message_created`, `message_edited`, `message_deleted`, `command_received`, `member_joined`, `member_left`
- **Event payload contract** — standardized envelope: `{platform, bot_id, chat_id, thread_id, sender, message, timestamp, raw_update}`

### 4. Chat Commands Router
- **New service** `TelegramCommandRouter` — intercepts `/command` messages, validates sender role, routes to handler
- **Built-in commands**: `/help`, `/agents`, `/agent enable <name>`, `/agent disable <name>`
- **Agent-delegated commands** — forwards agent-declared commands (from manifest `commands[]`) to agent via A2A
- **Role-based access** — maps Telegram chat roles (creator, administrator, member) to platform roles (admin, moderator, user)
- **Command response formatting** — Telegram MarkdownV2 / HTML formatting with proper escaping

### 5. Channel Posting & Read-only Channels
- **New service** `TelegramChannelManager` — manages channels where the bot is an admin with `can_post_messages` permission; users cannot post, only the bot publishes content
- **Channel post support** — bot sends formatted posts (`sendMessage`, `sendPhoto`, `sendMediaGroup`) to Telegram channels
- **Channel post updates** — receives `channel_post` and `edited_channel_post` webhook events, normalizes as `channel_post_created` / `channel_post_edited`
- **Pinned post with action buttons** — bot pins a message with `InlineKeyboardMarkup` buttons (e.g., "Розмістити оголошення", "Запропонувати тему") that trigger interactive flows
- **Post-level interaction buttons** — each published post can include inline buttons (e.g., "Написати пропозицію", "Відгукнутись", "Поскаржитись") with `callback_data` routing

### 6. Interactive Callback & Conversational DM Flows
- **New service** `TelegramCallbackRouter` — routes `callback_query` events from inline keyboard buttons to registered handlers based on `callback_data` pattern matching
- **New service** `TelegramConversationManager` — manages multi-step conversational flows in DMs (state machine: `idle → collecting_text → collecting_media → preview → confirm → published`)
- **Callback → DM flow** — when a user presses a button in a channel/group, the bot starts a private conversation to collect structured input (text, photos, confirmation)
- **Deep-link start parameters** — supports `t.me/bot?start=action_payload` for cases where the user hasn't started DM with the bot yet
- **Conversation state persistence** — stores active conversation state in Redis with TTL (default 30 min, configurable)
- **Moderation flow** — after user submits content via DM, bot sends draft to moderator/admin group with "Опублікувати" / "Відхилити" / "Редагувати" buttons; on approval, bot posts to the target channel/chat/thread
- **`answerCallbackQuery`** — acknowledges button presses with optional `show_alert` text or toast notification

### 7. Outbound Messaging (bot writes to chats, threads, and channels)
- **New service** `TelegramSender` — sends messages via Telegram Bot API (`sendMessage`, `sendPhoto`, `sendMediaGroup`, `copyMessage`, `editMessageText`, `editMessageReplyMarkup`, `deleteMessage`)
- **New service** `TelegramReplyBuilder` — constructs replies with `reply_to_message_id`, thread awareness, inline keyboards
- **New adapter** `TelegramDeliveryAdapter` implements `ChannelAdapterInterface` — integrates with existing delivery-channels abstraction for scheduled/proactive pushes
- **Media sending** — `sendPhoto` (single image with caption), `sendMediaGroup` (album of photos/documents, up to 10 items), with media uploaded via URL or file_id
- **Thread-aware sending** — sends to specific forum topics via `message_thread_id`
- **Channel-aware sending** — sends to channels where bot is admin, supports scheduled publishing
- **Rate limiting** — respects Telegram Bot API limits (30 msg/sec to different chats, 20 msg/min to same group)
- **Message formatting** — MarkdownV2 with fallback to HTML, auto-splitting for messages >4096 chars

### 8. Mini App Support (optional — for rich forms)
- **New service** `TelegramMiniAppController` — serves Mini App HTML pages at `/telegram/mini-app/{app_id}` for complex forms (announcements, proposals, surveys)
- **WebAppInfo button integration** — inline keyboard buttons can open a Mini App form directly inside Telegram
- **`web_app_data` processing** — when user submits a Mini App form, the bot receives structured JSON data via `message.web_app_data`; normalizer produces `mini_app_submitted` event
- **Mini App templates** — reusable HTML templates for common flows: "create announcement", "submit proposal", "fill survey"
- **HTTPS requirement** — Mini Apps require HTTPS; local dev uses Traefik with self-signed cert or ngrok

### 9. OpenClaw Telegram Bridge Enhancement
- **Modified** OpenClaw frontdesk configuration — bot metadata (chat_id, thread_id, sender context) passed through to tool invocations
- **New event routing in AGENTS.md** — rules for passive message processing (store to knowledge base) vs active command handling
- **Bidirectional context** — OpenClaw receives Telegram context in tool calls, and can specify reply target in tool responses
- **Multi-bot support** — OpenClaw instances per bot persona, each with own frontdesk policy and tool permissions

### 10. Admin UI Extensions
- **New admin page** `/admin/telegram/bots` — manage bot configurations (add/edit/remove bots, set webhook, test connection)
- **New admin page** `/admin/telegram/chats` — view tracked chats, member counts, message activity
- **Modified** `/admin/agents` — show which agents subscribe to which Telegram events
- **Modified** admin dashboard — Telegram connection status widget (webhook health, last update received, message throughput)

### 11. Security & Privacy
- **Webhook verification** — validate Telegram secret token on every incoming update
- **Bot token encryption** — store bot tokens encrypted in database (AES-256-GCM via platform secret key)
- **Rate limiting** — per-chat-id inbound rate limiter to prevent flood attacks
- **PII handling** — sender usernames/IDs stored; message content retention policy configurable per community
- **Audit logging** — all inbound/outbound Telegram interactions logged to OpenSearch with trace context

## Impact

- Affected specs: new capabilities `telegram-adapter`, `telegram-event-bus`, `telegram-chat-commands`, `telegram-message-reader`, `telegram-outbound-messaging`, `telegram-channel-posting`, `telegram-interactive-flows`; modifies `platform-foundation` (new env vars), `admin-tools-navigation` (new sidebar links), `delivery-channels` (new Telegram adapter), `observability-integration` (Telegram trace events)
- Affected code:
  - `apps/brama-core/src/Telegram/` (new namespace — all Telegram services, including Channel, Callback, Conversation, MiniApp sub-namespaces)
  - `apps/brama-core/src/Controller/Api/Webhook/TelegramWebhookController.php` (new)
  - `apps/brama-core/src/Controller/Admin/TelegramBotsController.php` (new)
  - `apps/brama-core/src/Controller/Admin/TelegramChatsController.php` (new)
  - `apps/brama-core/src/EventBus/EventBus.php` (modified — complete A2A dispatch)
  - `apps/brama-core/src/Chat/` (modified — Telegram event types)
  - `apps/brama-core/migrations/` (new — telegram_bots, telegram_chats tables)
  - `apps/brama-core/config/services.yaml` (new service wiring)
  - `apps/brama-core/templates/admin/telegram/` (new)
  - `docker/openclaw/plugins/platform-tools/index.js` (modified — Telegram context passthrough)
  - `docs/templates/openclaw/frontdesk/AGENTS.md` (modified — event routing rules)
  - `compose.yaml` (modified — webhook port exposure, environment variables)
- Dependencies: `add-openclaw-agent-discovery` (for A2A bridge), `add-delivery-channels` (for outbound adapter pattern), `add-admin-agent-registry` (for agent manifest events field)
- No breaking changes to existing APIs
