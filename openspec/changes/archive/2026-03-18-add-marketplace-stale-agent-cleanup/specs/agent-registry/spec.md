## ADDED Requirements

### Requirement: Stale Marketplace Agent Cleanup
The system SHALL automatically hard-delete agents from `agent_registry` when the agent was never installed (`installed_at IS NULL`) and has accumulated consecutive health-check failures equal to or exceeding a configurable stale threshold (default: 5).

The cleanup SHALL run at the end of each health-poll cycle and SHALL NOT affect agents that have ever been installed (`installed_at IS NOT NULL`), regardless of their current health status.

Each deletion SHALL be recorded in the `agent_registry_audit` table with action `stale_deleted`.

#### Scenario: Unreachable marketplace agent exceeds stale threshold
- **WHEN** an agent has `installed_at IS NULL` and `health_check_failures >= 5`
- **THEN** the agent row is deleted from `agent_registry`
- **AND** an audit entry with action `stale_deleted` is created

#### Scenario: Installed agent is not affected by stale cleanup
- **WHEN** an agent has `installed_at IS NOT NULL` and `health_check_failures >= 5`
- **THEN** the agent row is NOT deleted
- **AND** the agent remains marked as `unavailable`

#### Scenario: Marketplace agent below stale threshold is preserved
- **WHEN** an agent has `installed_at IS NULL` and `health_check_failures < 5`
- **THEN** the agent row is preserved in the marketplace
