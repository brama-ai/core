# Change: Validate Local k3s Runtime End-to-End

## Why

Rendering Helm manifests is not enough. The project needs a reproducible validation path that
proves the local k3s runtime actually works in Rancher Desktop with the current Helm charts and
devcontainer configuration. Without this, the team can produce deployment assets that look correct
but fail on first real boot.

The purpose of this change is to convert the k3s deployment work into a verified operational path
with concrete checks at every stage — from cluster readiness through infrastructure, core runtime,
agent runtime, and operator-facing access.

## What Changes

- **ADDED**: A 16-step local k3s validation workflow covering five stages:
  1. Cluster readiness (node reachability, namespace, system pod health)
  2. Infrastructure layer (PostgreSQL, Redis, RabbitMQ, OpenSearch status)
  3. Core runtime (pod readiness, health endpoint, operator access via port-forward/ingress)
  4. Reference agent runtime (hello-agent readiness, health, core-to-agent connectivity)
  5. Verified runbook (step order, known issues, minimum re-validation sequence)
- **ADDED**: Acceptance criteria for each stage with success signals and failure inspection commands
- **ADDED**: A verified operator runbook at `docs/guides/deployment/en/local-k3s-validation.md` (ua mirror)
- **ADDED**: Spec requirements for repeatable validation, success signals, and runbook completeness

## Scope

- **In scope**: Local Rancher Desktop k3s validation using current Helm chart (`brama-core/deploy/charts/brama`),
  `values-k3s-dev.yaml`, Makefile `k8s-*` targets, and devcontainer Docker-outside-of-Docker setup
- **In scope**: Bitnami sub-charts (PostgreSQL 15.x, Redis 19.x, RabbitMQ 15.x), Traefik ingress
- **In scope**: Core app, core scheduler, migration job, hello-agent, news-maker-agent
- **Out of scope**: Production deployment, Hetzner k3s migration, CI/CD pipeline integration,
  OpenSearch in-cluster (disabled in k3s-dev), LiteLLM, knowledge-agent

## Impact

- Affected specs:
  - `k3s-runtime-validation` (new capability)
- Affected docs:
  - `docs/guides/deployment/en/local-k3s-validation.md`
  - `docs/guides/deployment/ua/local-k3s-validation.md`
- Affected runtime:
  - Local Rancher Desktop k3s environment
- Affected Helm chart:
  - `brama-core/deploy/charts/brama/` (templates, values-k3s-dev.yaml)
- Affected Makefile targets:
  - `k8s-setup`, `k8s-build`, `k8s-load`, `k8s-secrets`, `k8s-deploy`, `k8s-status`
