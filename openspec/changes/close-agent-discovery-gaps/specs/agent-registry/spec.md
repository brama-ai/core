## MODIFIED Requirements

### Requirement: Agent Card Fetcher
The platform SHALL fetch Agent Cards from registered agents using the `AgentCardFetcher` service
(was `AgentManifestFetcher`). Discovery SHALL run both on-demand (via admin panel or CLI) and
automatically via the platform scheduler at a 60-second interval.

The `AgentCardFetcher` SHALL be invoked by the `agent:discovery` command, which is registered
as a scheduled task in the platform's scheduler infrastructure. When triggered by the scheduler,
the command SHALL discover agents from all configured providers (Traefik, Kubernetes) and upsert
the registry with fetched Agent Cards.

#### Scenario: Fetch Agent Card from agent
- **WHEN** the platform discovers a new agent via Traefik or Kubernetes provider
- **THEN** the `AgentCardFetcher` retrieves the Agent Card from `http://{hostname}:{port}/api/v1/manifest`

#### Scenario: Scheduled discovery fetches cards automatically
- **WHEN** the platform scheduler triggers `agent:discovery` on its 60-second cycle
- **THEN** the discovery command fetches Agent Cards for all discovered agents and upserts the registry

#### Scenario: On-demand discovery via admin panel
- **WHEN** an admin clicks "Discover Agents" in the admin panel
- **THEN** the `agent:discovery` command runs immediately and the agent list refreshes

### Requirement: Admin Agent List View
The admin panel SHALL provide an agent management page at `/admin/agents` that displays all registered agents in a two-tab layout: **Installed** (agents with `installed_at IS NOT NULL`) and **Marketplace** (agents with `installed_at IS NULL`).

Each agent row SHALL display: agent name, version, description, status badge (enabled/disabled/not_installed), health badge (healthy/degraded/unavailable/error/unknown), and last updated timestamp.

The page SHALL include a "Discover Agents" button that triggers pull-based agent discovery (Traefik/Kubernetes) and refreshes the agent list. The page SHALL also include an "Add by URL" button that opens a placeholder modal explaining the feature is in development.

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

#### Scenario: Admin sees Add by URL button
- **WHEN** an admin navigates to `/admin/agents`
- **THEN** an "Add by URL" button is visible alongside the "Discover Agents" button
- **AND** clicking it opens a modal with a development notice and manual agent onboarding instructions
