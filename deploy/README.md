# deploy/

This directory contains deployment artifacts for the Brama AI Community Platform.

## Contents

```
deploy/
├── build-and-push.sh              # Build all platform images and push to local registry
├── charts/
│   └── brama/                     # Helm umbrella chart
│       ├── Chart.yaml             # Chart metadata and sub-chart dependencies
│       ├── Chart.lock             # Locked sub-chart versions
│       ├── values.yaml            # Default values (all options documented)
│       ├── values-hetzner.yaml    # Hetzner CX32 production values (k3s single-node)
│       ├── values-prod.example.yaml  # Generic production values example
│       ├── values-k3s-dev.yaml    # Local k3s development values
│       ├── values-k3s-local-infra.yaml  # Local k3s with bundled infra
│       ├── charts/                # Bundled sub-chart archives (helm dependency update)
│       └── templates/             # Kubernetes resource templates
│           ├── _helpers.tpl       # Shared template helpers
│           ├── core/              # Core app deployment, service, scheduler, RBAC
│           ├── agents/            # Agent deployments, services, knowledge-worker
│           ├── litellm/           # LiteLLM deployment, service, configmap
│           ├── langfuse/          # Langfuse web + worker deployments, service
│           ├── openclaw/          # OpenClaw gateway deployment, service
│           ├── website/           # Website stub deployment, service, ingress
│           ├── jobs/              # Migration job (Helm hook)
│           ├── ingress.yaml       # Main ingress (core, litellm, langfuse, openclaw)
│           ├── serviceaccount.yaml
│           └── NOTES.txt
└── kubernetes/                    # Legacy Helm chart skeleton (superseded by charts/brama)
```

## build-and-push.sh

Builds all platform Docker images and pushes them to the local k3s registry at
`registry.localhost:5000`.

### Usage

```bash
# Build all 7 platform images
bash deploy/build-and-push.sh

# Build specific services only
bash deploy/build-and-push.sh core knowledge-agent

# Pin a version tag
VERSION=1.2.3 bash deploy/build-and-push.sh

# Use a different registry
REGISTRY=my-registry.example.com NAMESPACE=myapp bash deploy/build-and-push.sh
```

### Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `REGISTRY` | `registry.localhost:5000` | Registry host |
| `NAMESPACE` | `acp` | Registry namespace (image prefix) |
| `VERSION` | `latest` | Image tag |

### Services

| Service | Image name | Dockerfile |
|---------|-----------|------------|
| `core` | `acp/core` | `brama-core/docker/brama-core/Dockerfile` |
| `knowledge-agent` | `acp/knowledge-agent` | `brama-core/docker/knowledge-agent/Dockerfile` |
| `hello-agent` | `acp/hello-agent` | `brama-core/docker/hello-agent/Dockerfile` |
| `wiki-agent` | `acp/wiki-agent` | `brama-core/docker/wiki-agent/Dockerfile` |
| `news-maker-agent` | `acp/news-maker-agent` | `brama-core/docker/news-maker-agent/Dockerfile` |
| `dev-reporter-agent` | `acp/dev-reporter-agent` | `brama-core/docker/dev-reporter-agent/Dockerfile` |
| `dev-agent` | `acp/dev-agent` | `brama-core/docker/dev-agent/Dockerfile` |

### Verification

```bash
# List all images in the local registry
curl -s http://registry.localhost:5000/v2/_catalog

# Check tags for a specific image
curl -s http://registry.localhost:5000/v2/acp/core/tags/list
```

## Helm Chart

### Quick deploy (Hetzner CX32)

```bash
# Update sub-chart dependencies
helm dependency update ./deploy/charts/brama

# Deploy to k3s
helm upgrade --install brama ./deploy/charts/brama \
  --namespace brama \
  --create-namespace \
  -f deploy/charts/brama/values-hetzner.yaml \
  --wait \
  --timeout 15m
```

### Values files

| File | Purpose |
|------|---------|
| `values.yaml` | Defaults — all options with documentation |
| `values-hetzner.yaml` | Hetzner CX32 production (k3s, local registry, Traefik, cert-manager) |
| `values-prod.example.yaml` | Generic production template (external managed services) |
| `values-k3s-dev.yaml` | Local k3s development (minimal services) |

### Sub-chart dependencies

| Chart | Version | Condition |
|-------|---------|-----------|
| postgresql (Bitnami) | 15.x | `postgresql.enabled` |
| redis (Bitnami) | 19.x | `redis.enabled` |
| rabbitmq (Bitnami) | 15.x | `rabbitmq.enabled` |
| opensearch (opensearch-project) | 2.x | `opensearch.enabled` |

Update dependencies:

```bash
helm dependency update ./deploy/charts/brama
```

## Full deployment guide

See [`docs/guides/deployment/en/kubernetes-install.md`](../docs/guides/deployment/en/kubernetes-install.md)
for the complete step-by-step guide including:

- k3s installation on Hetzner VPS
- Local registry setup
- cert-manager + Let's Encrypt TLS
- Secret creation
- PostgreSQL data migration from Docker Compose
- Verification checklist
- Rollback procedure
