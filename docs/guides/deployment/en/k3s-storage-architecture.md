# k3s Storage Architecture

## Overview

This document describes the storage architecture for stateful platform services deployed on
single-node k3s (local Rancher Desktop or Hetzner VPS). It defines durability tiers, PVC strategy,
backup/restore responsibilities, and loss expectations for each stateful service.

> **Scope**: Single-node k3s deployments only. Multi-node HA storage, cross-region backup, and
> managed cloud database migration are out of scope for this document.

## Durability Tiers

All stateful services are classified into one of three durability tiers:

| Tier | Name | Description |
|------|------|-------------|
| **A** | Authoritative | Must be backed up before upgrade. Data loss is permanent and unrecoverable without a restore. |
| **B** | Rebuildable | Should persist locally, but can be rebuilt from authoritative sources if lost. |
| **C** | Optional | Persistence is optional or environment-dependent. Loss causes temporary degradation only. |

## Stateful Service Matrix

| Service | Tier | Data Type | Backup Required | Rebuild Path | Loss Impact |
|---------|------|-----------|-----------------|--------------|-------------|
| **PostgreSQL** | A | Platform and agent relational state | Yes — `pg_dump` before upgrade | No rebuild path; restore from backup | Permanent data loss; platform cannot function |
| **OpenSearch** | B | Search indices, structured logs | Optional (snapshot) | Re-index from PostgreSQL | Search/log data unavailable until re-indexed |
| **Langfuse** | B/C | Observability traces, scores, generations | Environment-dependent | Not rebuildable | Historical traces lost; platform remains functional |
| **Redis** | C | Cache entries, session tokens | No | Automatic (cache miss) | Temporary cache miss or session reset |
| **RabbitMQ** | C | Message queues for agent task delivery | No | Producers re-enqueue | In-flight messages may be lost on restart |
| **Local Registry** | C | Container images | No | Rebuild from source | Images must be rebuilt or re-pulled |

## StorageClass Strategy

All single-node k3s environments (local Rancher Desktop and Hetzner single-node) use the
`local-path` storage class. This is the k3s default and provides node-local persistent volumes.

**Implications of `local-path`:**
- Data is stored on the node's local filesystem
- Data survives pod restarts (PVC remains bound)
- Data does **not** survive node loss unless restored from backup
- No replication or HA — this is intentional for single-node deployments

For production with managed services, use `externalDependencies` to route to external PostgreSQL
and Redis, eliminating the need for in-cluster PVCs for Tier A and C services.

## PVC Size Baselines

| Service | Local Dev | Local Infra | Production (in-cluster) | Tier |
|---------|-----------|-------------|-------------------------|------|
| PostgreSQL | 2 Gi | 5 Gi | 10 Gi | A |
| Redis | 1 Gi | 1 Gi | 2 Gi | C |
| RabbitMQ | 1 Gi | 2 Gi | 2 Gi | C |
| OpenSearch | — (disabled) | 8 Gi | 8 Gi | B |

These baselines are set in the Helm values files:
- `deploy/charts/brama/values-k3s-dev.yaml` — local dev
- `deploy/charts/brama/values-k3s-local-infra.yaml` — local infra validation
- `deploy/charts/brama/values.yaml` — production defaults
- `deploy/charts/brama/values-prod.example.yaml` — production example with externalization

## Data Classification

### PostgreSQL — Tier A (Authoritative)

PostgreSQL is the **primary durable system of record** for the platform. It holds:
- Platform relational data (users, communities, agents, configurations)
- Agent-owned relational data (knowledge base entries, news items, etc.)
- Migration state (Doctrine migrations table)

**What this means for operators:**
- A PostgreSQL backup is **required** before every upgrade
- Data loss is **permanent** without a restore from backup
- The platform cannot function without a healthy PostgreSQL instance
- See [PostgreSQL Backup and Restore Runbook](./k3s-storage-backup.md) for procedures

### OpenSearch — Tier B (Rebuildable)

OpenSearch holds search indices and structured logs. These are derived from PostgreSQL data and
can be rebuilt via re-indexing if lost.

**What this means for operators:**
- OpenSearch data loss causes search and log features to be unavailable temporarily
- The platform remains functional for all non-search operations
- Re-indexing from PostgreSQL restores full functionality
- Snapshot backups are optional but recommended for large index volumes

**Re-indexing after data loss:**
```bash
# Re-index knowledge base entries from PostgreSQL
kubectl exec -n brama deploy/brama-core -- \
  php bin/console knowledge:reindex --no-interaction

# Verify index health
kubectl exec -n brama deploy/brama-core -- \
  curl -sf http://brama-opensearch:9200/_cluster/health
```

### Langfuse — Tier B or C (Environment-Dependent)

Langfuse stores observability data: LLM traces, scores, and generation metadata.

**Classification by environment:**

| Environment | Tier | Rationale |
|-------------|------|-----------|
| Local dev | C | Traces are best-effort; loss is acceptable |
| Hetzner production | B | Historical traces have operational value; persist when possible |

**What this means for operators:**
- Langfuse data loss does **not** affect platform functionality
- Historical traces and scores are lost if Langfuse storage is lost
- For Hetzner production: treat Langfuse as Tier B and include its database in backup coverage
- Langfuse uses its own PostgreSQL database; include it in `pg_dump` coverage if classified as Tier B

### Redis — Tier C (Cache/Session State)

Redis holds cache entries and session tokens. It is **not** the source of truth for any data.

**What this means for operators:**
- Redis data loss causes **temporary** degradation only
- Users may experience cache misses or need to re-login after session reset
- No backup is required for cache-only usage
- The application reconnects automatically after Redis restart

**Loss impact:**
- Cache miss: next request rebuilds the cache entry from PostgreSQL
- Session reset: users are redirected to login; no data is lost

### RabbitMQ — Tier C (Transient Message Delivery)

RabbitMQ delivers messages between platform components and agents. Queues are transient.

**What this means for operators:**
- In-flight messages may be lost on pod restart or node loss
- Producers re-enqueue messages when they detect delivery failure
- No backup is required
- The application reconnects automatically after RabbitMQ restart

**Loss impact:**
- In-flight agent tasks may need to be re-submitted
- No permanent data loss (task state is tracked in PostgreSQL)

## Externalization Path

The Helm chart supports switching from bundled in-cluster services to external managed services
without changing application code. This ensures storage decisions do not prevent future migration.

```yaml
# values-prod.yaml — switch PostgreSQL to external managed service
externalDependencies:
  postgres:
    external: true
    host: postgres.example.com
    port: 5432
    database: ai_community_platform

postgresql:
  enabled: false  # disable bundled sub-chart
```

The same pattern applies to Redis (`externalDependencies.redis`) and OpenSearch
(`externalDependencies.opensearch`).

## Storage Verification Gate

Before deploying core and agent workloads, the operator MUST verify that storage is healthy.
See [Storage Verification Procedures](./k3s-storage-verification.md) for the full checklist.

**Minimum gate before core rollout:**
1. All Tier A PVCs (PostgreSQL) are `Bound`
2. PostgreSQL pod is `Running` and passes health check
3. At least one pod-restart test confirms PostgreSQL data survives

## Related Documents

- [PostgreSQL Backup and Restore Runbook](./k3s-storage-backup.md)
- [Storage Verification Procedures](./k3s-storage-verification.md)
- [Kubernetes Installation Guide](./kubernetes-install.md)
- [Kubernetes Upgrade Runbook](./kubernetes-upgrade.md)
