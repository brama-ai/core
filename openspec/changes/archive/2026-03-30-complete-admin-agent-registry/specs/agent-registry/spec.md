## ADDED Requirements

### Requirement: Admin Agent List View
The admin panel SHALL provide an agent management page at `/admin/agents` that displays all registered agents in a two-tab layout: **Installed** (agents with `installed_at IS NOT NULL`) and **Marketplace** (agents with `installed_at IS NULL`).

Each agent row SHALL display: agent name, version, description, status badge (enabled/disabled/not_installed), health badge (healthy/degraded/unavailable/error/unknown), and last updated timestamp.

The page SHALL include a "Discover Agents" button that triggers pull-based agent discovery (Traefik/Kubernetes) and refreshes the agent list.

#### Scenario: Admin views installed agents
- **WHEN** an admin navigates to `/admin/agents`
- **AND** switches to the Installed tab
- **THEN** the page displays all agents with `installed_at IS NOT NULL`
- **AND** each row shows name, version, status badge, health badge, and action buttons (enable/disable, settings, delete)

#### Scenario: Admin views marketplace agents
- **WHEN** an admin navigates to `/admin/agents`
- **AND** switches to the Marketplace tab
- **THEN** the page displays all agents with `installed_at IS NULL`
- **AND** each row shows name, version, description, health badge, and an Install button

#### Scenario: Admin triggers agent discovery
- **WHEN** an admin clicks the "Discover Agents" button
- **THEN** the platform runs pull-based discovery (Traefik or Kubernetes provider)
- **AND** fetches Agent Cards from discovered agents
- **AND** upserts discovered agents into the registry
- **AND** displays the count of discovered agents

### Requirement: Admin Agent Settings View
The admin panel SHALL provide an agent settings page at `/admin/agents/{name}/settings` that displays the agent's configuration form, Agent Card metadata, and an embedded admin iframe (if the agent exposes `admin_url`).

The configuration form SHALL include editable fields for `description` and `system_prompt`, with a Save button that persists changes via `PUT /api/v1/internal/agents/{name}/config`.

All UI strings on the settings page SHALL use translation keys via the `|trans` Twig filter for i18n consistency.

#### Scenario: Admin views agent settings
- **WHEN** an admin navigates to `/admin/agents/{name}/settings`
- **THEN** the page displays the agent's configuration form with description and system_prompt fields
- **AND** the page displays Agent Card metadata (version, description, commands, events, skills)

#### Scenario: Admin saves agent configuration
- **WHEN** an admin modifies the description or system_prompt fields
- **AND** clicks Save
- **THEN** the configuration is persisted to the `agent_registry.config` JSONB column
- **AND** the saved values are visible after page reload

#### Scenario: Agent with admin_url shows embedded iframe
- **WHEN** an agent's manifest includes an `admin_url` field
- **AND** an admin navigates to that agent's settings page
- **THEN** the page displays an embedded iframe pointing to the agent's admin URL

### Requirement: Admin Agent Lifecycle Actions
The admin panel SHALL support the following agent lifecycle actions via the UI, each requiring `ROLE_ADMIN` authorization:

- **Install**: Move a marketplace agent to installed state by provisioning storage and marking `installed_at`
- **Enable**: Activate an installed agent for event routing and command handling
- **Disable**: Deactivate an installed agent (stops receiving events and commands)
- **Delete (Uninstall)**: Deprovision storage and clear `installed_at`, returning the agent to marketplace state

Each action SHALL be recorded in the `agent_registry_audit` table.

#### Scenario: Admin installs a marketplace agent
- **WHEN** an admin clicks Install on a marketplace agent
- **THEN** the platform provisions required storage (Postgres/Redis/OpenSearch per manifest)
- **AND** sets `installed_at` to the current timestamp
- **AND** the agent moves from the Marketplace tab to the Installed tab
- **AND** an audit entry with action `installed` is created

#### Scenario: Admin enables an installed agent
- **WHEN** an admin clicks Enable on a disabled installed agent
- **THEN** the agent's `enabled` flag is set to `true`
- **AND** the agent begins receiving events and commands
- **AND** an audit entry with action `enabled` is created

#### Scenario: Admin disables an enabled agent
- **WHEN** an admin clicks Disable on an enabled agent
- **THEN** the agent's `enabled` flag is set to `false`
- **AND** the agent stops receiving events and commands
- **AND** an audit entry with action `disabled` is created

#### Scenario: Admin deletes (uninstalls) an installed agent
- **WHEN** an admin clicks Delete on a disabled installed agent
- **THEN** the platform deprovisions the agent's storage
- **AND** clears `installed_at` (agent returns to marketplace)
- **AND** an audit entry with action `uninstalled` is created
- **AND** the agent row is NOT deleted from the registry

### Requirement: Agent Convention Violations Display
The admin panel SHALL display convention violation details for agents that fail convention verification during discovery.

Violations SHALL be stored in the `agent_registry.violations` JSONB column and displayed via a modal dialog accessible by clicking the agent's degraded health badge.

#### Scenario: Agent with violations shows degraded badge
- **WHEN** an agent fails convention verification during discovery
- **THEN** the agent's violations are stored in the `violations` column
- **AND** the agent list displays a degraded badge for that agent

#### Scenario: Admin views violation details
- **WHEN** an admin clicks on a degraded badge for an agent with violations
- **THEN** a modal dialog displays the list of convention violation messages
