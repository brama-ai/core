## ADDED Requirements

### Requirement: Single-VPS Self-Hosted Deployment

The platform SHALL support a fully self-hosted deployment on a single Hetzner VPS running k3s,
with no external service dependencies beyond the VPS itself and DNS.

#### Scenario: Complete stack on one VPS
- **WHEN** the operator provisions a Hetzner CX32 VPS (4 vCPU / 8 GB RAM) with Ubuntu
- **AND** follows the k3s installation and Helm deployment procedure
- **THEN** all platform services (core, scheduler, agents, databases, message broker, search, observability) SHALL run on that single node
- **AND** no external managed services (RDS, ElastiCache, managed K8s) SHALL be required

#### Scenario: Maintenance window migration
- **WHEN** the operator migrates from Docker Compose to k3s on the same VPS
- **THEN** the migration SHALL be completable within a planned maintenance window of 30–60 minutes
- **AND** existing PostgreSQL data SHALL be preserved via pg_dumpall backup and restore

### Requirement: Operator-Managed Infrastructure

The self-hosted deployment SHALL be fully operator-managed with standard Kubernetes tooling.

#### Scenario: Helm-based lifecycle management
- **WHEN** the operator needs to deploy, upgrade, or rollback the platform
- **THEN** all operations SHALL be performed via `helm upgrade --install` and `helm rollback`
- **AND** the operator SHALL NOT need custom scripts beyond the provided `build-and-push.sh`

#### Scenario: Secret management
- **WHEN** the operator creates Kubernetes secrets for the platform
- **THEN** each service SHALL reference its secrets via `secretRef` in the Helm values
- **AND** secrets SHALL NOT be stored in version control or Helm values files

### Requirement: Rollback and Recovery

The self-hosted deployment SHALL support rollback to the previous working state.

#### Scenario: Helm rollback on failed upgrade
- **WHEN** a Helm upgrade fails (pods not reaching Ready state within timeout)
- **THEN** the operator SHALL be able to run `helm rollback acp <revision> -n acp`
- **AND** the previous working version SHALL be restored within 5 minutes

#### Scenario: Full rollback to Docker Compose
- **WHEN** the k3s deployment is unrecoverable during initial migration
- **THEN** the operator SHALL be able to stop k3s and restart Docker Compose
- **AND** PostgreSQL data SHALL remain intact in Docker volumes (not removed during migration)
