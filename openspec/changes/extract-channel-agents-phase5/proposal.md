# Change: Extract Channel Agents Phase 5 — Traffic Switch + Cleanup

## Why

Phases 1–4 built the full channel agent architecture: generic `Channel/` namespace (Phase 1), core routing services (Phase 2), database schema renames (Phase 3), and the standalone `telegram-channel-agent` (Phase 4). However, both old and new code paths coexist — `TelegramWebhookController` still handles inbound traffic alongside `ChannelWebhookController`, `PlatformCommandRouter` still uses `ChannelAdapterInterface` instead of `ChannelManager`, admin controllers still reference Telegram-specific names, and console commands still use `app:telegram:*` names.

Phase 5 completes the migration by switching all traffic to the new channel-agnostic services, removing the deprecated `Telegram/` namespace (except repositories), deleting the standalone `telegram-qa` bot, updating admin UI to channel-generic controllers/templates, and renaming console commands to `app:channel:*`.

## What Changes

- **BREAKING** Delete `TelegramWebhookController` — `ChannelWebhookController` becomes the sole webhook entry point (legacy URL alias preserved)
- **New contract** `RoleResolverInterface` in `Channel/Contract/` — extracted from `TelegramRoleResolverInterface`
- **Modified** `PlatformCommandRouter` — uses `ChannelManager.send()` instead of `ChannelAdapterInterface.send()`, uses `RoleResolverInterface` instead of `TelegramRoleResolverInterface`
- **Modified** `ChannelManager` — gains `adminAction()` method for delegating channel-specific admin operations via A2A
- **New controllers** `ChannelInstancesController` and `ChannelConversationsController` — replace `TelegramBotsController` and `TelegramChatsAdminController`
- **New templates** `templates/admin/channels/` — replace `templates/admin/telegram/`
- **Modified** `DashboardController` — `buildTelegramStats()` renamed to `buildChannelStats()`
- **New commands** `app:channel:set-webhook`, `app:channel:poll`, `app:channel:webhook-info`, `app:channel:delete-webhook` — replace `app:telegram:*` with `--type` flag, old names kept as aliases
- **BREAKING** Delete 24 files under `src/Telegram/` (keeping only `Repository/TelegramBotRepository.php` and `Repository/TelegramChatRepository.php`)
- **Delete** `agentic-development/telegram-qa/` directory — HITL now in `telegram-channel-agent`
- **Delete** 4 old Telegram console commands, 2 old admin controllers, 3 old admin templates

## Impact

- Affected specs: `channel-traffic-switch` (new), `channel-admin-ui` (new), `channel-console-commands` (new)
- Affected code:
  - `src/src/Channel/Command/PlatformCommandRouter.php` (modified)
  - `src/src/Channel/ChannelManager.php` (modified)
  - `src/src/Channel/Contract/RoleResolverInterface.php` (new)
  - `src/src/Controller/Api/Webhook/TelegramWebhookController.php` (deleted)
  - `src/src/Controller/Admin/ChannelInstancesController.php` (new)
  - `src/src/Controller/Admin/ChannelConversationsController.php` (new)
  - `src/src/Controller/Admin/TelegramBotsController.php` (deleted)
  - `src/src/Controller/Admin/TelegramChatsAdminController.php` (deleted)
  - `src/src/Controller/Admin/DashboardController.php` (modified)
  - `src/src/Command/Channel*.php` (4 new)
  - `src/src/Command/Telegram*.php` (4 deleted)
  - `src/src/Telegram/` (24 files deleted, 2 kept)
  - `src/templates/admin/channels/` (3 new templates)
  - `src/templates/admin/telegram/` (3 deleted templates)
  - `src/templates/admin/layout.html.twig` (modified)
  - `src/templates/admin/dashboard.html.twig` (modified)
  - `src/config/services.yaml` (modified)
  - `agentic-development/telegram-qa/` (deleted)
  - `agentic-development/README.md` (modified)
- Dependencies: Phases 1–4 (all completed)
- Related changes: `extract-channel-agents-phase1` (completed), `extract-channel-agents-phase2` (completed)
- Parent spec: `agentic-development/openspec/changes/extract-channel-agents/` (Phase 5 of 6)
