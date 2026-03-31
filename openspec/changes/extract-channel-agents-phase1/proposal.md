# Change: Extract Channel Agents Phase 1 — Core Namespace Extraction

## Why

The platform's channel abstractions (`ChannelAdapterInterface`, `NormalizedEvent` DTOs, `DeliveryPayload`/`DeliveryResult`/`DeliveryTarget`, command handlers, event publisher) are all namespaced under `App\Telegram\` — a specific implementation namespace. This makes them undiscoverable as platform contracts and blocks the multi-channel agent architecture described in the parent spec (`agentic-development/openspec/changes/extract-channel-agents/`).

Phase 1 performs a **namespace-only extraction** with zero behavior changes: move platform-level abstractions from `Telegram/` to a new `Channel/` namespace, leave deprecated aliases in the old locations, and make command handlers and the event publisher channel-agnostic. This is the prerequisite for all subsequent phases (ChannelManager, ChannelWebhookRouter, telegram-channel-agent).

## What Changes

- **New namespace** `App\Channel\DTO\` with moved DTOs: `NormalizedEvent`, `NormalizedChat`, `NormalizedSender`, `NormalizedMessage`, `DeliveryPayload`, `DeliveryResult`, `DeliveryTarget`
- **New DTO** `App\Channel\DTO\ChannelCapabilities` — declares what a channel supports (threads, reactions, editing, media, message limits, parse formats)
- **New namespace** `App\Channel\Contract\` with moved `ChannelAdapterInterface`
- **New namespace** `App\Channel\Command\` with `PlatformCommandRouter` (renamed from `TelegramCommandRouter`) and moved handlers (`HelpHandler`, `AgentsListHandler`, `AgentEnableHandler`, `AgentDisableHandler`)
- **New namespace** `App\Channel\EventBus\` with `ChannelEventPublisher` (renamed from `TelegramEventPublisher`), accepting any `platform` value
- **Deprecated aliases** in all old `Telegram/` locations — `class_alias` wrappers with `@deprecated` docblocks and `trigger_deprecation()` calls
- **Handler refactoring** — command handlers accept `NormalizedEvent` and return `DeliveryPayload` instead of calling `TelegramSenderInterface` directly; the router is responsible for dispatching the response
- **BREAKING**: None — all old class names continue to work via aliases

## Impact

- Affected specs: creates new `channel-abstractions` capability
- Affected code:
  - `src/src/Channel/DTO/*.php` (7 moved + 1 new)
  - `src/src/Channel/Contract/ChannelAdapterInterface.php` (moved)
  - `src/src/Channel/Command/PlatformCommandRouter.php` (moved + renamed)
  - `src/src/Channel/Command/Handler/*.php` (4 moved + refactored)
  - `src/src/Channel/EventBus/ChannelEventPublisher.php` (moved + renamed)
  - `src/src/Telegram/DTO/*.php` (deprecated aliases)
  - `src/src/Telegram/Delivery/*.php` (deprecated aliases)
  - `src/src/Telegram/Command/*.php` (deprecated aliases)
  - `src/src/Telegram/EventBus/*.php` (deprecated alias)
- Dependencies: none — this is a pure refactoring with no new external dependencies
- Related changes: `add-telegram-delivery-adapter` (uses `ChannelAdapterInterface` — will need import update), `add-telegram-admin-ui` (uses command handlers — will need import update)
- Parent spec: `agentic-development/openspec/changes/extract-channel-agents/` (Phase 1 of 6)
