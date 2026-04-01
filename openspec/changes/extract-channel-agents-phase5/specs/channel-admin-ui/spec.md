## ADDED Requirements

### Requirement: ChannelInstancesController

The system SHALL provide a `ChannelInstancesController` at `src/Controller/Admin/ChannelInstancesController.php` that replaces `TelegramBotsController` with channel-generic routes and A2A-delegated admin actions.

#### Scenario: Channel instances CRUD routes

Given: `ChannelInstancesController` exists
When: admin navigates to channel management
Then: the following routes are available:

| Route | Name | Method |
|-------|------|--------|
| `/admin/channels/instances` | `admin_channel_instances` | GET |
| `/admin/channels/instances/new` | `admin_channel_instances_new` | GET, POST |
| `/admin/channels/instances/{id}/edit` | `admin_channel_instances_edit` | GET, POST |
| `/admin/channels/instances/{id}/delete` | `admin_channel_instances_delete` | POST |
| `/admin/channels/instances/{id}/test-connection` | `admin_channel_instances_test` | POST |
| `/admin/channels/instances/{id}/set-webhook` | `admin_channel_instances_set_webhook` | POST |
| `/admin/channels/instances/{id}/webhook-info` | `admin_channel_instances_webhook_info` | GET |

#### Scenario: Admin actions delegate to channel agent via ChannelManager

Given: `ChannelInstancesController` handles admin actions
When: test-connection is triggered for a channel instance
Then: the controller calls `ChannelManager.adminAction($channelType, "test-connection", ["token" => $token])`
And: `ChannelManager` resolves the agent via `ChannelRegistry`
And: calls `channel.adminAction` via A2A on the appropriate channel agent
And: the result is displayed to the admin user

#### Scenario: Set-webhook delegates via A2A

Given: admin triggers set-webhook for a channel instance
When: the action is processed
Then: the controller calls `ChannelManager.adminAction($channelType, "set-webhook", ["token" => $token, "url" => $url, "secret" => $secret])`
And: the agent calls the channel provider's API to register the webhook

#### Scenario: Webhook-info delegates via A2A

Given: admin views webhook info for a channel instance
When: the action is processed
Then: the controller calls `ChannelManager.adminAction($channelType, "webhook-info", ["token" => $token])`
And: the current webhook configuration is displayed

#### Scenario: No direct TelegramApiClient usage

Given: `ChannelInstancesController` is fully implemented
When: the controller source is examined
Then: it does not import `TelegramApiClientInterface` or `TelegramApiClient`
And: all channel-specific operations go through `ChannelManager.adminAction()`

### Requirement: ChannelConversationsController

The system SHALL provide a `ChannelConversationsController` at `src/Controller/Admin/ChannelConversationsController.php` that replaces `TelegramChatsAdminController` with a channel-generic route.

#### Scenario: Channel conversations list route

Given: `ChannelConversationsController` exists
When: admin navigates to conversations
Then: route `/admin/channels/conversations` (name: `admin_channel_conversations`) returns the conversations list
And: the controller uses `TelegramChatRepository` (or a renamed equivalent) for data access

### Requirement: Channel-Generic Admin Templates

The system SHALL provide channel-generic Twig templates under `templates/admin/channels/` replacing the Telegram-specific templates.

#### Scenario: Templates renamed and updated

Given: templates exist at `templates/admin/telegram/`
When: templates are moved to channel-generic paths
Then: the following templates exist:
- `templates/admin/channels/instances.html.twig` (was `telegram/bots.html.twig`)
- `templates/admin/channels/instance_form.html.twig` (was `telegram/bot_form.html.twig`)
- `templates/admin/channels/conversations.html.twig` (was `telegram/chats.html.twig`)

#### Scenario: Channel-specific form fields based on channel_type

Given: the instance form template renders
When: `channel_type` is `"telegram"`
Then: the form shows Telegram-specific fields: bot token, username, webhook URL, webhook secret
And: common fields (name, enabled, channel_type) are always rendered
And: future channel types will have their own field sets

### Requirement: Dashboard Channel Stats

The `DashboardController` SHALL use channel-generic stats instead of Telegram-specific stats.

#### Scenario: Dashboard uses channel_stats variable

Given: `DashboardController` has been updated
When: the dashboard page renders
Then: the method `buildChannelStats()` is called (was `buildTelegramStats()`)
And: the template variable is `channel_stats` (was `telegram_stats`)
And: the dashboard shows "Channels" section with links to `admin_channel_instances`

### Requirement: Admin Navigation Updated

The admin layout navigation SHALL link to the channel-generic admin pages.

#### Scenario: Navigation shows Channels link

Given: `templates/admin/layout.html.twig` has been updated
When: the admin layout renders
Then: the navigation contains a link to `admin_channel_instances` with label "Channels"
And: no navigation link points to old Telegram-specific routes

## REMOVED Requirements

### Requirement: TelegramBotsController

Removed: `src/Controller/Admin/TelegramBotsController.php`
Reason: Replaced by `ChannelInstancesController` with channel-generic routes and A2A-delegated admin actions.

#### Scenario: TelegramBotsController removed

Given: `ChannelInstancesController` handles all channel instance management
When: `TelegramBotsController` is deleted
Then: no references to `TelegramBotsController` remain in the codebase

### Requirement: TelegramChatsAdminController

Removed: `src/Controller/Admin/TelegramChatsAdminController.php`
Reason: Replaced by `ChannelConversationsController` with channel-generic routes.

#### Scenario: TelegramChatsAdminController removed

Given: `ChannelConversationsController` handles conversation listing
When: `TelegramChatsAdminController` is deleted
Then: no references to `TelegramChatsAdminController` remain in the codebase

### Requirement: Telegram Admin Templates

Removed: `templates/admin/telegram/` (entire directory)
Reason: Replaced by `templates/admin/channels/` with channel-generic naming.

#### Scenario: Telegram admin templates removed

Given: channel-generic templates exist at `templates/admin/channels/`
When: the old Telegram templates are deleted
Then: `templates/admin/telegram/` directory no longer exists
And: no Twig `{% include %}` or `{% extends %}` references point to `admin/telegram/` paths
