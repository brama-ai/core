# k3s Storage Backup and Restore Runbook

## Overview

This runbook provides concrete backup and restore procedures for stateful services in k3s
deployments. It covers PostgreSQL (Tier A — authoritative), OpenSearch (Tier B — rebuildable),
Redis (Tier C — no backup required), and RabbitMQ (Tier C — no backup required).

> **When to use this runbook:**
> - Before every platform upgrade (PostgreSQL backup is mandatory)
> - After a failed upgrade that requires rollback with data restore
> - After accidental data loss or node failure

## PostgreSQL Backup

PostgreSQL is the **primary durable system of record** (Tier A). A backup is **required** before
every upgrade.

### Pre-Upgrade Backup Procedure

#### 1. Identify the PostgreSQL pod

```bash
kubectl get pods -n brama -l app.kubernetes.io/name=postgresql
# Example output:
# NAME                    READY   STATUS    RESTARTS   AGE
# brama-postgresql-0      1/1     Running   0          2d
```

#### 2. Create a full database dump

```bash
# Dump all platform databases to a local file
kubectl exec -n brama brama-postgresql-0 -- \
  pg_dumpall -U postgres > brama-backup-$(date +%Y%m%d-%H%M%S).sql

# Verify the dump file is non-empty
ls -lh brama-backup-*.sql
```

#### 3. Dump individual databases (recommended for targeted restore)

```bash
# Core platform database
kubectl exec -n brama brama-postgresql-0 -- \
  pg_dump -U postgres ai_community_platform \
  > brama-core-$(date +%Y%m%d-%H%M%S).sql

# Knowledge agent database (if enabled)
kubectl exec -n brama brama-postgresql-0 -- \
  pg_dump -U postgres knowledge_agent \
  > brama-knowledge-$(date +%Y%m%d-%H%M%S).sql

# Langfuse database (if Langfuse is classified as Tier B for this environment)
kubectl exec -n brama brama-postgresql-0 -- \
  pg_dump -U postgres langfuse \
  > brama-langfuse-$(date +%Y%m%d-%H%M%S).sql
```

#### 4. Copy backup files off the cluster

```bash
# Copy to local machine from the pod's filesystem (if using pg_dump to file)
kubectl cp brama/brama-postgresql-0:/tmp/backup.sql ./brama-backup-$(date +%Y%m%d).sql

# Or use the stdout approach above and redirect to a local file directly
```

#### 5. Verify backup integrity

```bash
# Check the dump file is valid SQL
head -20 brama-core-*.sql
# Should start with: -- PostgreSQL database dump

# Check file size is reasonable (not 0 bytes)
wc -l brama-core-*.sql
```

### Backup Checklist

Before proceeding with an upgrade, confirm:

- [ ] `pg_dump` completed without errors
- [ ] Backup file is non-empty and contains valid SQL
- [ ] Backup file is stored outside the cluster (local machine, S3, or remote storage)
- [ ] Backup timestamp is recorded (for rollback reference)

---

## PostgreSQL Restore

Use this procedure when rolling back a failed upgrade or recovering from data loss.

### Restore Procedure

#### 1. Stop application workloads to prevent writes during restore

```bash
kubectl scale deploy/brama-core -n brama --replicas=0
kubectl scale deploy/brama-core-scheduler -n brama --replicas=0

# Scale down agents if enabled
kubectl scale deploy/brama-agent-knowledge -n brama --replicas=0 2>/dev/null || true
kubectl scale deploy/brama-agent-hello -n brama --replicas=0 2>/dev/null || true
```

#### 2. Drop and recreate the target database

```bash
# Connect to PostgreSQL
kubectl exec -it -n brama brama-postgresql-0 -- psql -U postgres

# In the psql shell:
DROP DATABASE IF EXISTS ai_community_platform;
CREATE DATABASE ai_community_platform OWNER app;
\q
```

#### 3. Restore from backup

```bash
# Copy backup file into the pod
kubectl cp ./brama-core-YYYYMMDD-HHMMSS.sql \
  brama/brama-postgresql-0:/tmp/restore.sql

# Restore the database
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform -f /tmp/restore.sql

# Clean up the temporary file
kubectl exec -n brama brama-postgresql-0 -- rm /tmp/restore.sql
```

