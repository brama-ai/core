# agent-registry Specification

## Purpose
TBD - created by archiving change add-marketplace-stale-agent-cleanup. Update Purpose after archive.
## Requirements
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

### Requirement: Agent Card Schema
The platform SHALL validate agent metadata against the Agent Card JSON Schema (`config/agent-card.schema.json`). The schema uses official A2A terminology: `skills` (was `capabilities`), `skill_schemas` (was `capability_schemas`).

#### Scenario: Valid Agent Card with skills
- **WHEN** an agent provides an Agent Card with `name`, `version`, `description`, `permissions`, `commands`, `events`, `a2a_endpoint`, and `skills` fields
- **THEN** the Agent Card passes validation

#### Scenario: Legacy capabilities field rejected
- **WHEN** an agent provides an Agent Card with `capabilities` instead of `skills`
- **THEN** the schema validation rejects the field as unknown (additionalProperties: false)

### Requirement: Agent Card Fetcher
The platform SHALL fetch Agent Cards from registered agents using the `AgentCardFetcher` service (was `AgentManifestFetcher`).

#### Scenario: Fetch Agent Card from agent
- **WHEN** the platform discovers a new agent via Traefik
- **THEN** the `AgentCardFetcher` retrieves the Agent Card from `http://{hostname}/api/v1/manifest`

### Requirement: A2A Message Audit
The platform SHALL record all A2A message invocations in the `a2a_message_audit` table (was `agent_invocation_audit`) with a `skill` column (was `tool`).

#### Scenario: Audit records use skill terminology
- **WHEN** the platform invokes a skill on a remote agent
- **THEN** the audit record stores the skill name in the `skill` column of `a2a_message_audit`

