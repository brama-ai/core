## MODIFIED Requirements

### Requirement: Infrastructure Services Must Be Verifiable Before Core Starts
Infrastructure services SHALL be deployable and verifiable as an independent layer.

Storage verification SHALL be part of this layer for services that declare persistent state.
The operator MUST be able to confirm that PVCs are bound and that stateful services survive pod
restarts before proceeding to core and agent deployment.

#### Scenario: Applying infrastructure manifests
- **WHEN** the operator deploys PostgreSQL, Redis, RabbitMQ, and OpenSearch
- **THEN** each service must reach a healthy running state or provide a diagnosable failure state
- **AND** the verification steps must include concrete `kubectl` commands to inspect the result

#### Scenario: Verifying persistent infrastructure state
- **WHEN** a stateful service is marked as persistent in the k3s deployment model
- **THEN** the operator verification steps confirm that its PVC is bound
- **AND** the documented checks confirm the service survives a pod restart without unintended data loss

#### Scenario: Storage verification blocks core rollout
- **WHEN** a Tier A stateful service (PostgreSQL) has an unbound PVC or fails health checks
- **THEN** the deployment runbook instructs the operator to resolve storage issues before deploying core
- **AND** the operator can diagnose the failure using documented `kubectl` commands
