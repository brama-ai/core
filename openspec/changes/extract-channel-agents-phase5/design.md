# Design: Extract Channel Agents Phase 5 — Traffic Switch + Cleanup

## Context

Phases 1–4 established the channel agent architecture with dual code paths. Both `TelegramWebhookController` and `ChannelWebhookController` handle the same URL pattern, `PlatformCommandRouter` uses the old `ChannelAdapterInterface` instead of `ChannelManager`, admin controllers are Telegram-specific, and the deprecated `Telegram/` namespace still contains 24 files. Phase 5 eliminates all old code paths and completes the migration.

## Goals / Non-Goals

**Goals:**
- Single inbound path: `ChannelWebhookController` only
- Single outbound path: `ChannelManager.send()` only
- Channel-generic admin UI with A2A-delegated admin actions
- Channel-generic console commands with `--type` flag
- Clean `src/Telegram/` namespace (only repositories remain)
- Remove standalone `telegram-qa` (merged into agent in Phase 4.5)

**Non-Goals:**
- Renaming `TelegramBotRepository` / `TelegramChatRepository` (deferred — they work with renamed tables already)
- Implementing new channel types (Discord, Slack)
- Changing the A2A protocol or EventBus
- Admin UI redesign beyond controller/template renames

## Decisions

### 1. Task execution order is dependency-driven

```
5.1 (inbound switch) → 5.2 (outbound switch) → 5.5 (admin UI) → 5.6 (console commands) → 5.3 (namespace cleanup) → 5.4 (telegram-qa removal)
```

Rationale: Task 5.3 (delete Telegram namespace) can only run after all consumers are eliminated. Tasks 5.1 and 5.2 remove the webhook and outbound consumers. Tasks 5.5 and 5.6 remove admin and CLI consumers. Task 5.4 is independent but logically last.

### 2. RoleResolverInterface extraction

`PlatformCommandRouter` currently depends on `TelegramRoleResolverInterface`. We extract a channel-agnostic `RoleResolverInterface` in `Channel/Contract/` with the same method signature. The existing `TelegramRoleResolver` becomes the concrete implementation wired via Symfony services.

This avoids changing the role resolution logic while making the dependency channel-agnostic.

### 3. ChannelManager gains adminAction()

Admin UI and console commands need to delegate channel-specific operations (test-connection, set-webhook, delete-webhook, webhook-info) to the channel agent. Rather than creating a separate service, we add `adminAction(string $channelType, string $action, array $params): array` to `ChannelManager`. This keeps the A2A routing centralized.

### 4. Admin UI: new controllers, not modified old ones

Creating new `ChannelInstancesController` and `ChannelConversationsController` rather than renaming the old controllers. This allows clean route definitions and avoids git rename confusion. Old controllers are deleted after new ones are verified.

### 5. Console command aliases via Symfony getAliases()

Symfony's `getAliases()` method provides built-in alias support. Old command names (`app:telegram:*`) are registered as aliases on the new `app:channel:*` commands. A deprecation notice is printed when the alias is used.

### 6. Repositories kept in Telegram namespace

`TelegramBotRepository` and `TelegramChatRepository` remain in `src/Telegram/Repository/` because:
- They already query the renamed `channel_instances`/`channel_conversations` tables (Phase 3)
- Renaming them to `ChannelInstanceRepository`/`ChannelConversationRepository` is a separate concern
- The admin UI controllers reference them; changing both at once increases risk
- A future cleanup task can rename them without functional changes

## Risks / Trade-offs

| Risk | Impact | Mitigation |
|------|--------|------------|
| Route conflict during 5.1 | Both controllers register same URL | Delete `TelegramWebhookController` first — `ChannelWebhookController` already has the legacy alias |
| Admin action A2A latency | Admin UI feels slower | Agent runs locally, A2A hop <10ms. Telegram API latency dominates. |
| Missing service wiring after deletions | Container fails to compile | Run `php bin/console cache:clear` after each task. PHPStan catches missing deps. |
| Broken admin bookmarks | Users get 404 on old URLs | Optional: add 301 redirects from old routes. Coder decision. |
| Incomplete import cleanup | PHPStan fails | Run `grep -r 'use App\\Telegram\\' src/` after 5.3 to verify zero non-Repository imports |
| TelegramRoleResolver still needed | Role resolution breaks | Keep as concrete impl of `RoleResolverInterface`. Only the interface moves. |

## Open Questions

None — all design decisions are covered by the parent `extract-channel-agents` design.md and the Phase 5 spec in `agentic-development/openspec/changes/extract-channel-agents/specs/phase5-traffic-switch-cleanup/spec.md`.