#### 4. Verify database connectivity

```bash
# Connect and check table count
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';"
# Expected: a non-zero count of tables
```

#### 5. Verify migration state

```bash
# Check that the migrations table exists and has entries
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "SELECT version, executed_at FROM doctrine_migration_versions ORDER BY executed_at DESC LIMIT 5;"
# Expected: recent migration versions with timestamps
```

#### 6. Restart application workloads

```bash
kubectl scale deploy/brama-core -n brama --replicas=1
kubectl scale deploy/brama-core-scheduler -n brama --replicas=1

# Wait for pods to become ready
kubectl rollout status deploy/brama-core -n brama
kubectl rollout status deploy/brama-core-scheduler -n brama
```

#### 7. Verify application health

```bash
# Check health endpoint
kubectl port-forward -n brama svc/brama-core 8080:80 &
sleep 3
curl -sf http://localhost:8080/health
# Expected: {"status":"ok"} or HTTP 200

# Check admin interface is accessible
curl -sf http://localhost:8080/admin/login
# Expected: HTTP 200 (login page)

# Kill port-forward
kill %1
```

### Post-Restore Verification Checklist

After completing the restore, verify:

- [ ] PostgreSQL pod is `Running` and healthy
- [ ] Database connectivity confirmed (`psql` connects without error)
- [ ] Migration state verified (doctrine_migration_versions table has expected entries)
- [ ] Core health endpoint returns HTTP 200
- [ ] Admin login page is accessible
- [ ] Key data is visible (communities, agents, configurations exist in the database)
- [ ] Application logs show no database connection errors

```bash
# Quick verification commands
kubectl get pods -n brama
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "SELECT count(*) FROM users;"
kubectl logs deploy/brama-core -n brama --tail=50 | grep -i error
```

---

## OpenSearch Rebuild

OpenSearch is Tier B (rebuildable). If indices are lost, rebuild from PostgreSQL.

### When to Rebuild

- OpenSearch pod was deleted and PVC was lost
- OpenSearch data directory was corrupted
- OpenSearch was disabled and re-enabled after data loss

### Rebuild Procedure

#### 1. Verify OpenSearch is healthy

```bash
kubectl get pods -n brama -l app.kubernetes.io/name=opensearch
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf http://localhost:9200/_cluster/health
# Expected: {"status":"green"} or {"status":"yellow"}
```

#### 2. Re-index from PostgreSQL

```bash
# Re-index knowledge base entries
kubectl exec -n brama deploy/brama-core -- \
  php bin/console knowledge:reindex --no-interaction

# Verify index was created
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf http://localhost:9200/_cat/indices?v
```

#### 3. Verify search functionality

```bash
# Test a basic search query through the platform
kubectl port-forward -n brama svc/brama-core 8080:80 &
sleep 3
curl -sf "http://localhost:8080/api/v1/search?q=test" \
  -H "Authorization: Bearer YOUR_TOKEN"
kill %1
```

### Loss Impact Statement

> OpenSearch index loss causes search and log features to be **temporarily unavailable**.
> The platform remains fully functional for all non-search operations.
> Re-indexing from PostgreSQL restores full search functionality.
> No permanent data loss occurs when OpenSearch indices are lost.

---

## Redis Loss and Recovery

Redis is Tier C (cache/session state). **No backup is required.**

### Loss Impact Statement

> Redis data loss causes **temporary** degradation only:
> - Cache misses: next request rebuilds the cache entry from PostgreSQL
> - Session reset: users are redirected to login; no data is lost
>
> The platform recovers automatically after Redis restarts.
> No operator action is required beyond confirming Redis is running.

### Recovery Procedure

```bash
# Verify Redis is running
kubectl get pods -n brama -l app.kubernetes.io/name=redis

# Check Redis connectivity
kubectl exec -n brama brama-redis-master-0 -- redis-cli ping
# Expected: PONG

# Application reconnects automatically — no manual intervention needed
```

---

## RabbitMQ Loss and Recovery

