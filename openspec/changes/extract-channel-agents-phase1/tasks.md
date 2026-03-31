# Tasks: Extract Channel Agents Phase 1 — Core Namespace Extraction

**Change ID:** `extract-channel-agents-phase1`
**Parent spec:** `agentic-development/openspec/changes/extract-channel-agents/` (tasks 1.1–1.4)

## 1. Create Channel DTO Namespace and Move DTOs

- [ ] **1.1** Create `src/src/Channel/DTO/NormalizedEvent.php`
  - Copy from `Telegram/DTO/NormalizedEvent.php`, change namespace to `App\Channel\DTO`
  - No logic changes — class is already platform-agnostic
  - **Verify:** class resolves under new namespace, PHPStan clean

- [ ] **1.2** Create `src/src/Channel/DTO/NormalizedChat.php`, `NormalizedSender.php`, `NormalizedMessage.php`
  - Copy from `Telegram/DTO/`, change namespace to `App\Channel\DTO`
  - No logic changes
  - **Verify:** all three classes resolve, PHPStan clean

- [ ] **1.3** Create `src/src/Channel/DTO/DeliveryPayload.php`
  - Copy from `Telegram/Delivery/DeliveryPayload.php`, change namespace to `App\Channel\DTO`
  - Rename property `$botId` to `$channelInstanceId` with updated docblock (generic: "Channel instance identifier used to look up credentials")
  - Update `DeliveryTarget` type hint to `App\Channel\DTO\DeliveryTarget`
  - **Verify:** class resolves, PHPStan clean

- [ ] **1.4** Create `src/src/Channel/DTO/DeliveryResult.php` and `DeliveryTarget.php`
  - Copy from `Telegram/Delivery/`, change namespace to `App\Channel\DTO`
  - `DeliveryTarget`: update docblock to remove Telegram-specific references ("Resolved Telegram chat_id" → "Resolved chat identifier")
  - No logic changes
  - **Verify:** classes resolve, PHPStan clean

- [ ] **1.5** Create `src/src/Channel/DTO/ChannelCapabilities.php`
  - New readonly DTO with constructor-promoted properties:
    - `bool $supportsThreads`
    - `bool $supportsReactions`
    - `bool $supportsEditing`
    - `bool $supportsMedia`
    - `bool $supportsMediaGroups`
    - `bool $supportsCallbackQueries`
    - `int $maxMessageLength`
    - `int $maxCaptionLength`
    - `array $supportedParseFormats` (e.g., `['markdown', 'html', 'text']`)
  - Include `toArray()` method for serialization
  - **Verify:** PHPStan clean, class instantiable

