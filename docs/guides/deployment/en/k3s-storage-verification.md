# k3s Storage Verification Procedures

## Overview

This document provides operator verification steps for confirming that storage is healthy before
deploying core and agent workloads. Storage verification is a **required gate** before core rollout
in k3s deployments.

> **When to run these procedures:**
> - After deploying infrastructure services (PostgreSQL, Redis, RabbitMQ, OpenSearch)
> - Before deploying core and agent workloads
> - After a pod restart or node reboot
> - As part of the pre-upgrade checklist

## Storage Verification Gate

The following checks MUST pass before deploying core and agent workloads:

1. All Tier A PVCs (PostgreSQL) are `Bound`
2. PostgreSQL pod is `Running` and passes health check
3. At least one pod-restart test confirms PostgreSQL data survives

If any Tier A check fails, **do not proceed with core rollout** until the issue is resolved.

---

## Step 1: Verify PVCs Are Bound

Check that all persistent volume claims are in `Bound` state:

```bash
# List all PVCs in the brama namespace
kubectl get pvc -n brama

# Expected output (example):
# NAME                              STATUS   VOLUME                                     CAPACITY   ACCESS MODES   STORAGECLASS   AGE
# data-brama-postgresql-0           Bound    pvc-abc123...                              2Gi        RWO            local-path     5m
# redis-data-brama-redis-master-0   Bound    pvc-def456...                              1Gi        RWO            local-path     5m
# data-brama-rabbitmq-0             Bound    pvc-ghi789...                              1Gi        RWO            local-path     5m
```

**Tier A PVCs (must be Bound before core rollout):**

```bash
# Check PostgreSQL PVC specifically
kubectl get pvc -n brama -l app.kubernetes.io/name=postgresql
# STATUS must be: Bound
```

**Diagnosing a PVC stuck in Pending:**

```bash
# Describe the PVC to see events
kubectl describe pvc data-brama-postgresql-0 -n brama

# Common causes:
# - StorageClass not found: verify local-path provisioner is running
# - Insufficient disk space: check node disk usage
# - Node selector mismatch: check node labels
```

**Verify local-path provisioner is running:**

```bash
kubectl get pods -n kube-system | grep local-path
# Expected: local-path-provisioner-... Running
```

---

## Step 2: Verify Stateful Services Are Healthy

### PostgreSQL Health Check

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=postgresql
# Expected: brama-postgresql-0   1/1   Running   0   Xm

# Verify database connectivity
kubectl exec -n brama brama-postgresql-0 -- \
  pg_isready -U postgres
# Expected: /var/run/postgresql:5432 - accepting connections

# Verify the platform database exists
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -c "\l" | grep ai_community_platform
# Expected: ai_community_platform | app | UTF8 | ...
```

### Redis Health Check

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=redis
# Expected: brama-redis-master-0   1/1   Running   0   Xm

# Verify Redis connectivity
kubectl exec -n brama brama-redis-master-0 -- redis-cli ping
# Expected: PONG
```

### RabbitMQ Health Check (if enabled)

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=rabbitmq
# Expected: brama-rabbitmq-0   1/1   Running   0   Xm

# Verify RabbitMQ is accepting connections
kubectl exec -n brama brama-rabbitmq-0 -- \
  rabbitmq-diagnostics ping
# Expected: Ping succeeded if node is running
```

### OpenSearch Health Check (if enabled)

```bash
# Check pod status
kubectl get pods -n brama -l app.kubernetes.io/name=opensearch
# Expected: brama-opensearch-0   1/1   Running   0   Xm

# Verify cluster health
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf http://localhost:9200/_cluster/health | python3 -m json.tool
# Expected: {"status": "green"} or {"status": "yellow"}
```

---

## Step 3: Pod-Restart Verification for PostgreSQL

This test confirms that PostgreSQL data survives a pod restart. Run this before the first
production deployment and after any infrastructure changes.

### Procedure

#### 1. Write a test record

```bash
# Insert a test record to verify data persistence
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "CREATE TABLE IF NOT EXISTS _storage_test (id serial, value text, created_at timestamptz DEFAULT now());"

kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "INSERT INTO _storage_test (value) VALUES ('pod-restart-test-$(date +%s)');"

# Record the inserted value
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "SELECT * FROM _storage_test ORDER BY id DESC LIMIT 1;"
```

#### 2. Delete the PostgreSQL pod

```bash
# Delete the pod — k3s will reschedule it automatically
kubectl delete pod brama-postgresql-0 -n brama

# Wait for the pod to be rescheduled and become ready
kubectl wait pod -n brama -l app.kubernetes.io/name=postgresql \
  --for=condition=Ready --timeout=120s
```

#### 3. Verify data survived the restart

```bash
# Check that the test record is still present
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "SELECT * FROM _storage_test ORDER BY id DESC LIMIT 1;"
# Expected: the same record inserted in step 1