RabbitMQ is Tier C (transient message delivery). **No backup is required.**

### Loss Impact Statement

> RabbitMQ queue loss may cause **in-flight messages to be lost** on pod restart or node loss.
> - Durable queues survive pod restarts when persistence is enabled
> - In-flight messages (unacknowledged) may be lost on unexpected pod termination
> - Producers re-enqueue messages when they detect delivery failure
> - Task state is tracked in PostgreSQL; no permanent data loss occurs
>
> The platform recovers automatically after RabbitMQ restarts.

### Recovery Procedure

```bash
# Verify RabbitMQ is running
kubectl get pods -n brama -l app.kubernetes.io/name=rabbitmq

# Check RabbitMQ management interface
kubectl port-forward -n brama svc/brama-rabbitmq 15672:15672 &
sleep 3
curl -sf http://localhost:15672/api/overview \
  -u app:app | python3 -m json.tool | grep -E "queues|messages"
kill %1

# Application reconnects automatically — no manual intervention needed
```

---

## Rollback Strategy

This section covers the full rollback path for a failed upgrade, combining Helm rollback with
database restore when necessary.

### Decision Tree

```
Upgrade failed?
├── Migration job failed (schema not applied)?
│   └── helm rollback → verify health → done
├── Migration job succeeded but app is broken?
│   ├── Schema change is reversible?
│   │   └── helm rollback → verify health → done
│   └── Schema change is NOT reversible (point of no return)?
│       └── Stop app → restore PostgreSQL from backup → helm rollback → verify health
└── App rolled out but data is corrupted?
    └── Stop app → restore PostgreSQL from backup → helm rollback → verify health
```

### Step 1: Assess Migration Reversibility

Before rolling back, determine whether the failed release applied irreversible schema changes:

```bash
# Check migration job logs
kubectl logs job/brama-migrate-$(kubectl get jobs -n brama -o name | grep migrate | tail -1 | cut -d/ -f2) -n brama

# Check current migration state
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "SELECT version, executed_at FROM doctrine_migration_versions ORDER BY executed_at DESC LIMIT 10;"
```

**Point of no return**: If a migration added columns, dropped columns, or transformed data in a
way that cannot be reversed by the previous application version, a database restore is required
before rolling back the Helm release.

### Step 2: Helm Rollback (Application Only)

If the schema is compatible with the previous release:

```bash
# List release history
helm history brama -n brama

# Roll back to the last known-good revision
helm rollback brama <revision> -n brama --wait --timeout 15m

# Verify rollout
kubectl get pods -n brama
kubectl rollout status deploy/brama-core -n brama
curl -sf https://platform.example.com/health
```

### Step 3: Database Restore + Helm Rollback (Full Rollback)

If the schema change is irreversible:

```bash
# 1. Stop application workloads
kubectl scale deploy/brama-core -n brama --replicas=0
kubectl scale deploy/brama-core-scheduler -n brama --replicas=0

# 2. Restore PostgreSQL from pre-upgrade backup
# (Follow the PostgreSQL Restore Procedure above)

# 3. Roll back the Helm release
helm rollback brama <revision> -n brama --wait --timeout 15m

# 4. Scale application back up
kubectl scale deploy/brama-core -n brama --replicas=1
kubectl scale deploy/brama-core-scheduler -n brama --replicas=1

# 5. Verify health
kubectl rollout status deploy/brama-core -n brama
curl -sf https://platform.example.com/health
```

### Post-Rollback Verification

After any rollback, verify:

- [ ] All pods are `Running`
- [ ] Core health endpoint returns HTTP 200
- [ ] Admin login works
- [ ] Migration state matches the rolled-back release
- [ ] No database connection errors in logs

```bash
kubectl get pods -n brama
curl -sf https://platform.example.com/health
kubectl logs deploy/brama-core -n brama --tail=100 | grep -i error
```

---

## Related Documents

- [k3s Storage Architecture](./k3s-storage-architecture.md)
- [Storage Verification Procedures](./k3s-storage-verification.md)
- [Kubernetes Upgrade Runbook](./kubernetes-upgrade.md)
- [Kubernetes Installation Guide](./kubernetes-install.md)
