## Context

The Telegram bot integration backend is fully implemented with repositories, services, and API client. The admin panel has established patterns for CRUD (TenantsController), list views (ChatsController, AgentsController), and dashboard widgets (DashboardController with DashboardMetricsService). This change adds the missing admin UI layer on top of existing backend services.

Key stakeholders: platform operators who need to manage bots and monitor Telegram activity without CLI access.

## Goals / Non-Goals

- Goals:
  - Provide full bot lifecycle management (add, edit, test, webhook, delete) via admin UI
  - Provide chat monitoring with activity visibility
  - Surface Telegram health on the admin dashboard
  - Follow existing admin UI patterns exactly (manual HTML forms, no Symfony FormTypes, dark theme CSS)

- Non-Goals:
  - No new database migrations (tables exist)
  - No new API endpoints (admin pages use server-side rendering with direct service calls)
  - No real-time updates (no SSE/WebSocket for Telegram status)
  - No chat detail page (deferred — only list view in this change)
  - No message content display (privacy concern — only metadata and counts)

## Decisions

### Decision: Multi-action controller for bot CRUD (like TenantsController)
Use a single `TelegramBotsController` with named methods (`list`, `create`, `edit`, `delete`, `testConnection`, `setWebhook`, `webhookStatus`) rather than separate single-action controllers. This matches the `TenantsController` pattern for CRUD and keeps related actions together.

- Alternatives considered:
  - Separate controllers per action (e.g., `TelegramBotCreateController`) — rejected because the Tenants CRUD pattern is already established and works well for related operations
  - API-based CRUD with JS frontend (like Agents/Scheduler) — rejected because bot management is a low-frequency admin task that doesn't need real-time interactivity

### Decision: Single-action controller for chat list
Use a single `TelegramChatsController` with `__invoke()` since it's read-only with no CRUD operations. Matches the pattern of `ChatsController` (A2A chats).

### Decision: Test Connection and Set Webhook as POST actions returning redirect
"Test Connection" and "Set Webhook" are POST actions on the bot list/edit page that execute the Telegram API call and redirect back with flash messages showing the result. This avoids JavaScript complexity while providing immediate feedback.

- Alternatives considered:
  - AJAX/fetch calls with inline result display — rejected for simplicity; flash messages are sufficient for these infrequent operations

### Decision: Extend DashboardMetricsService for Telegram stats
Add a `getTelegramMetrics()` method to the existing `DashboardMetricsService` (or inject `TelegramBotRegistry` + `TelegramChatRepository` directly into `DashboardController`). The dashboard template adds a new `glass-card` widget in the metrics grid.

### Decision: Repository enhancements for admin queries
The `TelegramBotRepository` currently only has `findEnabled()` for listing. The admin UI needs `findAll()` to show disabled bots too. Similarly, `TelegramChatRepository` needs a `findAllByBot()` that includes left chats, and activity count queries. These are small additions to existing repositories.

### Decision: Sidebar placement
The "Telegram" link goes between "Settings" and the "Інструменти" section divider. It uses `current_route starts with 'admin_telegram'` for active state detection, matching the pattern used by Agents and Logs.

## Risks / Trade-offs

- **Bot token exposure risk** → Mitigation: bot tokens are never displayed in the UI. The edit form shows a masked placeholder. Token is only accepted on create; to change a token, delete and re-create the bot.
- **Webhook URL auto-generation** → Mitigation: the Set Webhook button generates the URL from the current request host + known route pattern. In local dev (localhost), it warns that webhooks require a public URL.
- **Activity count query performance** → Mitigation: activity counts use simple `COUNT(*)` with date filter on `last_message_at` from `telegram_chats`. For MVP scale (single community, <100 chats), this is negligible.

## Open Questions

- Should the chat list page link to a future chat detail page, or is the list view sufficient for the initial implementation? (Recommendation: list-only for now, add detail page in a follow-up)
