## ADDED Requirements

### Requirement: Channel Set-Webhook Command

The system SHALL provide a console command `app:channel:set-webhook` that delegates webhook registration to the channel agent via `ChannelManager.adminAction()`. The old name `app:telegram:set-webhook` SHALL be kept as an alias with a deprecation notice.

#### Scenario: Set webhook via channel command

Given: `ChannelSetWebhookCommand` is registered as `app:channel:set-webhook`
When: `php bin/console app:channel:set-webhook --type telegram` is executed
Then: the command calls `ChannelManager.adminAction("telegram", "set-webhook", ...)`
And: the webhook is registered via the channel agent's A2A `channel.adminAction` skill
And: the command output shows the result

#### Scenario: Default type is telegram

Given: `ChannelSetWebhookCommand` accepts a `--type` option
When: `php bin/console app:channel:set-webhook` is executed without `--type`
Then: the command defaults to `--type telegram`

#### Scenario: Old alias is functional with deprecation notice

Given: `app:telegram:set-webhook` is registered as an alias
When: `php bin/console app:telegram:set-webhook` is executed
Then: the command runs successfully
And: the output includes a deprecation notice: "This command alias is deprecated. Use app:channel:set-webhook --type telegram instead."

### Requirement: Channel Poll Command

The system SHALL provide a console command `app:channel:poll` that handles long-polling for the specified channel type. The old name `app:telegram:poll` SHALL be kept as an alias with a deprecation notice.

#### Scenario: Poll via channel command

Given: `ChannelPollCommand` is registered as `app:channel:poll`
When: `php bin/console app:channel:poll --type telegram` is executed
Then: the command uses the channel-agnostic flow for polling
And: the old name `app:telegram:poll` works as an alias

### Requirement: Channel Webhook-Info Command

The system SHALL provide a console command `app:channel:webhook-info` that retrieves webhook configuration from the channel agent via `ChannelManager.adminAction()`. The old name `app:telegram:webhook-info` SHALL be kept as an alias with a deprecation notice.

#### Scenario: Get webhook info via channel command

Given: `ChannelWebhookInfoCommand` is registered as `app:channel:webhook-info`
When: `php bin/console app:channel:webhook-info --type telegram` is executed
Then: the command calls `ChannelManager.adminAction("telegram", "webhook-info", ...)`
And: the webhook configuration is displayed
And: the old name `app:telegram:webhook-info` works as an alias

### Requirement: Channel Delete-Webhook Command

The system SHALL provide a console command `app:channel:delete-webhook` that removes the webhook via the channel agent. The old name `app:telegram:delete-webhook` SHALL be kept as an alias with a deprecation notice.

#### Scenario: Delete webhook via channel command

Given: `ChannelDeleteWebhookCommand` is registered as `app:channel:delete-webhook`
When: `php bin/console app:channel:delete-webhook --type telegram` is executed
Then: the command calls `ChannelManager.adminAction("telegram", "delete-webhook", ...)`
And: the webhook is removed
And: the old name `app:telegram:delete-webhook` works as an alias

### Requirement: Channel Command Listing

All four channel commands SHALL appear in the Symfony console command list under the `app:channel` namespace.

#### Scenario: Command list shows all channel commands

Given: all four channel commands are registered
When: `php bin/console list app:channel` is executed
Then: the output shows:
- `app:channel:set-webhook`
- `app:channel:poll`
- `app:channel:webhook-info`
- `app:channel:delete-webhook`

## REMOVED Requirements

### Requirement: Telegram Console Commands

Removed: Four Telegram-specific console commands.
Reason: Replaced by `app:channel:*` commands with `--type` flag. Old names kept as aliases on the new commands.

#### Scenario: Old Telegram commands removed

Removed:
- `src/Command/TelegramWebhookCommand.php` (`app:telegram:set-webhook`)
- `src/Command/TelegramPollCommand.php` (`app:telegram:poll`)
- `src/Command/TelegramWebhookInfoCommand.php` (`app:telegram:webhook-info`)
- `src/Command/TelegramDeleteWebhookCommand.php` (`app:telegram:delete-webhook`)
Reason: Functionality replaced by channel-generic commands. Old command names preserved as aliases via `getAliases()` on the new commands.
