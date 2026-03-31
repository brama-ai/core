## Context

The project currently has a validated Docker Compose runtime and a devcontainer that reuses that
runtime. The next step is not to replace Compose immediately, but to add a separate local k3s path
that can be booted, observed, and tested step by step in Rancher Desktop.

The highest risk is not authoring manifests. The highest risk is building a large k3s layer that
cannot be validated incrementally. This change therefore optimizes for staged verification:

1. cluster access and namespace
2. shared configuration and secrets
3. infrastructure services
4. core runtime
5. one reference agent
6. ingress and operator access

This change is a prerequisite for `migrate-to-k3s-hetzner`. The local k3s path proves that
manifests work before they are deployed to a remote VPS.

## Goals / Non-Goals

### Goals

- Provide a documented, reproducible path to boot the platform on local k3s via Rancher Desktop
- Define a ConfigMap/Secret strategy that maps cleanly from `.env.deployment` values
- Boot all four infrastructure services (PostgreSQL, Redis, RabbitMQ, OpenSearch) as k3s deployments
- Boot the core runtime with readiness and liveness probes
- Boot one reference agent (hello-agent) and verify core-to-agent connectivity
- Document ingress or port-forward paths for browser-based operator access

### Non-Goals

- Replace Docker Compose as the primary local development runtime
- Create a production-grade Helm chart (that is `migrate-to-k3s-hetzner` scope)
- Support multi-node clusters or HA configurations
- Implement CI/CD automation for k3s deployments
- Deploy the full agent fleet — only one reference agent is in scope
- Deploy optional services (LiteLLM, Langfuse, OpenClaw) — these are deferred to the Hetzner change

## Decisions

### 1. Plain manifests, not Helm, for the local k3s path

**Decision**: Use plain Kubernetes YAML manifests (optionally organized with Kustomize) for the
local k3s deployment path.

**Rationale**: The goal is to validate that the platform can boot in k3s with the simplest possible
tooling. Plain manifests are easier to read, debug, and iterate on during initial bring-up. The
full Helm chart is scoped to `migrate-to-k3s-hetzner`.

**Alternatives considered**:
- Helm chart from the start — rejected because it adds templating complexity before the basic
  deployment model is proven
- Kompose (auto-convert from Docker Compose) — rejected because generated manifests are noisy
  and don't teach the team the k3s deployment model

### 2. Single namespace with shared labels

**Decision**: All resources deploy into a single `brama` namespace with consistent labels:
- `app.kubernetes.io/part-of: brama`
- `app.kubernetes.io/component: <component>` (e.g., `infra`, `core`, `agent`)
- `app.kubernetes.io/name: <service>` (e.g., `postgres`, `redis`, `core`, `hello-agent`)

**Rationale**: A single namespace simplifies DNS resolution (services resolve as `<name>.brama.svc`)
and avoids cross-namespace networking complexity for the initial local path.

### 3. ConfigMap for non-secret values, Secret for credentials

**Decision**: Map `.env.deployment` into two Kubernetes resources:
- `ConfigMap/brama-config` — non-secret runtime values (hostnames, ports, URLs, feature flags)
- `Secret/brama-secrets` — credentials and sensitive values (passwords, API keys, JWT secrets)

**Mapping strategy from `.env.deployment`**:

