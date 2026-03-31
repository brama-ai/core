# Change: Enable Platform Runtime on Local k3s

## Why

The platform has a validated Docker Compose runtime for local development, but no verified
Kubernetes-compatible deployment path. Before migrating production to k3s on Hetzner
(`migrate-to-k3s-hetzner`), the team needs a local k3s environment where manifests, configuration
strategies, health probes, and cross-service connectivity can be developed and tested incrementally.

Rancher Desktop provides a zero-cost local k3s cluster that mirrors the target production topology.
This change establishes the local k3s runtime as a second deployment target — not a replacement for
Compose, but a prerequisite for confident production migration.

## What Changes

- **ADDED**: Documented prerequisites for Rancher Desktop k3s (versions, settings, context name)
- **ADDED**: Target namespace (`brama`) with shared labels and naming conventions
- **ADDED**: Shared ConfigMap/Secret strategy mapping `.env.deployment` values to Kubernetes resources
- **ADDED**: k3s deployment assets for infrastructure services (PostgreSQL, Redis, RabbitMQ, OpenSearch)
- **ADDED**: k3s deployment assets for the core runtime with readiness/liveness probes
- **ADDED**: k3s deployment assets for one reference agent (hello-agent) with connectivity verification
- **ADDED**: Ingress and port-forward documentation for local operator access

## Impact

- Affected specs:
  - `k3s-deployment` — new capability covering local k3s bootstrapping, config strategy,
    infrastructure services, core runtime, reference agent, and operator access
- Affected code:
  - New deployment manifests or kustomize overlays under workspace deployment layer
  - New documentation under `docs/` for k3s local setup runbook
- Affected runtime:
  - Local Rancher Desktop k3s environment (no production impact)
- Relationship to other changes:
  - Prerequisite for `migrate-to-k3s-hetzner` — validates manifests locally before production
  - Complements `local-dev-runtime` spec — Compose remains the primary dev path; k3s is additive
  - Does NOT overlap with `migrate-to-k3s-hetzner` Helm chart scope — this change uses plain
    manifests for simplicity; the Hetzner change introduces the full Helm chart
