## 1. Infrastructure — OpenSearch session_key support
- [x] 1.1 Promote `session_key`, `sender`, `recipient`, `channel` to top-level indexed fields in OpenClaw plugin (`platform-tools/index.js`)
- [x] 1.2 Add `session_key`, `sender`, `recipient` to OpenSearch index mapping template (`LogIndexManager`)
- [x] 1.3 Extract `LogSearchInterface` from `LogIndexManager` for testability
- [x] 1.4 Register `LogSearchInterface` alias in `services.yaml`

## 2. Backend — ChatRepository & DTOs
- [x] 2.1 Create `App\Chat\ChatRepository` that queries OpenSearch with pagination, filtering (channel, sender), and grouping by `session_key`
- [x] 2.2 Create `App\Chat\DTO\ChatListItem` value object (sessionKey, channel, sender, messageCount, timestamps, traceIds)
- [x] 2.3 Create `App\Chat\DTO\ChatMessage` value object (direction, timestamp, eventName, traceId, sender, recipient, tool, status, durationMs, payload)
- [x] 2.4 Add method to retrieve chat messages for a session with payload enrichment from OpenSearch context
- [x] 2.5 Add method to retrieve trace IDs associated with a session

## 3. Backend — Controllers
- [x] 3.1 Update `ChatsController` to fetch paginated chat list from `ChatRepository`, pass filters from query params
- [x] 3.2 Add `ChatDetailController` at `/admin/chats/{sessionKey}` that fetches messages and trace IDs

## 4. Frontend — Chat List Page
- [x] 4.1 Replace stub `chats.html.twig` with a filterable table: session key, channel, sender, message count, trace IDs, timestamps
- [x] 4.2 Add filter bar: channel input, sender input
- [x] 4.3 Add pagination controls
- [x] 4.4 Trace ID column links to `/admin/logs/trace/{traceId}` (existing trace view)
- [x] 4.5 Chat row click navigates to `/admin/chats/{sessionKey}` (detail view)

## 5. Frontend — Chat Detail Page
- [x] 5.1 Create `chat_detail.html.twig` showing conversation flow as message bubbles
- [x] 5.2 Show inbound messages (user), outbound messages (bot), and tool call events
- [x] 5.3 Show input/output payloads in formatted JSON blocks
- [x] 5.4 Add "View Trace" buttons linking to `/admin/logs/trace/{traceId}`
- [x] 5.5 Handle graceful fallback when no messages found

## 6. Styling
- [x] 6.1 Add CSS for chat list table, filter bar (reuse existing `admin.css` patterns)
- [x] 6.2 Add chat message bubbles / conversation-style layout for the detail view
- [x] 6.3 Add tool call event styling with color-coded icons

## 7. Tests
- [x] 7.1 Unit tests for `ChatRepository` (list, count, messages, trace IDs, pagination, unavailable fallback)
- [x] 7.2 Unit tests for `ChatListItem` DTO
- [x] 7.3 Unit tests for `ChatMessage` DTO

## 8. Quality checks
- [x] 8.1 PHPStan level 8 passes (0 errors)
- [x] 8.2 PHP CS Fixer passes (0 violations)
- [x] 8.3 Codeception unit + functional suites pass (183 tests, 595 assertions)
