# Design: k3s Storage Architecture

## Context

The platform's current k3s plans identify the required infrastructure services but do not yet define
which state must persist, how it should be stored, or how operators recover from failures. This
change focuses on the storage layer for the current single-node k3s target, not on multi-node HA.

The target environments are:

- local Rancher Desktop k3s for validation
- single-node Hetzner k3s for self-hosted deployment

The Helm chart at `deploy/charts/brama/` already has persistence configuration for PostgreSQL,
Redis, RabbitMQ, and OpenSearch via Bitnami sub-charts. The `externalDependencies` pattern already
supports switching from bundled to external managed services. This change formalizes the storage
expectations and adds the missing operational guidance.

## Goals

- Define storage expectations for all stateful services in the k3s topology
- Prevent accidental coupling between "must survive node restart" and "can be rebuilt"
- Make backup/restore responsibilities explicit before the first real rollout
- Keep the first implementation realistic for single-node k3s and local-path storage
- Ensure storage decisions do not prevent future migration to managed services

## Non-Goals

- Multi-node replication or HA storage
- Cross-region backup strategy
- Full disaster recovery automation
- Managed cloud databases or object storage migration
- Automated backup scheduling (first iteration is manual operator procedures)

## Decisions

### 1. Service data is classified by recovery criticality

Stateful services are split into three tiers:

- **Tier A: must be backed up and restored intentionally**
  - Postgres (core + agent databases)
- **Tier B: should persist locally, but can be rebuilt from source state if needed**
  - OpenSearch indices (rebuildable from Postgres via re-indexing)
  - Langfuse stateful dependencies where local history matters
- **Tier C: persistence is optional or environment-dependent**
  - Redis cache/state (session and cache data, not authoritative)
  - RabbitMQ queues (transient message delivery, replay from source)
  - local container registry (images can be rebuilt)

This avoids treating every stateful pod as equally critical.

### 2. Postgres is the primary durable system of record

For the current platform architecture, Postgres remains the source of truth for platform and
agent-owned relational data. k3s storage decisions must optimize first for:

- safe PVC attachment
- predictable restart behavior
- pre-upgrade backups via `pg_dump`
- documented restore verification with concrete health checks

### 3. Single-node k3s uses simple persistence, not pseudo-HA

For local and Hetzner single-node k3s, the default baseline is a `local-path`-backed PVC strategy.
The goal is operational clarity, not introducing a fake HA story on one node.

This means the architecture must document:

- which PVCs are required
- expected size baselines per environment
- what survives pod restarts
- what does not survive node loss unless restored from backup

### 4. PVC sizing baselines are environment-specific

Based on the existing Helm chart values:

| Service     | Local Dev | Production | Notes |
|-------------|-----------|------------|-------|
| PostgreSQL  | 2 Gi      | 10 Gi      | Tier A, mandatory PVC |
| Redis       | 1 Gi      | 2 Gi       | Tier C, PVC optional but enabled by default |
| RabbitMQ    | 1 Gi      | 2 Gi       | Tier C, PVC optional but enabled by default |
| OpenSearch  | 8 Gi      | 8 Gi       | Tier B, PVC required when enabled |

All environments use `local-path` storage class for single-node k3s.

### 5. Backup policy must match service semantics

Each stateful service must have an explicit operator expectation:

- Postgres: mandatory pre-upgrade backup via `pg_dump`, documented restore path with verification
- OpenSearch: documented rebuild expectation via re-indexing from Postgres; snapshot optional
- Redis: explicit statement that data is cache/session only, loss causes temporary degradation
- RabbitMQ: explicit statement that queues are transient, in-flight messages may be lost on restart
- Langfuse: environment-dependent; document whether traces are required to survive upgrades

### 6. Externalization remains an allowed future path

The architecture must support both bundled in-cluster services and externalized dependencies later.
The existing `externalDependencies` pattern in `values.yaml` already supports this:

```yaml
externalDependencies:
  postgres:
    external: true
    host: postgres.example.com
```

Current k3s work must not hardcode assumptions that prevent moving Postgres, Redis, or OpenSearch
out of the cluster in future phases.

### 7. Storage verification gates core deployment

Before deploying core and agent workloads, the operator must verify:

- All Tier A PVCs are bound
- All Tier A services pass health checks
- At least one pod-restart test confirms data survival for PostgreSQL

This prevents deploying application workloads against broken or unverified storage.

## Target Service Matrix

### Postgres

- Tier: A (authoritative)
- Persistence: required
- PVC: required
- Backup: required (pre-upgrade `pg_dump`)
- Restore drill: required (with post-restore verification)
- Default mode: in-cluster PVC-backed database for local and Hetzner single-node

### Redis

- Tier: C (optional persistence)
- Persistence: environment-dependent (enabled by default in Helm chart)
- PVC: optional, but decision must be explicit
- Backup: not required for cache-only usage
- Restore drill: not required unless Redis stores critical workflow state

### RabbitMQ

- Tier: C (optional persistence)
- Persistence: environment-dependent (enabled by default in Helm chart)
- PVC: optional, but queue durability expectations must be explicit
- Backup: not primary; operator guidance must explain acceptable queue loss/replay model

### OpenSearch

- Tier: B (rebuildable)
- Persistence: recommended
- PVC: required if logs/search data must survive pod restart
- Backup: optional; rebuild expectation via re-indexing from Postgres must be documented
- Restore drill: optional in first iteration, but data-loss expectation must be explicit

### Langfuse Dependencies

- Tier: B or C (environment-dependent, to be classified per deployment)
- Persistence: recommended when observability history matters
- Backup: environment-dependent
- Must state whether observability data is operationally critical or best-effort

### Local Container Registry

- Tier: C (disposable)
- Persistence: optional
- Backup: not required (images can be rebuilt)

## Implementation Shape

The first implementation should add:

- comments in Helm values files documenting durability tier and backup expectations per service
- verification steps in deployment runbooks for PVC binding and pod-restart survival
- PostgreSQL backup and restore runbook with concrete commands
- OpenSearch rebuild/re-index guidance
- Redis and RabbitMQ loss impact documentation
- rollback strategy covering both Helm rollback and database restore
- storage architecture overview documentation under `docs/`

## Risks

- Treating Redis or RabbitMQ as durable without a recovery story creates false safety
- Treating OpenSearch as disposable without stating that operator expectation creates silent data loss
- Using local-path storage without restore drills may create a misleading production narrative
- Langfuse classification as Tier B without backup automation may lead to unexpected data loss

## Open Questions

- Should Langfuse be classified as Tier B or Tier C for the first Hetzner rollout?
- Does any current workflow require Redis persistence beyond cache/session use?
- Do we want OpenSearch snapshot automation in the first iteration, or only manual operator guidance?
