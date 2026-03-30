# Change: Add k3s Storage Architecture for Stateful Platform Services

## Why

The platform already has high-level k3s plans for local validation and Hetzner deployment, but the
storage layer is still underspecified. Postgres, Redis, RabbitMQ, OpenSearch, and observability
dependencies have different durability, recovery, and sizing needs. Without an explicit storage
architecture, the first real k3s rollout risks accidental data loss, weak backup coverage, and
hard-to-reverse persistence decisions.

## What Changes

- **ADDED**: A canonical k3s storage architecture for stateful platform services (`k3s-storage` spec)
- **ADDED**: Classification of services by durability tier (A: authoritative, B: rebuildable, C: optional)
- **ADDED**: StorageClass, PVC sizing, and retention requirements for single-node k3s (local and Hetzner)
- **ADDED**: Backup and restore requirements for PostgreSQL with concrete verification steps
- **ADDED**: Loss and rebuild expectations for Redis, RabbitMQ, and OpenSearch
- **ADDED**: Explicit persistence policy for Langfuse observability data
- **ADDED**: Externalization path requirement ensuring storage decisions do not prevent future migration
  to managed services
- **ADDED**: Pod-restart survival verification for Tier A and Tier B services
- **ADDED**: Self-hosted backup coverage model, restore verification, and rollback strategy
- **MODIFIED**: k3s deployment requirements to include storage verification as a gate before core rollout

## Impact

- Affected specs:
  - `k3s-deployment` (modified: storage verification gate)
  - `self-hosted-deployment` (new: backup coverage, restore verification, rollback strategy)
  - `k3s-storage` (new: durability tiers, PVC strategy, backup/restore, externalization)
- Affected code:
  - `deploy/charts/brama/` — Helm values documenting persistence flags, PVC sizes, storage classes
  - `deploy/charts/brama/values-k3s-dev.yaml` — local dev storage baselines
  - `deploy/charts/brama/values-k3s-local-infra.yaml` — infra-only storage baselines
  - `deploy/charts/brama/values-prod.example.yaml` — production storage guidance
- Affected operations:
  - local k3s validation (pod-restart verification)
  - Hetzner single-node production deployment (backup/restore procedures)
  - upgrade runbooks (pre-upgrade backup, post-restore verification)
- Related changes:
  - `enable-k3s-runtime` — defines the base k3s-deployment spec being modified
  - `migrate-to-k3s-hetzner` — defines Hetzner-specific k3s deployment requirements
