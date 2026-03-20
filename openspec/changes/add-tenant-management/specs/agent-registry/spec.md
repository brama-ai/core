## MODIFIED Requirements

### Requirement: Stale Marketplace Agent Cleanup
The system SHALL automatically hard-delete agents from `agent_registry` when the agent was never installed (`installed_at IS NULL`) and has accumulated consecutive health-check failures equal to or exceeding a configurable stale threshold (default: 5).

The cleanup SHALL run at the end of each health-poll cycle and SHALL NOT affect agents that have ever been installed (`installed_at IS NOT NULL`), regardless of their current health status.

Each deletion SHALL be recorded in the `agent_registry_audit` table with action `stale_deleted` and the associated `tenant_id`.

#### Scenario: Unreachable marketplace agent exceeds stale threshold
- **WHEN** an agent has `installed_at IS NULL` and `health_check_failures >= 5`
- **THEN** the agent row is deleted from `agent_registry`
- **AND** an audit entry with action `stale_deleted` is created with the agent's `tenant_id`

#### Scenario: Installed agent is not affected by stale cleanup
- **WHEN** an agent has `installed_at IS NOT NULL` and `health_check_failures >= 5`
- **THEN** the agent row is NOT deleted
- **AND** the agent remains marked as `unavailable`

#### Scenario: Marketplace agent below stale threshold is preserved
- **WHEN** an agent has `installed_at IS NULL` and `health_check_failures < 5`
- **THEN** the agent row is preserved in the marketplace

### Requirement: A2A Message Audit
The platform SHALL record all A2A message invocations in the `a2a_message_audit` table with a `skill` column and a `tenant_id` column referencing the tenant context in which the invocation occurred.

#### Scenario: Audit records use skill terminology
- **WHEN** the platform invokes a skill on a remote agent
- **THEN** the audit record stores the skill name in the `skill` column of `a2a_message_audit`

#### Scenario: Audit records include tenant context
- **WHEN** the platform invokes a skill within a tenant context
- **THEN** the audit record includes the `tenant_id` of the active tenant

## ADDED Requirements

### Requirement: Agent Registry Tenant Scoping
Every agent in `agent_registry` SHALL be associated with a `tenant_id`. The unique constraint on agent name SHALL be scoped to the tenant: `UNIQUE(name, tenant_id)`.

#### Scenario: Same agent name in different tenants
- **WHEN** Tenant A installs agent "news-digest" and Tenant B installs agent "news-digest"
- **THEN** both installations succeed as separate rows with different `tenant_id` values

#### Scenario: Duplicate agent name within same tenant
- **WHEN** Tenant A attempts to install a second agent named "news-digest"
- **THEN** the unique constraint rejects the duplicate
