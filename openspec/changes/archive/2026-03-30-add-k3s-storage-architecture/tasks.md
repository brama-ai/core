# Tasks: add-k3s-storage-architecture

## 1. Define Storage Contracts
- [x] 1.1 Add a `k3s-storage` spec describing service durability tiers, PVC strategy, backup/restore
  requirements, loss expectations, Langfuse persistence policy, externalization path, and pod-restart
  verification
- [x] 1.2 Modify `k3s-deployment` spec to require storage verification (PVC bound, pod-restart
  survival) before core and agent rollout
- [x] 1.3 Add `self-hosted-deployment` spec requirements for backup coverage model, restore
  verification, and rollback strategy

## 2. Document Storage Architecture
- [x] 2.1 Document the stateful service matrix with durability tiers: Postgres (Tier A), OpenSearch
  and Langfuse (Tier B), Redis, RabbitMQ, and registry (Tier C)
- [x] 2.2 Document the baseline StorageClass strategy: `local-path` for both local and Hetzner
  single-node k3s
- [x] 2.3 Document PVC size baselines per service per environment (local dev vs production)
- [x] 2.4 Document what data is authoritative (Postgres) vs rebuildable (OpenSearch indices) vs
  disposable (Redis cache)
- [x] 2.5 Document Langfuse persistence classification and expected impact of data loss

## 3. Helm Chart Storage Configuration
- [x] 3.1 Verify Helm values structure expresses persistence flags, storage class, and PVC sizes
  explicitly for all stateful services
- [x] 3.2 Verify `values-k3s-dev.yaml` has correct local-path storage baselines for all enabled
  stateful services
- [x] 3.3 Verify `values-k3s-local-infra.yaml` has correct local-path storage baselines
- [x] 3.4 Verify `values-prod.example.yaml` documents externalization pattern for Postgres and Redis
- [x] 3.5 Add comments in values files documenting durability tier and backup expectations per service

## 4. Backup and Restore Runbooks
- [x] 4.1 Create PostgreSQL backup runbook with concrete `pg_dump` commands for k3s deployments
- [x] 4.2 Create PostgreSQL restore runbook with post-restore verification checklist (database
  connectivity, migration state, health endpoint, data visibility)
- [x] 4.3 Document OpenSearch rebuild/re-index guidance for when indices are lost
- [x] 4.4 Document Redis and RabbitMQ loss impact and recovery expectations
- [x] 4.5 Document rollback strategy covering both `helm rollback` and database restore

## 5. Verification Procedures
- [x] 5.1 Add operator verification steps for confirming PVCs are bound after infrastructure deployment
- [x] 5.2 Add pod-restart verification procedure for PostgreSQL (delete pod, verify data survives)
- [x] 5.3 Add pod-restart verification procedure for OpenSearch when persistence is enabled
- [x] 5.4 Add storage verification gate to the deployment runbook (before core rollout)

## 6. Documentation
- [x] 6.1 Add storage architecture overview documentation under `docs/`
- [x] 6.2 Add operator guidance for pre-upgrade backup, restore, and rollback checks
- [x] 6.3 Update deployment runbooks to reference storage verification steps

## 7. Quality Checks
- [x] 7.1 `openspec validate add-k3s-storage-architecture --strict`
- [x] 7.2 Review the change against `enable-k3s-runtime` and `migrate-to-k3s-hetzner` for overlap
  and conflicts
- [x] 7.3 Verify all spec scenarios are testable by an operator following the documented procedures
