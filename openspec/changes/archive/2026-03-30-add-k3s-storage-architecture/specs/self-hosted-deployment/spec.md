## ADDED Requirements

### Requirement: Self-Hosted k3s Deployments Must Define Backup Coverage
The platform SHALL provide a backup coverage model for self-hosted k3s deployments.

The backup coverage model SHALL classify each stateful service by whether it requires backup before
upgrade, can be rebuilt from authoritative sources, or is acceptable to lose.

#### Scenario: Operator prepares a self-hosted upgrade
- **WHEN** an operator prepares to upgrade a self-hosted k3s deployment
- **THEN** the runbook identifies which stateful services require backup before rollout
- **AND** the runbook identifies which services can be rebuilt instead of restored
- **AND** the runbook provides concrete backup commands for each service that requires backup

#### Scenario: Operator reviews backup coverage for all stateful services
- **WHEN** the operator reviews the backup coverage documentation
- **THEN** every stateful service in the deployment has an explicit backup classification
- **AND** the classification matches the durability tier defined in the k3s-storage spec

### Requirement: Self-Hosted k3s Deployments Must Define Restore Verification
The platform SHALL provide restore verification guidance for authoritative stateful services.

The restore verification guidance SHALL include concrete commands and expected outputs for each
service that supports restore.

#### Scenario: Operator restores PostgreSQL after failed rollout
- **WHEN** the operator restores PostgreSQL in a self-hosted k3s deployment
- **THEN** the runbook defines concrete verification checks for application health and data visibility
- **AND** the restore flow is treated as a first-class rollback path rather than an implicit assumption
- **AND** the runbook includes a post-restore checklist covering database connectivity, migration
  state, and application health endpoint

#### Scenario: Operator verifies platform health after restore
- **WHEN** the operator completes a PostgreSQL restore and restarts the platform
- **THEN** the core health endpoint returns success
- **AND** the admin interface is accessible
- **AND** previously created data (communities, agents, configurations) is visible

### Requirement: Self-Hosted k3s Deployments Must Document Rollback Strategy
The platform SHALL provide a documented rollback strategy for self-hosted k3s deployments that
covers both application rollback (Helm rollback) and data rollback (restore from backup).

#### Scenario: Operator rolls back a failed upgrade
- **WHEN** a Helm upgrade fails or produces unexpected behavior on a self-hosted k3s deployment
- **THEN** the runbook provides a step-by-step rollback procedure
- **AND** the procedure covers both `helm rollback` for application state and database restore if
  migrations were applied
- **AND** the procedure identifies the point-of-no-return for migrations that cannot be reversed