- [ ] **1.6** Create deprecated aliases in old `Telegram/DTO/` and `Telegram/Delivery/` locations
  - Each old file becomes: `trigger_deprecation()` + `class_alias()` pointing to new `Channel\DTO\` class
  - Files: `NormalizedEvent`, `NormalizedChat`, `NormalizedSender`, `NormalizedMessage`, `DeliveryPayload`, `DeliveryResult`, `DeliveryTarget`
  - For `DeliveryPayload`: alias maps old `$botId` usage to new `$channelInstanceId` (same property, renamed)
  - **Verify:** existing code using `App\Telegram\DTO\NormalizedEvent` still works, deprecation notice logged, PHPStan clean

## 2. Move ChannelAdapterInterface to Contract Namespace

- [ ] **2.1** Create `src/src/Channel/Contract/ChannelAdapterInterface.php`
  - Copy from `Telegram/Delivery/ChannelAdapterInterface.php`, change namespace to `App\Channel\Contract`
  - Update method signatures to use `App\Channel\DTO\DeliveryPayload` and `App\Channel\DTO\DeliveryResult`
  - **Verify:** interface resolves under new namespace

- [ ] **2.2** Create deprecated alias at `Telegram/Delivery/ChannelAdapterInterface.php`
  - `trigger_deprecation()` + `class_alias()` to `App\Channel\Contract\ChannelAdapterInterface`
  - **Verify:** `TelegramDeliveryAdapter implements ChannelAdapterInterface` still works via alias, PHPStan clean

- [ ] **2.3** Update `TelegramDeliveryAdapter` to import from new namespace
  - Change `use App\Telegram\Delivery\ChannelAdapterInterface` to `use App\Channel\Contract\ChannelAdapterInterface`
  - Change delivery DTO imports to `App\Channel\DTO\*`
  - **Verify:** `TelegramDeliveryAdapter` still implements the interface, all existing tests pass

## 3. Move Command Handlers to Channel Namespace

- [ ] **3.1** Create `src/src/Channel/Command/Handler/HelpHandler.php`
  - Move from `Telegram/Command/Handler/`, change namespace to `App\Channel\Command\Handler`
  - Change signature: `handle(NormalizedEvent $event): ?DeliveryPayload` (remove `TelegramSenderInterface` parameter)
  - Build response text as before, return `new DeliveryPayload(channelInstanceId: $event->botId, target: DeliveryTarget::fromAddress($event->chat->id . ($event->chat->threadId ? ':' . $event->chat->threadId : '')), text: $text)`
  - Import DTOs from `App\Channel\DTO\`
  - **Verify:** handler returns correct `DeliveryPayload`, PHPStan clean

- [ ] **3.2** Create `src/src/Channel/Command/Handler/AgentsListHandler.php`
  - Same refactoring pattern as HelpHandler: return `DeliveryPayload` instead of calling sender
  - **Verify:** handler returns correct payload, PHPStan clean

- [ ] **3.3** Create `src/src/Channel/Command/Handler/AgentEnableHandler.php`
  - Same pattern; additional params (`$agentName`, `$role`) stay in method signature
  - Signature: `handle(NormalizedEvent $event, string $agentName, string $role): ?DeliveryPayload`
  - **Verify:** handler returns correct payload for enable/already-enabled/not-found cases

- [ ] **3.4** Create `src/src/Channel/Command/Handler/AgentDisableHandler.php`
  - Same pattern; signature: `handle(NormalizedEvent $event, string $agentName): ?DeliveryPayload`
  - **Verify:** handler returns correct payload for disable/already-disabled/not-found cases

- [ ] **3.5** Create `src/src/Channel/Command/PlatformCommandRouter.php`
  - Rename from `TelegramCommandRouter`, namespace `App\Channel\Command`
  - Constructor: keep `TelegramRoleResolverInterface`, `TelegramSenderInterface`, `AgentRegistryInterface`, `LoggerInterface` (Telegram-specific deps stay for now — full decoupling in Phase 2)
  - `route()` method: call refactored handlers, receive `DeliveryPayload`, dispatch via `TelegramSenderInterface` internally
  - Add private `sendPayload(DeliveryPayload $payload): void` method that bridges to `TelegramSenderInterface`
  - **Verify:** `/help`, `/agents`, `/agent enable|disable` commands produce same responses as before

- [ ] **3.6** Create deprecated aliases in old `Telegram/Command/` locations
  - `TelegramCommandRouter` → alias to `PlatformCommandRouter`
  - `Handler/HelpHandler`, `Handler/AgentsListHandler`, `Handler/AgentEnableHandler`, `Handler/AgentDisableHandler` → aliases to new namespace
  - **Verify:** any code referencing old class names still works

## 4. Create ChannelEventPublisher

- [ ] **4.1** Create `src/src/Channel/EventBus/ChannelEventPublisher.php`
  - Copy from `Telegram/EventBus/TelegramEventPublisher.php`, change namespace to `App\Channel\EventBus`
  - Rename class to `ChannelEventPublisher`
  - Update log message: "Publishing channel event to EventBus" (remove "Telegram")
  - Import `NormalizedEvent` from `App\Channel\DTO\`
  - No logic changes — already dispatches any event type to `EventBusInterface`
  - **Verify:** events still dispatch to subscribed agents, log message updated

- [ ] **4.2** Create deprecated alias at `Telegram/EventBus/TelegramEventPublisher.php`
  - `trigger_deprecation()` + `class_alias()` to `App\Channel\EventBus\ChannelEventPublisher`
  - **Verify:** existing service wiring using old class name still works

## 5. Update Service Configuration

- [ ] **5.1** Update `src/config/services.yaml`
  - Register new `App\Channel\` namespace for autowiring
  - Ensure `PlatformCommandRouter` is wired with same dependencies as old `TelegramCommandRouter`
  - Ensure `ChannelEventPublisher` is wired with same dependencies as old `TelegramEventPublisher`
  - Keep old service IDs as aliases during transition
  - **Verify:** `bin/console debug:container` shows new services, old aliases resolve

## 6. Update Existing Tests

- [ ] **6.1** Update unit tests to use new namespaces
  - Update import statements in existing tests that reference moved classes
  - Add tests for `ChannelCapabilities` DTO (construction, `toArray()`)
  - Add tests for refactored handlers (return `DeliveryPayload` instead of void)
  - **Verify:** `codecept run unit` passes, no test references old namespace without alias

## 7. Quality Checks

- [ ] **7.1** Run PHPStan analysis
  - `phpstan analyse` at level 8 — zero errors
  - Verify deprecation notices are properly typed
  - **Verify:** PHPStan clean

- [ ] **7.2** Run PHP CS Fixer
  - `php-cs-fixer check src/src/Channel/` — no violations
  - **Verify:** CS clean

- [ ] **7.3** Run full test suite
  - `codecept run` — all unit and functional suites pass
  - **Verify:** zero failures, zero errors

## 8. Documentation

- [ ] **8.1** Update or create `docs/channel-abstractions.md`
  - Document the `App\Channel\` namespace structure
  - Document the deprecation timeline for `App\Telegram\DTO\` and `App\Telegram\Delivery\` aliases
  - Document `ChannelCapabilities` DTO fields and intended usage
  - Reference the parent spec for the full multi-phase plan
  - **Verify:** document exists and is accurate
