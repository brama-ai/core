## ADDED Requirements

### Requirement: Inbound Traffic via ChannelWebhookController Only

The system SHALL route all inbound channel webhook traffic through `ChannelWebhookController` as the sole webhook entry point. The legacy URL `/api/v1/webhook/telegram/{channelId}` SHALL be preserved via an alias route on `ChannelWebhookController` with `channelType` defaulting to `"telegram"`.

#### Scenario: ChannelWebhookController is the sole webhook entry point

Given: `ChannelWebhookController` exists at `src/Controller/Api/Webhook/ChannelWebhookController.php`
And: `TelegramWebhookController` has been deleted
When: a POST request arrives at `/api/v1/webhook/telegram/{channelId}`
Then: `ChannelWebhookController` handles it with `channelType = "telegram"`
And: the full flow executes: validate -> normalize -> track -> route commands -> publish events
And: there are no route name conflicts in the Symfony router

#### Scenario: Generic channel webhook route works

Given: `ChannelWebhookController` is the sole webhook controller
When: a POST request arrives at `/api/v1/webhook/{channelType}/{channelId}`
Then: `ChannelWebhookController` handles it with the specified `channelType`
And: the channel agent is resolved via `ChannelRegistry`

#### Scenario: No references to TelegramWebhookController remain

Given: `TelegramWebhookController.php` has been deleted
When: the codebase is searched for `TelegramWebhookController`
Then: zero references are found in PHP files, config files, or route definitions
And: PHPStan passes with zero errors

### Requirement: Outbound Traffic via ChannelManager Only

`PlatformCommandRouter` SHALL send all outbound responses through `ChannelManager.send()` instead of `ChannelAdapterInterface.send()`. No service outside `src/Telegram/` SHALL import `TelegramSender` or `TelegramSenderInterface`.

#### Scenario: PlatformCommandRouter uses ChannelManager for responses

Given: `PlatformCommandRouter` has been updated
When: a platform command handler returns a `DeliveryPayload`
Then: `PlatformCommandRouter` calls `$this->channelManager->send($event->platform, $target, $payload)`
And: `ChannelManager` resolves the agent via `ChannelRegistry`
And: calls `channel.sendOutbound` via A2A on the appropriate channel agent
And: the message is delivered to the correct chat/thread

#### Scenario: No business agents use TelegramSender directly

Given: all outbound traffic goes through `ChannelManager`
When: the codebase is searched for `TelegramSender` or `TelegramSenderInterface` imports outside `src/Telegram/`
Then: zero references are found

### Requirement: RoleResolverInterface

A channel-agnostic `RoleResolverInterface` SHALL be created in `App\Channel\Contract` to replace the Telegram-specific `TelegramRoleResolverInterface` dependency in `PlatformCommandRouter`.

#### Scenario: RoleResolverInterface replaces TelegramRoleResolverInterface

Given: `PlatformCommandRouter` currently imports `App\Telegram\Service\TelegramRoleResolverInterface`
When: the dependency is made channel-agnostic
Then: a new `App\Channel\Contract\RoleResolverInterface` exists with method:
```php
public function resolve(string $channelInstanceId, string $chatId, string $userId): string;
```
And: `PlatformCommandRouter` imports `App\Channel\Contract\RoleResolverInterface` instead
And: the existing `TelegramRoleResolver` implements `RoleResolverInterface`
And: Symfony service wiring binds `RoleResolverInterface` to the existing `TelegramRoleResolver`

### Requirement: ChannelManager adminAction Method

`ChannelManager` SHALL provide an `adminAction()` method that delegates channel-specific admin operations to the appropriate channel agent via A2A `channel.adminAction` skill.

#### Scenario: ChannelManager supports admin action delegation

Given: `ChannelManager` currently only has a `send()` method
When: admin UI and console commands need to delegate channel-specific actions
Then: `ChannelManager` gains a new method:
```php
public function adminAction(string $channelType, string $action, array $params): array
```
And: it resolves the agent via `ChannelRegistry`
And: calls `channel.adminAction` via `A2AClientInterface` with `{action, params}`
And: returns the agent's response array

