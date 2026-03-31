# Design: Extract Channel Agents Phase 1 — Core Namespace Extraction

## Context

The platform's Telegram integration lives in `brama-core/src/src/Telegram/`. Within that namespace, several classes are actually **platform-level abstractions** that have no Telegram-specific logic:

| Class | Current location | Telegram-specific? |
|-------|-----------------|-------------------|
| `NormalizedEvent` | `Telegram/DTO/` | No — already has a `platform` field |
| `NormalizedChat` | `Telegram/DTO/` | No — generic chat model |
| `NormalizedSender` | `Telegram/DTO/` | No — generic sender model |
| `NormalizedMessage` | `Telegram/DTO/` | No — generic message model |
| `DeliveryPayload` | `Telegram/Delivery/` | No — generic delivery request |
| `DeliveryResult` | `Telegram/Delivery/` | No — generic delivery outcome |
| `DeliveryTarget` | `Telegram/Delivery/` | No — generic address model |
| `ChannelAdapterInterface` | `Telegram/Delivery/` | No — the abstraction itself |
| `TelegramCommandRouter` | `Telegram/Command/` | Partially — routing logic is generic, but calls `TelegramSenderInterface` |
| `HelpHandler` et al. | `Telegram/Command/Handler/` | Partially — logic is generic, but signature requires `TelegramSenderInterface` |
| `TelegramEventPublisher` | `Telegram/EventBus/` | No — just dispatches to `EventBusInterface` |

Phase 1 moves these to `App\Channel\` and makes the partially-coupled ones fully channel-agnostic.

## Goals

- Every platform-level channel abstraction lives in `App\Channel\`
- Zero breaking changes — old imports continue to work via deprecated aliases
- Command handlers become channel-agnostic (no `TelegramSenderInterface` in their signatures)
- Event publisher accepts events from any platform, not just "telegram"
- New `ChannelCapabilities` DTO is available for future channel agents to declare their features

## Non-Goals

- Creating `ChannelManager`, `ChannelWebhookRouter`, or `ChannelRegistry` (Phase 2)
- Moving Telegram-specific code (`TelegramApiClient`, `TelegramSender`, `TelegramUpdateNormalizer`, `TelegramDeliveryAdapter`) — these stay in `Telegram/`
- Database schema changes (Phase 3)
- Creating the telegram-channel-agent (Phase 4)

## Decisions

### 1. Deprecated alias strategy

Each moved class gets a thin alias file in the old location:

```php
// src/src/Telegram/DTO/NormalizedEvent.php (after move)
<?php
namespace App\Telegram\DTO;

trigger_deprecation('brama/core', '1.0', 'Class "%s" is deprecated, use "%s" instead.', NormalizedEvent::class, \App\Channel\DTO\NormalizedEvent::class);

