# Tasks: Extract Channel Agents Phase 5 — Traffic Switch + Cleanup

**Change ID:** `extract-channel-agents-phase5`
**Parent:** `agentic-development/openspec/changes/extract-channel-agents/` (Phase 5)
**Prerequisites:** `extract-channel-agents-phase1` (completed), `extract-channel-agents-phase2` (completed), Phase 3 DB migration (completed), Phase 4 telegram-channel-agent (completed)

**Execution order:** 5.1 → 5.2 → 5.5 → 5.6 → 5.3 → 5.4 (dependency-driven)

## 1. Switch Inbound Traffic (Task 5.1)

- [ ] 1.1 Delete `src/src/Controller/Api/Webhook/TelegramWebhookController.php`
  - `ChannelWebhookController` becomes the sole webhook entry point
  - Legacy URL `/api/v1/webhook/telegram/{channelId}` already preserved via `ChannelWebhookController` alias route
  - Resolves route conflict: both controllers currently register POST on `/api/v1/webhook/telegram/{param}`
  - **Verify:** POST to `/api/v1/webhook/telegram/{channelId}` works, events dispatch, platform commands respond
  - **Impl:** delete `src/src/Controller/Api/Webhook/TelegramWebhookController.php`

- [ ] 1.2 Remove any references to `TelegramWebhookController` in config, routes, or service definitions
  - Search `config/` and `src/` for `TelegramWebhookController`
  - **Verify:** zero references in PHP files, config files, or route definitions. PHPStan passes.

## 2. Switch Outbound Traffic (Task 5.2)

- [ ] 2.1 Create `src/src/Channel/Contract/RoleResolverInterface.php`
  - Extract from `TelegramRoleResolverInterface` with same method signature:
    ```php
    interface RoleResolverInterface
    {
        public function resolve(string $channelInstanceId, string $chatId, string $userId): string;
    }
    ```
  - **Verify:** PHPStan passes
  - **Impl:** `src/src/Channel/Contract/RoleResolverInterface.php`

- [ ] 2.2 Make `TelegramRoleResolver` implement `RoleResolverInterface`
  - Add `implements RoleResolverInterface` to `TelegramRoleResolver`
  - Wire `RoleResolverInterface` → `TelegramRoleResolver` in `config/services.yaml`
  - **Verify:** service container compiles, PHPStan passes
  - **Impl:** `src/src/Telegram/Service/TelegramRoleResolver.php`, `src/config/services.yaml`

- [ ] 2.3 Update `PlatformCommandRouter` to use `ChannelManager` and `RoleResolverInterface`
  - Replace constructor dependency `ChannelAdapterInterface` → `ChannelManager`
  - Replace constructor dependency `TelegramRoleResolverInterface` → `RoleResolverInterface`
  - Update `sendReply()`: call `$this->channelManager->send($event->platform, $target, $payload)` instead of `$this->channelAdapter->send($payload)`
  - Update `handlePlatformCommand()`: call `$this->channelManager->send()` for handler results
  - **Verify:** `/help`, `/agents` commands respond via ChannelManager → A2A. No `TelegramSender` imports outside `src/Telegram/`. PHPStan passes.
  - **Impl:** `src/src/Channel/Command/PlatformCommandRouter.php`

- [ ] 2.4 Add `adminAction()` method to `ChannelManager`
  - Method signature: `public function adminAction(string $channelType, string $action, array $params): array`
  - Resolves agent via `ChannelRegistry`, calls `channel.adminAction` via `A2AClientInterface`
  - **Verify:** unit test covers successful admin action, channel resolution failure
  - **Impl:** `src/src/Channel/ChannelManager.php`

## 3. Update Admin UI (Task 5.5)

- [ ] 3.1 Create `src/src/Controller/Admin/ChannelInstancesController.php`
  - Routes: `/admin/channels/instances`, `/admin/channels/instances/new`, `/{id}/edit`, `/{id}/delete`, `/{id}/test-connection`, `/{id}/set-webhook`, `/{id}/webhook-info`
  - Admin actions (test-connection, set-webhook, webhook-info) delegate via `ChannelManager.adminAction()` → agent A2A `channel.adminAction`
  - No direct `TelegramApiClient` usage
  - Uses `TelegramBotRepository` for CRUD (repository already queries `channel_instances` table)
  - **Verify:** admin pages render at new URLs, CRUD operations work, admin actions delegated via A2A
  - **Impl:** `src/src/Controller/Admin/ChannelInstancesController.php`

