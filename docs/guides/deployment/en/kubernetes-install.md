# Kubernetes Installation Guide

## Overview

This guide covers installing the AI Community Platform on a Kubernetes cluster using the official
Helm chart located at `deploy/charts/ai-community-platform/`.

> **Status**: Initial packaging skeleton. The chart defines the operator contract for configuration,
> secrets, migrations, probes, and ingress. Image publishing and a hosted chart repository are
> planned for a future release. For now, install from the local chart path.

## Deployment Modes

The platform supports two official deployment modes:

| Mode | Best for | Packaging |
|------|----------|-----------|
| **Docker Compose** | Local dev, hobby, single-host production | `compose.yaml` + Makefile |
| **Kubernetes** | Cluster-native operators, managed infrastructure | Helm chart |

This guide covers the Kubernetes path. For Docker, see
[`docs/guides/deployment/en/deployment.md`](./deployment.md).

## Prerequisites

- Kubernetes 1.27+
- Helm 3.12+
- `kubectl` configured for your target cluster
- An ingress controller (nginx-ingress recommended)
- cert-manager (optional, for TLS automation)
- Access to a container registry where platform images are published

## Service Topology

### Mandatory application services

| Service | Description | Replicas |
|---------|-------------|----------|
| `core` | Main platform (PHP/Symfony) | 1+ |
| `core-scheduler` | Background scheduler | 1 (fixed) |

### Optional agents (enable per environment)

| Agent | Default | Port |
|-------|---------|------|
| `knowledge` | enabled | 8083 |
| `hello` | enabled | 8085 |
| `newsMaker` | disabled | 8087 |

### Infrastructure dependencies

| Dependency | Bundled by default | Recommended for production |
|------------|-------------------|---------------------------|
| PostgreSQL | Yes (sub-chart) | External managed (RDS, Cloud SQL, etc.) |
| Redis | Yes (sub-chart) | External managed (ElastiCache, Memorystore, etc.) |
| OpenSearch | No | External managed or omit |
| RabbitMQ | No | External managed or omit |

## Step 1: Prepare Secrets

Create Kubernetes Secrets before installing the chart. The chart does not create secrets — it
references them by name.

### Core secrets

```bash
kubectl create namespace acp

kubectl create secret generic core-secrets \
  --namespace acp \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=EDGE_AUTH_JWT_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/ai_community_platform?serverVersion=16&charset=utf8" \
  --from-literal=LANGFUSE_PUBLIC_KEY="lf_pk_your_key" \
  --from-literal=LANGFUSE_SECRET_KEY="lf_sk_your_key"
```

### LiteLLM secrets

```bash
kubectl create secret generic litellm-secrets \
  --namespace acp \
  --from-literal=LITELLM_MASTER_KEY="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/litellm?serverVersion=16&charset=utf8" \
  --from-literal=OPENROUTER_API_KEY="sk-or-your-key"
```

### Agent secrets (repeat for each enabled agent)

```bash
kubectl create secret generic knowledge-agent-secrets \
  --namespace acp \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/knowledge_agent?serverVersion=16&charset=utf8"
```

> **Security note**: In production, prefer an external secret operator (External Secrets Operator,
> Sealed Secrets, Vault Agent Injector) over `kubectl create secret` to avoid secrets in shell
> history.

## Step 2: Prepare Values

Copy the example values file and customize it:

```bash
cp deploy/charts/ai-community-platform/values-prod.example.yaml values-prod.yaml
```

Edit `values-prod.yaml` with your environment-specific settings:

- Set `ingress.hosts.*` to your actual domain names
- Set `secretRef` fields to match the secret names you created
- Set image tags to the target release version
- Disable bundled sub-charts if using external managed services:
  ```yaml
  postgresql:
    enabled: false
  redis:
    enabled: false
  externalDependencies:
    postgres:
      external: true
      host: your-postgres-host
    redis:
      external: true
      host: your-redis-host
  ```

## Step 3: Install the Chart

```bash
helm upgrade --install ai-community-platform \
  ./deploy/charts/ai-community-platform \
  --namespace acp \
  --create-namespace \
  -f values-prod.yaml \
  --wait \
  --timeout 15m
```

