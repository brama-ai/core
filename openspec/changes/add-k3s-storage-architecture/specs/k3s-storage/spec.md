# k3s Storage Specification

## ADDED Requirements

### Requirement: Stateful Services Must Declare Durability Tier
The platform SHALL classify each stateful k3s dependency by durability and recovery criticality.

The classification SHALL use three tiers:

- **Tier A (authoritative, must back up):** PostgreSQL (core and agent databases)
- **Tier B (recommended to persist, rebuildable from source):** OpenSearch indices, Langfuse
  observability data
- **Tier C (persistence optional or environment-dependent):** Redis cache/state, RabbitMQ queues,
  local container registry

The initial matrix SHALL cover at least PostgreSQL, Redis, RabbitMQ, OpenSearch, Langfuse
dependencies, and the local container registry.

#### Scenario: Operator reviews k3s storage architecture
- **WHEN** an operator reads the platform k3s storage architecture documentation
- **THEN** each stateful service is assigned an explicit durability tier
- **AND** the documentation states whether its data is authoritative, recommended to persist, or
  acceptable to rebuild

#### Scenario: New stateful service is added to the platform
- **WHEN** a new stateful dependency is introduced to the k3s deployment
- **THEN** the service MUST be classified into a durability tier before deployment
- **AND** its PVC, backup, and restore expectations MUST be documented

### Requirement: PostgreSQL Must Have Mandatory Backup And Restore Guidance
The platform SHALL treat PostgreSQL as the primary durable system of record in k3s deployments.

The backup and restore guidance SHALL include:

- a pre-upgrade backup procedure using `pg_dump` or equivalent
- a documented restore procedure with post-restore verification steps
- explicit instructions for verifying application health after restore

#### Scenario: Operator prepares a k3s upgrade
- **WHEN** an operator follows the upgrade path for a k3s deployment
- **THEN** the documented procedure requires a PostgreSQL backup before rollout
- **AND** the restore procedure includes post-restore verification steps

#### Scenario: Operator restores PostgreSQL from backup
- **WHEN** the operator restores a PostgreSQL backup in a k3s deployment
- **THEN** the runbook provides concrete verification commands to confirm data integrity
- **AND** the verification includes checking that the core application health endpoint returns success
- **AND** the verification includes confirming that key database tables contain expected data

### Requirement: PVC Strategy Must Be Explicit For Persistent Services
The platform SHALL document which services require PVCs, which storage class they use, and the
baseline size expectations for single-node k3s.

The baseline PVC sizing for single-node k3s SHALL be:

- PostgreSQL: 2 Gi (local dev), 10 Gi (production)
- Redis: 1 Gi (local dev), 2 Gi (production)
- RabbitMQ: 1 Gi (local dev), 2 Gi (production)
- OpenSearch: 8 Gi (all environments where enabled)

#### Scenario: Operator provisions stateful services on local k3s
- **WHEN** the operator renders or applies the k3s storage-aware deployment assets for local dev
- **THEN** the services marked as persistent have explicit PVC expectations
- **AND** the PVCs use the `local-path` storage class
- **AND** the documentation states the intended size baseline for each service

#### Scenario: Operator provisions stateful services on Hetzner k3s
- **WHEN** the operator renders or applies the k3s storage-aware deployment assets for Hetzner
- **THEN** the services marked as persistent have explicit PVC expectations
- **AND** the PVCs use the `local-path` storage class (single-node default)
- **AND** the documentation states the intended size baseline for each service

### Requirement: Non-Authoritative State Must Have Loss Expectations
The platform SHALL document the loss and rebuild expectations for Redis, RabbitMQ, OpenSearch, and
other non-primary stateful services.

#### Scenario: Redis state is lost in a single-node k3s deployment
- **WHEN** Redis state is lost in a single-node k3s deployment
- **THEN** the operator documentation states that Redis is used for cache and session state
- **AND** the documentation confirms that Redis data loss does not cause permanent data loss
- **AND** the documentation identifies the operational impact as temporary cache miss or session reset

#### Scenario: RabbitMQ state is lost in a single-node k3s deployment
- **WHEN** RabbitMQ state is lost in a single-node k3s deployment
- **THEN** the operator documentation states whether queues are durable or transient
- **AND** the documentation identifies the acceptable queue loss and replay model
- **AND** the documentation confirms whether in-flight messages can be safely re-enqueued

#### Scenario: OpenSearch state is lost in a single-node k3s deployment
- **WHEN** OpenSearch indices are lost in a single-node k3s deployment
- **THEN** the operator documentation states whether indices can be rebuilt from authoritative sources
- **AND** the documentation identifies the operational impact of index loss
- **AND** the documentation provides guidance for re-indexing from PostgreSQL or other primary sources

### Requirement: Langfuse Observability Data Must Have Explicit Persistence Policy
The platform SHALL document whether Langfuse observability data (traces, scores, generations) is
operationally critical or best-effort in each deployment environment.

#### Scenario: Operator evaluates Langfuse data retention for Hetzner k3s
- **WHEN** the operator reviews the storage architecture for a Hetzner k3s deployment
- **THEN** the documentation states whether Langfuse trace data must survive upgrades
- **AND** the documentation classifies Langfuse as Tier B or Tier C for that environment
- **AND** the documentation provides guidance for Langfuse database backup if classified as Tier B

#### Scenario: Langfuse data is lost during upgrade
- **WHEN** Langfuse observability data is lost during a k3s upgrade
- **THEN** the operator documentation states the expected impact
- **AND** the documentation confirms whether the platform remains fully functional without historical traces

### Requirement: Storage Architecture Must Support Future Externalization
The platform k3s storage architecture SHALL NOT hardcode assumptions that prevent moving stateful
services out of the cluster in future phases.

The Helm chart values SHALL support both bundled in-cluster services and externalized dependencies
through the `externalDependencies` configuration pattern.

#### Scenario: Operator switches PostgreSQL from in-cluster to external managed service
- **WHEN** the operator sets `externalDependencies.postgres.external: true` and provides host/port
- **THEN** the bundled PostgreSQL sub-chart is disabled
- **AND** the core application connects to the external PostgreSQL instance
- **AND** no in-cluster PVC is created for PostgreSQL

#### Scenario: Operator switches Redis from in-cluster to external managed service
- **WHEN** the operator sets `externalDependencies.redis.external: true` and provides host/port
- **THEN** the bundled Redis sub-chart is disabled
- **AND** the core application connects to the external Redis instance

### Requirement: Persistent State Must Survive Pod Restart
The platform SHALL verify that data for services classified as Tier A or Tier B survives pod
restarts in k3s deployments.

#### Scenario: PostgreSQL data survives pod restart
- **WHEN** the PostgreSQL pod is deleted and rescheduled in a k3s deployment
- **THEN** the PVC remains bound
- **AND** all previously written data is accessible after the pod restarts
- **AND** the core application health endpoint returns success after reconnection

#### Scenario: OpenSearch data survives pod restart when persistence is enabled
- **WHEN** the OpenSearch pod is deleted and rescheduled with persistence enabled
- **THEN** the PVC remains bound
- **AND** previously indexed data is accessible after the pod restarts