class_alias(\App\Channel\DTO\NormalizedEvent::class, NormalizedEvent::class);
```

**Why `class_alias` over `extends`?** `class_alias` makes the old name a true alias — `instanceof` checks, type hints, and `::class` references all work transparently. An `extends` approach would require the old class to be non-final, which conflicts with the existing `final` declarations.

**Why `trigger_deprecation`?** Symfony's `trigger_deprecation()` integrates with the deprecation logging system, making it easy to track and eventually remove aliases. It only triggers on first autoload, not on every usage.

### 2. Command handler refactoring — return `DeliveryPayload` instead of calling sender

Current handlers have this signature:
```php
public function handle(NormalizedEvent $event, TelegramSenderInterface $sender): void
```

This couples them to Telegram. The new signature:
```php
public function handle(NormalizedEvent $event): ?DeliveryPayload
```

The handler returns a `DeliveryPayload` (or `null` if no response needed). The `PlatformCommandRouter` is responsible for dispatching the response through whatever channel the event came from.

**Why not an interface for handlers?** The four handlers have different additional parameters (`agentName`, `role`). A common interface would either be too generic or require parameter objects. For now, the router calls each handler directly with the appropriate arguments. An interface can be introduced in Phase 2 when the router delegates through `ChannelManager`.

**Transition strategy:** During Phase 1, `PlatformCommandRouter` still uses `TelegramSenderInterface` internally to send the `DeliveryPayload`. The decoupling is at the handler level — handlers don't know about Telegram. Full decoupling (router uses `ChannelManager`) happens in Phase 2.

### 3. `ChannelCapabilities` DTO — forward-looking but used immediately

The DTO is created now even though no channel agent exists yet because:
- It documents the platform's expectations of channel features
- It can be used by `PlatformCommandRouter` to adapt responses (e.g., skip markdown if channel doesn't support it)
- It's referenced in the parent spec's `ChannelAgentInterface` contract

### 4. `ChannelEventPublisher` — minimal rename

`TelegramEventPublisher` becomes `ChannelEventPublisher` with one change: the log message says "channel event" instead of "Telegram event". The class already accepts any `NormalizedEvent` regardless of `platform` value — it just dispatches to `EventBusInterface`. No logic changes needed.

### 5. `ChannelAdapterInterface` — moved to `Contract/` sub-namespace

The interface moves to `App\Channel\Contract\ChannelAdapterInterface`. The `Contract/` sub-namespace follows the Symfony convention for interfaces that define a contract between core and implementations. `TelegramDeliveryAdapter` continues to implement it — its `use` statement changes to the new namespace (or works via the deprecated alias).

### 6. `DeliveryPayload` — generalize `botId` to `channelInstanceId`

The current `DeliveryPayload` has a `$botId` property with a docblock referencing `TelegramBotRegistry`. In the new location, this becomes `$channelInstanceId` — a generic identifier for the channel instance (bot, app, integration) that should handle the delivery. The old `$botId` property is kept as a deprecated alias getter.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| Deprecated aliases add autoloader overhead | Negligible — `class_alias` is resolved once per request by PHP autoloader |
| Handlers returning `DeliveryPayload` is a signature change | All handler call sites are in `TelegramCommandRouter` (now `PlatformCommandRouter`) — single file to update |
| Other code imports `App\Telegram\DTO\NormalizedEvent` directly | Aliases ensure these continue to work; PHPStan will flag deprecations for gradual migration |
| `add-telegram-delivery-adapter` change uses old namespace | That change's `TelegramDeliveryAdapter` stays in `Telegram/` and uses the new `App\Channel\Contract\ChannelAdapterInterface` import (or alias) |

## File Mapping

| Old path | New path | Notes |
|----------|----------|-------|
| `Telegram/DTO/NormalizedEvent.php` | `Channel/DTO/NormalizedEvent.php` | Moved, old becomes alias |
| `Telegram/DTO/NormalizedChat.php` | `Channel/DTO/NormalizedChat.php` | Moved, old becomes alias |
| `Telegram/DTO/NormalizedSender.php` | `Channel/DTO/NormalizedSender.php` | Moved, old becomes alias |
| `Telegram/DTO/NormalizedMessage.php` | `Channel/DTO/NormalizedMessage.php` | Moved, old becomes alias |
| `Telegram/Delivery/DeliveryPayload.php` | `Channel/DTO/DeliveryPayload.php` | Moved + `botId` → `channelInstanceId`, old becomes alias |
| `Telegram/Delivery/DeliveryResult.php` | `Channel/DTO/DeliveryResult.php` | Moved, old becomes alias |
| `Telegram/Delivery/DeliveryTarget.php` | `Channel/DTO/DeliveryTarget.php` | Moved, old becomes alias |
| — | `Channel/DTO/ChannelCapabilities.php` | New |
| `Telegram/Delivery/ChannelAdapterInterface.php` | `Channel/Contract/ChannelAdapterInterface.php` | Moved, old becomes alias |
| `Telegram/Command/TelegramCommandRouter.php` | `Channel/Command/PlatformCommandRouter.php` | Moved + renamed + refactored |
| `Telegram/Command/Handler/HelpHandler.php` | `Channel/Command/Handler/HelpHandler.php` | Moved + signature change |
| `Telegram/Command/Handler/AgentsListHandler.php` | `Channel/Command/Handler/AgentsListHandler.php` | Moved + signature change |
| `Telegram/Command/Handler/AgentEnableHandler.php` | `Channel/Command/Handler/AgentEnableHandler.php` | Moved + signature change |
| `Telegram/Command/Handler/AgentDisableHandler.php` | `Channel/Command/Handler/AgentDisableHandler.php` | Moved + signature change |
| `Telegram/EventBus/TelegramEventPublisher.php` | `Channel/EventBus/ChannelEventPublisher.php` | Moved + renamed |

## Open Questions

None — Phase 1 scope is well-defined and self-contained.