| `.env.deployment` variable | k3s resource | Key |
|---|---|---|
| `POSTGRES_HOST`, `POSTGRES_PORT` | ConfigMap | `POSTGRES_HOST`, `POSTGRES_PORT` |
| `POSTGRES_USER`, `POSTGRES_PASSWORD` | Secret | `POSTGRES_USER`, `POSTGRES_PASSWORD` |
| `DATABASE_URL` | ConfigMap (constructed) | `DATABASE_URL` |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_URL` | ConfigMap | `REDIS_HOST`, `REDIS_PORT`, `REDIS_URL` |
| `OPENSEARCH_HOST`, `OPENSEARCH_PORT`, `OPENSEARCH_URL` | ConfigMap | same |
| `RABBITMQ_HOST`, `RABBITMQ_PORT` | ConfigMap | same |
| `RABBITMQ_USER`, `RABBITMQ_PASSWORD` | Secret | same |
| `EDGE_AUTH_JWT_SECRET` | Secret | `EDGE_AUTH_JWT_SECRET` |
| `LITELLM_API_KEY` | Secret | `LITELLM_API_KEY` |
| `LANGFUSE_PUBLIC_KEY`, `LANGFUSE_SECRET_KEY` | Secret | same |

Services reference these via `envFrom` with `configMapRef` and `secretRef`.

**Rationale**: This mirrors the `.env.deployment` model that already works for Compose, making the
mapping auditable and the transition to Helm values straightforward later.

### 4. Infrastructure services use simple Deployments with PVCs

**Decision**: Each infrastructure service (PostgreSQL, Redis, RabbitMQ, OpenSearch) is deployed as
a single-replica Deployment with a PersistentVolumeClaim using the k3s default `local-path`
StorageClass.

**Rationale**: StatefulSets are not needed for single-replica local development. Deployments are
simpler and sufficient. The `local-path` provisioner is built into k3s and requires no additional
setup.

**Alternatives considered**:
- StatefulSets — rejected for local dev; adds complexity without benefit for single-replica
- Helm sub-charts (Bitnami PostgreSQL, etc.) — rejected for this change; too much abstraction
  before the basic model is proven

### 5. Core readiness/liveness probes target /health

**Decision**: The core Deployment uses:
- `readinessProbe`: HTTP GET `/health` on port 80, `initialDelaySeconds: 10`, `periodSeconds: 5`
- `livenessProbe`: HTTP GET `/health` on port 80, `initialDelaySeconds: 30`, `periodSeconds: 10`

**Rationale**: The existing `health-endpoint` spec defines `GET /health` returning
`{"status":"ok","service":"core-platform"}` with 200 OK. This is the natural probe target.

### 6. hello-agent as the reference agent

**Decision**: Use `hello-agent` as the reference agent for k3s validation.

**Rationale**: hello-agent is the simplest agent in the fleet — it has a published container image
(`ghcr.io/nmdimas/a2a-hello-agent:main`), minimal dependencies (LiteLLM, OpenSearch), and a
health endpoint. It is the lowest-risk choice for proving agent runtime viability in k3s.

### 7. Port-forward as the primary local access method

**Decision**: Document `kubectl port-forward` as the primary access method for local k3s, with
optional Traefik Ingress as a secondary path.

**Rationale**: Port-forward works immediately with zero configuration. Traefik Ingress in Rancher
Desktop requires additional setup (host entries, Traefik configuration) that varies by OS. The
port-forward path is universally reliable.

**Port-forward mapping**:
- Core: `kubectl port-forward svc/core 8081:80 -n brama`
- PostgreSQL: `kubectl port-forward svc/postgres 5432:5432 -n brama`
- Redis: `kubectl port-forward svc/redis 6379:6379 -n brama`
- RabbitMQ management: `kubectl port-forward svc/rabbitmq 15672:15672 -n brama`
- OpenSearch: `kubectl port-forward svc/opensearch 9200:9200 -n brama`

## Risks / Trade-offs

- **Risk**: Rancher Desktop k3s version drift may cause manifest incompatibilities
  - **Mitigation**: Document minimum Rancher Desktop version and k3s version in prerequisites
- **Risk**: `local-path` PVCs may behave differently from production storage
  - **Mitigation**: This is acceptable for local dev; production storage is scoped to the Hetzner change
- **Risk**: Plain manifests may diverge from the eventual Helm chart
  - **Mitigation**: Keep manifests simple and well-labeled; the Helm chart can be generated from
    proven manifests rather than the reverse
- **Risk**: OpenSearch may require elevated `vm.max_map_count` on the host
  - **Mitigation**: Document the sysctl requirement in prerequisites; Rancher Desktop typically
    handles this via its VM

## Migration Plan

This is a net-new capability — no migration from existing state is needed. The change is additive
and does not modify the Docker Compose runtime.

Rollback: Delete the `brama` namespace (`kubectl delete namespace brama`) to remove all k3s
resources cleanly.

## Open Questions

- Whether Kustomize overlays should be used from the start to support dev/prod variants, or
  whether plain manifests are sufficient for the initial local path
- Whether the manifest directory should live under `deploy/k3s/` in the workspace root or under
  `brama-core/deploy/k3s/`
