# Change: Add Admin Chats Section

## Why
The admin panel has a "Chats" stub page (`/admin/chats`) that shows "В розробці". Platform operators need a way to inspect conversations that flow through the A2A gateway — see which agents were involved, what messages were exchanged, and drill into trace details for debugging.

## What Changes
- Replace the stub Chats page with a functional chat list view
- A "chat" = an OpenClaw session (identified by `session_key`), started when user types `/new`
- Chat list shows sessions grouped by `session_key`, with channel, sender, message count, trace IDs, and timestamps
- Clicking a trace ID navigates to the existing trace visualization (`/admin/logs/trace/{traceId}`)
- Clicking a chat row opens a conversation detail view showing message bubbles: user messages, bot responses, and tool call events
- Promote `session_key`, `sender`, `recipient` to top-level indexed fields in OpenSearch for efficient querying
- Extract `LogSearchInterface` from `LogIndexManager` for testability
- Add `ChatRepository` that queries OpenSearch for session-based data
- Add filtering by channel and sender

## Impact
- Affected specs: new `admin-chats` capability
- Affected code: `apps/core/src/Controller/Admin/ChatsController.php`, `apps/core/templates/admin/chats.html.twig`, new `ChatRepository`, new `chat_detail.html.twig`, `docker/openclaw/plugins/platform-tools/index.js`, `LogIndexManager`
- No database schema changes — all data from OpenSearch
