## ADDED Requirements

### Requirement: Agents Page Has Installed and Marketplace Tabs
The admin agents page SHALL present two sections: installed agents and marketplace agents.

#### Scenario: Agent is installed
- **WHEN** an agent has `installed_at` set
- **THEN** it appears in the `Встановлені` tab

#### Scenario: Agent is discoverable but not installed
- **WHEN** an agent is present in registry with `installed_at = null`
- **THEN** it appears in the `Маркетплейс` tab

#### Scenario: Uninstalled but discoverable agent returns to marketplace
- **WHEN** admin uninstalls an agent and the agent is still discoverable in Docker
- **THEN** the row remains visible in `Маркетплейс`

### Requirement: Lifecycle Action Order in Admin UI
The admin agents UI SHALL enforce action availability order: install -> enable -> settings.

#### Scenario: Marketplace row shows install only
- **WHEN** an agent is not installed
- **THEN** the row shows `Встановити`
- **AND** does not show `Увімкнути` or `Налаштування`

#### Scenario: Installed disabled row shows enable
- **WHEN** an agent is installed and disabled
- **THEN** the row shows `Увімкнути`
- **AND** hides `Налаштування`

#### Scenario: Installed enabled row shows settings
- **WHEN** an agent is installed and enabled
- **THEN** the row shows `Налаштування`

### Requirement: Action Error Feedback
Admin UI SHALL display backend lifecycle error details for failed actions.

#### Scenario: Backend returns 500 with JSON error
- **WHEN** install/enable/uninstall API returns non-2xx with `{ "error": "..." }`
- **THEN** UI alert includes the backend message instead of only HTTP code