- [ ] 3.2 Create `src/src/Controller/Admin/ChannelConversationsController.php`
  - Route: `/admin/channels/conversations`
  - Uses `TelegramChatRepository` (already queries `channel_conversations` table)
  - **Verify:** conversations list page renders at new URL
  - **Impl:** `src/src/Controller/Admin/ChannelConversationsController.php`

- [ ] 3.3 Create new Twig templates under `templates/admin/channels/`
  - `instances.html.twig` (from `telegram/bots.html.twig`)
  - `instance_form.html.twig` (from `telegram/bot_form.html.twig`)
  - `conversations.html.twig` (from `telegram/chats.html.twig`)
  - Channel-specific form fields loaded dynamically based on `channel_type`
  - **Verify:** all templates render correctly with new route names and variable names
  - **Impl:** `src/templates/admin/channels/`

- [ ] 3.4 Update `DashboardController`: `buildTelegramStats()` → `buildChannelStats()`
  - Rename method and template variable from `telegram_stats` to `channel_stats`
  - **Verify:** dashboard renders with channel stats section
  - **Impl:** `src/src/Controller/Admin/DashboardController.php`

- [ ] 3.5 Update `templates/admin/layout.html.twig`
  - Nav link → `admin_channel_instances`, label "Channels"
  - **Verify:** navigation shows "Channels" link pointing to correct route
  - **Impl:** `src/templates/admin/layout.html.twig`

- [ ] 3.6 Update `templates/admin/dashboard.html.twig`
  - Use `channel_stats` variable, links to `admin_channel_instances`
  - **Verify:** dashboard links work
  - **Impl:** `src/templates/admin/dashboard.html.twig`

- [ ] 3.7 Delete old admin controllers and templates
  - Delete `src/src/Controller/Admin/TelegramBotsController.php`
  - Delete `src/src/Controller/Admin/TelegramChatsAdminController.php`
  - Delete `src/templates/admin/telegram/` directory (3 templates)
  - **Verify:** no references to deleted controllers/templates remain. PHPStan passes.

## 4. Update Console Commands (Task 5.6)

- [ ] 4.1 Create `src/src/Command/ChannelSetWebhookCommand.php`
  - Name: `app:channel:set-webhook`, alias: `app:telegram:set-webhook`
  - Accepts `--type` option (default: `"telegram"`)
  - Delegates via `ChannelManager.adminAction($type, "set-webhook", ...)`
  - Deprecation notice when alias is used
  - **Verify:** `php bin/console app:channel:set-webhook --type telegram` works
  - **Impl:** `src/src/Command/ChannelSetWebhookCommand.php`

- [ ] 4.2 Create `src/src/Command/ChannelPollCommand.php`
  - Name: `app:channel:poll`, alias: `app:telegram:poll`
  - Accepts `--type` option (default: `"telegram"`)
  - **Verify:** command runs with `--type telegram`
  - **Impl:** `src/src/Command/ChannelPollCommand.php`

- [ ] 4.3 Create `src/src/Command/ChannelWebhookInfoCommand.php`
  - Name: `app:channel:webhook-info`, alias: `app:telegram:webhook-info`
  - Accepts `--type` option (default: `"telegram"`)
  - Delegates via `ChannelManager.adminAction($type, "webhook-info", ...)`
  - **Verify:** command runs with `--type telegram`
  - **Impl:** `src/src/Command/ChannelWebhookInfoCommand.php`

- [ ] 4.4 Create `src/src/Command/ChannelDeleteWebhookCommand.php`
  - Name: `app:channel:delete-webhook`, alias: `app:telegram:delete-webhook`
  - Accepts `--type` option (default: `"telegram"`)
  - Delegates via `ChannelManager.adminAction($type, "delete-webhook", ...)`
  - **Verify:** command runs with `--type telegram`
  - **Impl:** `src/src/Command/ChannelDeleteWebhookCommand.php`

- [ ] 4.5 Delete old Telegram console commands
  - Delete `src/src/Command/TelegramWebhookCommand.php`
  - Delete `src/src/Command/TelegramPollCommand.php`
  - Delete `src/src/Command/TelegramWebhookInfoCommand.php`
  - Delete `src/src/Command/TelegramDeleteWebhookCommand.php`
  - **Verify:** `php bin/console list app:channel` shows all 4 new commands. Old aliases functional.

## 5. Remove Deprecated Telegram Namespace (Task 5.3)

**Must run after tasks 1, 2, 3, 4 — all consumers eliminated.**