# Verify PVC is still bound
kubectl get pvc -n brama -l app.kubernetes.io/name=postgresql
# Expected: STATUS = Bound
```

#### 4. Clean up the test record

```bash
kubectl exec -n brama brama-postgresql-0 -- \
  psql -U postgres -d ai_community_platform \
  -c "DROP TABLE IF EXISTS _storage_test;"
```

#### 5. Verify application health after restart

```bash
# If core is already deployed, verify it reconnects
kubectl rollout status deploy/brama-core -n brama
kubectl port-forward -n brama svc/brama-core 8080:80 &
sleep 3
curl -sf http://localhost:8080/health
# Expected: HTTP 200
kill %1
```

---

## Step 4: Pod-Restart Verification for OpenSearch (When Persistence Is Enabled)

Run this test when OpenSearch is enabled with persistence to confirm index data survives restarts.

### Procedure

#### 1. Write a test document

```bash
# Insert a test document into OpenSearch
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf -X POST "http://localhost:9200/storage-test/_doc" \
  -H "Content-Type: application/json" \
  -d '{"value": "pod-restart-test", "timestamp": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"}'
# Expected: {"result":"created",...}
```

#### 2. Delete the OpenSearch pod

```bash
kubectl delete pod brama-opensearch-0 -n brama

# Wait for the pod to be rescheduled and become ready
kubectl wait pod -n brama -l app.kubernetes.io/name=opensearch \
  --for=condition=Ready --timeout=180s
```

#### 3. Verify data survived the restart

```bash
# Check cluster health
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf http://localhost:9200/_cluster/health | python3 -m json.tool

# Verify the test document is still present
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf "http://localhost:9200/storage-test/_search" | python3 -m json.tool
# Expected: hits.total.value = 1
```

#### 4. Clean up the test index

```bash
kubectl exec -n brama brama-opensearch-0 -- \
  curl -sf -X DELETE "http://localhost:9200/storage-test"
```

---

## Storage Verification Checklist

Use this checklist as the storage verification gate before core rollout:

### Tier A (PostgreSQL) — Required

- [ ] PostgreSQL PVC is `Bound`
- [ ] PostgreSQL pod is `Running` (1/1 Ready)
- [ ] `pg_isready` returns "accepting connections"
- [ ] Platform database (`ai_community_platform`) exists
- [ ] Pod-restart test: data survived pod deletion and rescheduling
- [ ] Core health endpoint returns HTTP 200 after PostgreSQL restart

### Tier B (OpenSearch) — Required When Enabled

- [ ] OpenSearch PVC is `Bound`
- [ ] OpenSearch pod is `Running` (1/1 Ready)
- [ ] Cluster health is `green` or `yellow`
- [ ] Pod-restart test: indexed data survived pod deletion and rescheduling

### Tier C (Redis, RabbitMQ) — Informational

- [ ] Redis pod is `Running` (1/1 Ready)
- [ ] `redis-cli ping` returns `PONG`
- [ ] RabbitMQ pod is `Running` (1/1 Ready) — if enabled
- [ ] RabbitMQ diagnostics ping succeeds — if enabled

---

## Diagnosing Storage Issues

### PVC Stuck in Pending

```bash
# Describe the PVC for events
kubectl describe pvc <pvc-name> -n brama

# Check local-path provisioner logs
kubectl logs -n kube-system -l app=local-path-provisioner --tail=50

# Check available disk space on the node
kubectl get nodes -o wide
# SSH to the node and check: df -h
```

### Pod Stuck in Pending After PVC Is Bound

```bash
# Describe the pod for scheduling events
kubectl describe pod <pod-name> -n brama

# Common causes:
# - Insufficient CPU/memory: check resource requests vs node capacity
# - Node selector mismatch: check node labels and pod affinity
```

### PostgreSQL Pod Crashes After Restart

```bash
# Check pod logs
kubectl logs brama-postgresql-0 -n brama --previous

# Common causes:
# - Corrupted data directory: may require restore from backup
# - Permission issue: check fsGroup in podSecurityContext
# - Out of disk space: check PVC usage
```

### Data Not Surviving Pod Restart

If data is lost after a pod restart, the PVC may not be correctly attached:

```bash
# Verify PVC is bound and attached to the correct node
kubectl get pvc -n brama -o wide
kubectl describe pvc data-brama-postgresql-0 -n brama | grep -E "Volume|Node|Status"

# Check if local-path provisioner created the volume directory
# (SSH to the node)
ls /var/lib/rancher/k3s/storage/
```

---

## Related Documents

- [k3s Storage Architecture](./k3s-storage-architecture.md)
- [PostgreSQL Backup and Restore Runbook](./k3s-storage-backup.md)
- [Kubernetes Installation Guide](./kubernetes-install.md)
- [Kubernetes Upgrade Runbook](./kubernetes-upgrade.md)
