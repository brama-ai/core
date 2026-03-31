## ADDED Requirements

### Requirement: Telegram Navigation from Admin

The admin sidebar SHALL include a "Telegram" navigation link that routes to the Telegram bot management page.

The link SHALL appear between the "Settings" link and the "Інструменти" section divider in the sidebar navigation.

The link SHALL use active state detection matching `current_route starts with 'admin_telegram'`.

#### Scenario: Admin sees Telegram link in sidebar

- **WHEN** an authenticated admin opens any `/admin/*` page
- **THEN** the left sidebar SHALL render a "Telegram" link with a paper-plane icon
- **AND** the link SHALL navigate to `/admin/telegram/bots`

#### Scenario: Telegram link is active on Telegram pages

- **WHEN** an admin is on any `/admin/telegram/*` page
- **THEN** the "Telegram" sidebar link SHALL have the `active` CSS class

#### Scenario: Telegram link is not active on other pages

- **WHEN** an admin is on a non-Telegram admin page (e.g., `/admin/dashboard`)
- **THEN** the "Telegram" sidebar link SHALL NOT have the `active` CSS class

### Requirement: Telegram Dashboard Widget

The admin dashboard SHALL include a Telegram status widget in the platform metrics section.

The widget SHALL display: total configured bots, enabled bots, total active chats, and a summary health indicator.

#### Scenario: Dashboard shows Telegram metrics

- **WHEN** an authenticated admin visits `/admin/dashboard`
- **THEN** the page SHALL display a Telegram status card in the metrics grid
- **AND** the card SHALL show total bots count, enabled bots count, and active chats count

#### Scenario: Dashboard shows Telegram widget with no bots

- **WHEN** no Telegram bots are configured
- **THEN** the Telegram widget SHALL display "0" for all counts
- **AND** SHALL show a link to add a bot

#### Scenario: Dashboard Telegram widget links to management

- **WHEN** an admin clicks the Telegram widget title or "Manage" link
- **THEN** the browser SHALL navigate to `/admin/telegram/bots`