The `--wait` flag causes Helm to wait until all Deployments and Jobs reach a ready state before
returning. The migration job runs as a `post-install` hook before the application pods start.

## Step 4: Verify the Installation

### Check pod status

```bash
kubectl get pods -n acp
```

All pods should reach `Running` state. The migration job pod will show `Completed`.

### Check migration job

```bash
kubectl get jobs -n acp
kubectl logs job/ai-community-platform-migrate-1 -n acp
```

The migration job logs should end with `==> Migrations complete`.

### Check rollout status

```bash
kubectl rollout status deploy/ai-community-platform-core -n acp
```

### Check ingress

```bash
kubectl get ingress -n acp
```

### Test health endpoint

```bash
# Replace with your actual domain or use port-forward
curl -sf https://platform.example.com/health
```

Or with port-forward:

```bash
kubectl port-forward -n acp svc/ai-community-platform-core 8080:80
curl -sf http://localhost:8080/health
```

## Step 5: Post-Install Verification

Minimum smoke checks after a fresh install:

- [ ] Platform URL loads and shows the login page
- [ ] Admin login works
- [ ] At least one agent health endpoint responds
- [ ] LiteLLM UI is accessible (if enabled)
- [ ] Migration job completed without errors

## Configuration Reference

### Key values

| Value | Description | Default |
|-------|-------------|---------|
| `core.image.tag` | Core app image tag | `0.1.0` |
| `core.secretRef` | Secret name for core env vars | `""` |
| `core.replicaCount` | Core replicas | `1` |
| `ingress.enabled` | Enable ingress | `true` |
| `ingress.tls.enabled` | Enable TLS | `false` |
| `migrations.enabled` | Run migration job on install/upgrade | `true` |
| `postgresql.enabled` | Bundle PostgreSQL sub-chart | `true` |
| `redis.enabled` | Bundle Redis sub-chart | `true` |

See `deploy/charts/ai-community-platform/values.yaml` for the full reference with all defaults.

## Probe Behavior

Every HTTP service exposes a `/health` endpoint wired to Kubernetes probes:

| Probe | Purpose | Failure action |
|-------|---------|----------------|
| `startupProbe` | Allows slow startup before liveness kicks in | Restart after 24 failures (2 min) |
| `readinessProbe` | Gates traffic until the app is ready | Remove from load balancer |
| `livenessProbe` | Restarts unhealthy containers | Restart container |

The scheduler uses an `exec` liveness probe instead of HTTP.

## Migration Behavior

Migrations run as a Kubernetes Job with Helm hook annotations:

```yaml
helm.sh/hook: pre-upgrade,post-install
helm.sh/hook-weight: "-5"
helm.sh/hook-delete-policy: before-hook-creation,hook-succeeded
```

This means:
- On fresh install: migration job runs after chart resources are created
- On upgrade: migration job runs before the new application pods start
- Completed jobs are cleaned up automatically on the next release

If the migration job fails, the Helm release will be marked as failed. Do not proceed with traffic
validation until migrations complete successfully.

## Troubleshooting

### Pod stuck in Pending

```bash
kubectl describe pod <pod-name> -n acp
```

Common causes: insufficient cluster resources, missing PVC, missing secret.

### Migration job failed

```bash
kubectl logs job/ai-community-platform-migrate-1 -n acp
```

Check for database connectivity issues or schema conflicts. Fix the root cause before retrying.

### Core pod CrashLoopBackOff

```bash
kubectl logs deploy/ai-community-platform-core -n acp --previous
```

Common causes: missing secret reference, wrong DATABASE_URL, failed migration.

### Ingress not routing

```bash
kubectl describe ingress ai-community-platform -n acp
kubectl get events -n acp --sort-by='.lastTimestamp'
```

Verify the ingress controller is installed and the `ingressClassName` matches.

## Next Steps

- [Upgrade runbook](./kubernetes-upgrade.md) — how to upgrade to a new release
- [Deployment topology matrix](./deployment-topology.md) — supported topologies and trade-offs
- [Docker deployment guide](./deployment.md) — Docker Compose path
