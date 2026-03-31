# channel-abstractions — Phase 1 Spec Delta

## ADDED Requirements

### Requirement: Channel DTO Namespace

The platform SHALL provide a `App\Channel\DTO\` namespace containing channel-agnostic data transfer objects used by all channel integrations. The following DTOs MUST be available:

- `NormalizedEvent` — represents a normalized inbound event from any channel
- `NormalizedChat` — represents a chat/conversation context
- `NormalizedSender` — represents the message sender
- `NormalizedMessage` — represents the message content and metadata
- `DeliveryPayload` — represents an outbound delivery request with `channelInstanceId`, `target`, `text`, and `contentType`
- `DeliveryResult` — represents the outcome of a delivery attempt
- `DeliveryTarget` — represents the delivery destination address

Each DTO MUST be a `final readonly` class with a `toArray()` method for serialization.

#### Scenario: NormalizedEvent is created with platform-agnostic fields

- **WHEN** a `NormalizedEvent` is constructed with `platform: "telegram"`, `eventType: "message_received"`, and valid chat/sender/message DTOs
- **THEN** the object is created successfully with all fields accessible
- **AND** `toArray()` returns a serializable array with snake_case keys

#### Scenario: NormalizedEvent works for non-Telegram platforms

- **WHEN** a `NormalizedEvent` is constructed with `platform: "discord"` and valid sub-DTOs
- **THEN** the object is created successfully — no Telegram-specific validation is enforced

#### Scenario: DeliveryPayload uses channelInstanceId

- **WHEN** a `DeliveryPayload` is constructed with `channelInstanceId: "bot-123"`, a `DeliveryTarget`, `text`, and `contentType`
- **THEN** the `channelInstanceId` property contains the provided value
- **AND** the payload is usable by any channel adapter

#### Scenario: DeliveryTarget parses compound address

- **WHEN** `DeliveryTarget::fromAddress("12345:678")` is called
- **THEN** `chatId` is `"12345"` and `threadId` is `678`

#### Scenario: DeliveryTarget parses simple address

- **WHEN** `DeliveryTarget::fromAddress("12345")` is called
- **THEN** `chatId` is `"12345"` and `threadId` is `null`

### Requirement: Channel Capabilities DTO

The platform SHALL provide a `App\Channel\DTO\ChannelCapabilities` DTO that declares the features supported by a channel integration.

The DTO MUST include the following properties:
- `supportsThreads: bool`
- `supportsReactions: bool`
- `supportsEditing: bool`
- `supportsMedia: bool`
- `supportsMediaGroups: bool`
- `supportsCallbackQueries: bool`
- `maxMessageLength: int`
- `maxCaptionLength: int`
- `supportedParseFormats: array` (list of strings, e.g., `['markdown', 'html', 'text']`)

#### Scenario: Telegram capabilities are representable

- **WHEN** a `ChannelCapabilities` is constructed with `supportsThreads: true`, `supportsReactions: false`, `supportsEditing: true`, `supportsMedia: true`, `supportsMediaGroups: true`, `supportsCallbackQueries: true`, `maxMessageLength: 4096`, `maxCaptionLength: 1024`, `supportedParseFormats: ['markdown', 'html', 'text']`
- **THEN** all properties are accessible and `toArray()` returns the correct structure

#### Scenario: Minimal channel capabilities

- **WHEN** a `ChannelCapabilities` is constructed with all boolean features set to `false`, `maxMessageLength: 2000`, `maxCaptionLength: 0`, `supportedParseFormats: ['text']`
- **THEN** the object represents a minimal text-only channel

### Requirement: Channel Adapter Contract

The platform SHALL provide `App\Channel\Contract\ChannelAdapterInterface` as the contract for channel delivery adapters.

The interface MUST define:
- `send(DeliveryPayload $payload): DeliveryResult` — deliver a message through the channel
- `supports(string $type): bool` — check if this adapter handles the given channel type

#### Scenario: Telegram adapter implements channel contract

- **WHEN** `TelegramDeliveryAdapter` is checked against `ChannelAdapterInterface`
- **THEN** it implements the interface (either directly or via the deprecated alias)

#### Scenario: Adapter declares supported channel type

- **WHEN** `supports("telegram")` is called on `TelegramDeliveryAdapter`
- **THEN** it returns `true`

- **WHEN** `supports("discord")` is called on `TelegramDeliveryAdapter`
- **THEN** it returns `false`

### Requirement: Platform Command Router

The platform SHALL provide `App\Channel\Command\PlatformCommandRouter` that routes platform-level chat commands to their handlers. The router MUST:

- Accept a `NormalizedEvent` and determine if it contains a platform command
- Check sender role authorization before executing privileged commands
- Delegate to the appropriate handler and dispatch the response
- Fall back to agent-declared commands via `AgentRegistryInterface`
- Return an "unknown command" response for unrecognized commands

Platform commands:
- `/help` — available to all users
- `/agents` — available to all users
- `/agent enable <name>` — requires moderator role or higher
- `/agent disable <name>` — requires moderator role or higher

#### Scenario: Help command is routed and responded

- **WHEN** a `NormalizedEvent` with `commandName: "/help"` is received from a user
- **THEN** `PlatformCommandRouter` delegates to `HelpHandler`
- **AND** the handler returns a `DeliveryPayload` containing the help text
- **AND** the router dispatches the response to the originating channel

#### Scenario: Agent enable requires moderator role

- **WHEN** a `NormalizedEvent` with `commandName: "/agent"`, `commandArgs: "enable hello-agent"` is received from a user with role `"user"`
- **THEN** the router returns a permission-denied response
- **AND** the agent is NOT enabled

#### Scenario: Unknown command returns help hint

- **WHEN** a `NormalizedEvent` with `commandName: "/unknown"` is received
- **AND** no agent declares this command
- **THEN** the router returns a response suggesting `/help`

### Requirement: Channel-Agnostic Command Handlers

Platform command handlers in `App\Channel\Command\Handler\` SHALL be channel-agnostic. Each handler MUST:

- Accept a `NormalizedEvent` (and handler-specific parameters) as input
- Return a `DeliveryPayload` (or `null`) as output
- NOT reference any channel-specific interfaces (`TelegramSenderInterface`, etc.)
- NOT call any channel API directly

#### Scenario: HelpHandler returns DeliveryPayload

- **WHEN** `HelpHandler::handle($event)` is called with a valid `NormalizedEvent`
- **THEN** it returns a `DeliveryPayload` with the help text
- **AND** the payload's `channelInstanceId` matches `$event->botId`
- **AND** the payload's target address matches the event's chat (with thread if present)

#### Scenario: AgentsListHandler returns agent list

- **WHEN** `AgentsListHandler::handle($event)` is called and 2 agents are registered (1 enabled, 1 disabled)
- **THEN** it returns a `DeliveryPayload` with text listing both agents with their status indicators

#### Scenario: AgentEnableHandler returns success message

- **WHEN** `AgentEnableHandler::handle($event, "hello-agent", "moderator")` is called and the agent exists and is disabled
- **THEN** it enables the agent and returns a `DeliveryPayload` with a success message

#### Scenario: AgentDisableHandler handles not-found agent

- **WHEN** `AgentDisableHandler::handle($event, "nonexistent")` is called
- **THEN** it returns a `DeliveryPayload` with an error message indicating the agent was not found

### Requirement: Channel Event Publisher

The platform SHALL provide `App\Channel\EventBus\ChannelEventPublisher` that publishes normalized channel events to the platform's `EventBusInterface`.

The publisher MUST:
- Accept a `NormalizedEvent` from any platform (not just Telegram)
- Dispatch the event via `EventBusInterface::dispatch()` using the event's `eventType` as the topic
- Log the publication with channel-agnostic terminology

#### Scenario: Telegram event is published

- **WHEN** `ChannelEventPublisher::publish()` is called with a `NormalizedEvent` where `platform: "telegram"` and `eventType: "message_received"`
- **THEN** `EventBusInterface::dispatch("message_received", $event->toArray())` is called

#### Scenario: Non-Telegram event is published

- **WHEN** `ChannelEventPublisher::publish()` is called with a `NormalizedEvent` where `platform: "discord"` and `eventType: "message_received"`
- **THEN** `EventBusInterface::dispatch("message_received", $event->toArray())` is called — no platform-specific filtering

### Requirement: Deprecated Telegram Namespace Aliases

The platform SHALL maintain backward-compatible aliases for all classes moved from `App\Telegram\` to `App\Channel\`. Each alias MUST:

- Use `class_alias()` to make the old fully-qualified class name resolve to the new class
- Call `trigger_deprecation('brama/core', '1.0', ...)` on first autoload
- Work transparently with `instanceof`, type hints, and `::class` references

Deprecated aliases:
- `App\Telegram\DTO\NormalizedEvent` → `App\Channel\DTO\NormalizedEvent`
- `App\Telegram\DTO\NormalizedChat` → `App\Channel\DTO\NormalizedChat`
- `App\Telegram\DTO\NormalizedSender` → `App\Channel\DTO\NormalizedSender`
- `App\Telegram\DTO\NormalizedMessage` → `App\Channel\DTO\NormalizedMessage`
- `App\Telegram\Delivery\DeliveryPayload` → `App\Channel\DTO\DeliveryPayload`
- `App\Telegram\Delivery\DeliveryResult` → `App\Channel\DTO\DeliveryResult`
- `App\Telegram\Delivery\DeliveryTarget` → `App\Channel\DTO\DeliveryTarget`
- `App\Telegram\Delivery\ChannelAdapterInterface` → `App\Channel\Contract\ChannelAdapterInterface`
- `App\Telegram\Command\TelegramCommandRouter` → `App\Channel\Command\PlatformCommandRouter`
- `App\Telegram\Command\Handler\HelpHandler` → `App\Channel\Command\Handler\HelpHandler`
- `App\Telegram\Command\Handler\AgentsListHandler` → `App\Channel\Command\Handler\AgentsListHandler`
- `App\Telegram\Command\Handler\AgentEnableHandler` → `App\Channel\Command\Handler\AgentEnableHandler`
- `App\Telegram\Command\Handler\AgentDisableHandler` → `App\Channel\Command\Handler\AgentDisableHandler`
- `App\Telegram\EventBus\TelegramEventPublisher` → `App\Channel\EventBus\ChannelEventPublisher`

#### Scenario: Old import resolves to new class

- **WHEN** code uses `use App\Telegram\DTO\NormalizedEvent` and creates an instance
- **THEN** the instance is of type `App\Channel\DTO\NormalizedEvent`
- **AND** `$instance instanceof \App\Channel\DTO\NormalizedEvent` returns `true`

#### Scenario: Deprecation notice is triggered

- **WHEN** the old class name `App\Telegram\DTO\NormalizedEvent` is autoloaded for the first time
- **THEN** a deprecation notice is triggered via `trigger_deprecation()`
- **AND** the notice includes the old and new class names

#### Scenario: Type hints work across namespaces

- **WHEN** a function declares parameter type `App\Channel\DTO\NormalizedEvent`
- **AND** an object created via `new \App\Telegram\DTO\NormalizedEvent(...)` is passed
- **THEN** the type check passes — `class_alias` ensures both names refer to the same class