#### Scenario: Admin action with unknown channel type

Given: `ChannelManager.adminAction()` is called with an unregistered channel type
When: `ChannelRegistry` cannot resolve the agent
Then: a `RuntimeException` is thrown with a descriptive message

### Requirement: Remove Deprecated Telegram Namespace

The system SHALL delete all files under `src/Telegram/` except `Repository/TelegramBotRepository.php` and `Repository/TelegramChatRepository.php`. This includes 14 deprecated alias files from Phase 1 and 10 active service files whose consumers were eliminated by tasks 5.1, 5.2, 5.5, and 5.6.

#### Scenario: Deprecated alias files deleted

Given: Phase 1 created `@deprecated` alias files in `src/Telegram/`
When: the deprecated aliases are removed
Then: 14 alias files are deleted (4 DTOs, 4 Delivery, 4 Command/Handler, 1 Command router, 1 EventBus)
And: PHPStan passes with zero errors

#### Scenario: Active Telegram service files deleted

Given: tasks 5.1, 5.2, 5.5, and 5.6 have eliminated all consumers of Telegram services
When: the remaining active files are removed
Then: 10 service files are deleted (2 Api, 1 Delivery adapter, 7 Service)
And: PHPStan passes with zero errors

#### Scenario: Repositories kept

Given: `TelegramBotRepository` and `TelegramChatRepository` query `channel_instances` and `channel_conversations` tables
When: the Telegram namespace is cleaned up
Then: `src/Telegram/Repository/TelegramBotRepository.php` is kept
And: `src/Telegram/Repository/TelegramChatRepository.php` is kept
And: these are the ONLY files remaining under `src/Telegram/`

#### Scenario: Service definitions cleaned up

Given: `config/services.yaml` may contain service definitions for deleted Telegram classes
When: the Telegram namespace is removed
Then: all service definitions referencing deleted classes are removed
And: `php bin/console cache:clear` succeeds

#### Scenario: No non-Repository imports from Telegram namespace

Given: all files except repositories have been deleted
When: the codebase is searched for `use App\Telegram\` excluding `Repository` imports
Then: zero references are found
And: PHPStan level max passes with zero errors

## REMOVED Requirements

### Requirement: TelegramWebhookController

Removed: `src/Controller/Api/Webhook/TelegramWebhookController.php`
Reason: Replaced by `ChannelWebhookController` with legacy URL alias. Route `/api/v1/webhook/telegram/{channelId}` is preserved via the alias route on `ChannelWebhookController`.

#### Scenario: TelegramWebhookController removed

Given: `ChannelWebhookController` handles all webhook traffic including the legacy Telegram URL
When: `TelegramWebhookController` is deleted
Then: no route conflicts exist
And: the legacy URL continues to function

### Requirement: Standalone telegram-qa Bot

Removed: `agentic-development/telegram-qa/` (entire directory)
Reason: HITL functionality merged into `telegram-channel-agent` in Phase 4.5.

#### Scenario: telegram-qa directory removed

Given: Phase 4.5 merged HITL functionality into `telegram-channel-agent`
When: the standalone bot directory is deleted
Then: `agentic-development/telegram-qa/` no longer exists
And: no pipeline scripts reference `telegram-qa` as a process to spawn
And: HITL flow uses `telegram-channel-agent` A2A skills

### Requirement: ChannelAdapterInterface Active Usage

Removed: `ChannelAdapterInterface` is no longer wired as an active service dependency.
Reason: `PlatformCommandRouter` (the sole consumer) now uses `ChannelManager`. `TelegramDeliveryAdapter` (the sole implementation) is deleted.

#### Scenario: ChannelAdapterInterface has no active consumers

Given: `PlatformCommandRouter` has been updated to use `ChannelManager`
When: the codebase is searched for `ChannelAdapterInterface` usage in service constructors
Then: zero active consumers are found
And: `TelegramDeliveryAdapter` is deleted
