# Deployment Topology Matrix

## Overview

The AI Community Platform supports two official deployment modes. This document describes the
supported topologies, their trade-offs, and the shared deployment contract that applies to both.

## Supported Topologies

| Topology | Mode | Best for | Status |
|----------|------|----------|--------|
| Single-host Docker Compose | Docker | Local dev, hobby, small self-hosted | Supported |
| Kubernetes with bundled infra | Kubernetes | Cluster operators, dev/staging | Initial skeleton |
| Kubernetes with external managed infra | Kubernetes | Production cluster operators | Initial skeleton |

> **Note**: The Kubernetes packaging is in its initial skeleton phase. The chart defines the
> operator contract but image publishing and a hosted chart repository are planned for a future
> release.

## Deployment Mode Comparison

| Aspect | Docker Compose | Kubernetes |
|--------|---------------|------------|
| **Operator interface** | `make` targets + compose files | `helm upgrade` + `values.yaml` |
| **Config injection** | `.env.local` + `compose.override.yaml` | Kubernetes Secrets + `values.yaml` |
| **Migrations** | `make migrate` (explicit command) | Pre-upgrade/post-install Job hook |
| **Health checks** | Docker healthcheck + curl | Kubernetes readiness/liveness/startup probes |
| **Ingress** | Traefik (bundled) | Ingress controller (operator-provided) |
| **TLS** | Traefik ACME or external | cert-manager or external |
| **Scaling** | Single-host only | Horizontal scaling for stateless services |
| **Stateful deps** | Always bundled | Bundled (default) or external managed |
| **Rollback** | `git checkout` + `make up` | `helm rollback` |
| **Upgrade flow** | Pull/checkout + migrate + restart | `helm upgrade` with migration hook |

## Service Classification

### Mandatory application services

These services are required in every deployment topology:

| Service | Docker | Kubernetes | Notes |
|---------|--------|------------|-------|
| `core` | `compose.core.yaml` | `core` Deployment | Main platform |
| `core-scheduler` | `compose.core.yaml` | `core-scheduler` Deployment | Single replica only |

### Optional agents

Agents can be enabled or disabled independently in both modes:

| Agent | Docker compose file | Kubernetes values key | Default |
|-------|--------------------|-----------------------|---------|
| knowledge-agent | `compose.agent-knowledge.yaml` | `agents.knowledge.enabled` | true |
| hello-agent | `compose.agent-hello.yaml` | `agents.hello.enabled` | true |
| news-maker-agent | `compose.agent-news-maker.yaml` | `agents.newsMaker.enabled` | false |
| wiki-agent | `compose.agent-wiki.yaml` | Not yet in chart | — |
| dev-agent | `compose.agent-dev.yaml` | Not yet in chart | — |
| dev-reporter-agent | `compose.agent-dev-reporter.yaml` | Not yet in chart | — |

### Optional platform add-ons

| Add-on | Docker compose file | Kubernetes | Notes |
|--------|--------------------|-----------|----|
| Langfuse (LLM observability) | `compose.langfuse.yaml` | Not yet in chart | Planned |
| OpenClaw (Telegram gateway) | `compose.openclaw.yaml` | Not yet in chart | Planned |
| Slides | `compose.slides.yaml` | Not yet in chart | Planned |

### Stateful infrastructure dependencies

| Dependency | Docker | Kubernetes (bundled) | Kubernetes (external) |
|------------|--------|---------------------|----------------------|
| PostgreSQL | Bundled | `postgresql.enabled: true` | `externalDependencies.postgres.external: true` |
| Redis | Bundled | `redis.enabled: true` | `externalDependencies.redis.external: true` |
| OpenSearch | Bundled | Not yet in chart | Planned |
| RabbitMQ | Bundled | Not yet in chart | Planned |

**Recommendation for production Kubernetes**: use external managed services for PostgreSQL and
Redis. Bundled sub-charts are suitable for development and staging environments.

## Shared Deployment Contract

Both Docker and Kubernetes deployments share the same logical contract:

### Configuration inputs

| Input | Docker | Kubernetes |
|-------|--------|------------|
| App secrets | `.env.local` | Kubernetes Secret + `secretRef` |
| Public URL | `compose.override.yaml` env | `core.env.EDGE_AUTH_LOGIN_BASE_URL` |
| LLM API keys | `.env.local` | Kubernetes Secret |
| Database URL | Auto-wired via compose network | `DATABASE_URL` in Secret |
| Langfuse keys | `.env.local` or compose env | `core.env` or Secret |

### Health and readiness

Every HTTP service exposes `GET /health` returning HTTP 200 when ready.

| Service | Docker healthcheck | Kubernetes probe |
|---------|-------------------|-----------------|
| core | `curl -sf http://localhost/health` | `httpGet /health` |
| agents | `curl -sf http://localhost:<port>/health` | `httpGet /health` |
| litellm | Docker healthcheck | `httpGet /health` |
| core-scheduler | Not applicable | `exec php bin/console scheduler:status` |

### Migration behavior

Migrations are always explicit — they are never a hidden side effect of container startup.

| Mode | How migrations run |
|------|--------------------|
| Docker | `make migrate` (and per-agent variants) |
| Kubernetes | Pre-upgrade/post-install Job hook |

### Upgrade verification gates

Both modes use the same logical verification gates after an upgrade:

1. Migration completed successfully
2. Core health endpoint responds
3. Critical worker (scheduler) is healthy
4. Public entrypoint (ingress/domain) is accessible
5. At least one critical agent flow works (optional smoke)

## Image Versioning Policy

> **Current state**: Images are built from source on the deployment host (Docker) or from the
> local chart path (Kubernetes). Published versioned images are planned for a future release.

When versioned images are published:
- Every release will tag images with a semantic version (e.g., `0.2.0`)
- The `latest` tag will not be used in production deployments
- The chart `appVersion` will match the platform release version
- A compatibility matrix will document which chart version supports which app version

## Trade-offs

### Docker Compose

**Advantages:**
- Minimal prerequisites (Docker + Make)
- Fast local development cycle
- Batteries-included: all dependencies bundled
- Simple rollback via `git checkout`

**Limitations:**
- Single-host only — no horizontal scaling
- All stateful dependencies run on the same host
- No native rolling update support

### Kubernetes

**Advantages:**
- Horizontal scaling for stateless services
- Native rolling updates with readiness gates
- External managed services for stateful dependencies
- Helm revision history for rollback
- Standard cluster-native secret management

**Limitations:**
- Higher operational complexity
- Requires a Kubernetes cluster and Helm
- Initial packaging is a skeleton — not all services are chart-managed yet
- Stateful services (PostgreSQL, Redis) require careful PVC management

## Future Roadmap

The following are planned but not yet implemented:

- [ ] Published versioned container images
- [ ] Hosted Helm chart repository
- [ ] Langfuse, OpenClaw, Slides in the Helm chart
- [ ] OpenSearch and RabbitMQ as optional chart dependencies
- [ ] Remaining agents in the Helm chart
- [ ] Horizontal Pod Autoscaler for stateless services
- [ ] PodDisruptionBudget for critical services
- [ ] Network policies