- [ ] 5.1 Delete 14 deprecated alias files from Phase 1
  - `src/Telegram/DTO/NormalizedChat.php`
  - `src/Telegram/DTO/NormalizedEvent.php`
  - `src/Telegram/DTO/NormalizedMessage.php`
  - `src/Telegram/DTO/NormalizedSender.php`
  - `src/Telegram/Delivery/ChannelAdapterInterface.php`
  - `src/Telegram/Delivery/DeliveryPayload.php`
  - `src/Telegram/Delivery/DeliveryResult.php`
  - `src/Telegram/Delivery/DeliveryTarget.php`
  - `src/Telegram/Command/TelegramCommandRouter.php`
  - `src/Telegram/Command/Handler/HelpHandler.php`
  - `src/Telegram/Command/Handler/AgentsListHandler.php`
  - `src/Telegram/Command/Handler/AgentEnableHandler.php`
  - `src/Telegram/Command/Handler/AgentDisableHandler.php`
  - `src/Telegram/EventBus/TelegramEventPublisher.php`
  - **Verify:** PHPStan passes after deletion

- [ ] 5.2 Delete 10 active Telegram service files
  - `src/Telegram/Api/TelegramApiClient.php`
  - `src/Telegram/Api/TelegramApiClientInterface.php`
  - `src/Telegram/Delivery/TelegramDeliveryAdapter.php`
  - `src/Telegram/Service/TelegramBotRegistry.php`
  - `src/Telegram/Service/TelegramChatTracker.php`
  - `src/Telegram/Service/TelegramSender.php`
  - `src/Telegram/Service/TelegramSenderInterface.php`
  - `src/Telegram/Service/TelegramUpdateNormalizer.php`
  - `src/Telegram/Service/TelegramRoleResolver.php`
  - `src/Telegram/Service/TelegramRoleResolverInterface.php`
  - **Note:** `TelegramRoleResolver` deletion requires that `RoleResolverInterface` binding in `services.yaml` points to a concrete implementation. If `TelegramRoleResolver` is the only implementation, it MUST be moved to `src/Channel/` or kept. Coder decides based on scope.
  - **Verify:** PHPStan passes, all tests green

- [ ] 5.3 Remove Symfony service definitions for deleted classes from `config/services.yaml`
  - Remove explicit definitions and aliases for all deleted Telegram classes
  - **Verify:** `php bin/console cache:clear` succeeds, container compiles

- [ ] 5.4 Delete empty subdirectories under `src/Telegram/`
  - Delete `Api/`, `Command/` (and `Command/Handler/`), `Delivery/`, `DTO/`, `EventBus/`, `Service/`
  - **Verify:** `src/Telegram/` contains only `Repository/` with 2 files

- [ ] 5.5 Verify zero non-Repository imports from Telegram namespace
  - Search for `use App\Telegram\` excluding `Repository` imports
  - **Verify:** zero references found, PHPStan level max passes, all tests green

## 6. Remove Standalone telegram-qa (Task 5.4)

- [ ] 6.1 Delete `agentic-development/telegram-qa/` directory
  - Files: `package.json`, `tsconfig.json`, `src/bot.ts`, `src/qa-bridge.ts`, `src/formatter.ts`
  - **Verify:** directory no longer exists

- [ ] 6.2 Update `agentic-development/README.md`
  - Remove `telegram-qa/` reference from directory listing
  - Note that HITL functionality is now provided by `telegram-channel-agent`
  - **Verify:** README is accurate

- [ ] 6.3 Verify no pipeline references to telegram-qa
  - Search `.sh`, `.yaml`, `.json` configs for `telegram-qa` as a process to spawn
  - **Verify:** zero references found, HITL works via agent A2A

## 7. Documentation

- [ ] 7.1 Update `docs/` with channel traffic switch documentation
  - Document the new admin routes (`/admin/channels/instances`, `/admin/channels/conversations`)
  - Document the new console commands (`app:channel:*` with `--type` flag)
  - Document the removal of `src/Telegram/` namespace (except repositories)
  - **Verify:** documentation is accurate and complete

- [ ] 7.2 Update `docs/agent-requirements/conventions.md` if channel admin action A2A contract is affected
  - **Verify:** conventions doc reflects `channel.adminAction` skill expectations

## 8. Quality Checks

- [ ] 8.1 PHPStan level max passes with zero errors on `brama-core/src/`
- [ ] 8.2 PHP CS Fixer passes with project rules
- [ ] 8.3 All existing unit and functional tests pass (no regressions)
- [ ] 8.4 Symfony container compiles without errors (`php bin/console cache:clear`)
- [ ] 8.5 Admin UI fully functional (all pages render, all actions work)
- [ ] 8.6 Webhook flow end-to-end: Telegram → ChannelWebhookController → agent → EventBus
- [ ] 8.7 Outbound flow end-to-end: PlatformCommandRouter → ChannelManager → agent → Telegram API
