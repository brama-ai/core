# Change: Migrate production deployment from Docker Compose to k3s on Hetzner VPS

## Why

The platform currently runs on a Hetzner CX32 VPS (4 vCPU / 8 GB RAM) via Docker Compose, deployed
through a GitHub Actions workflow that SSHes in and runs `docker compose up -d --build`. While this
works, it lacks proper orchestration: no automated health-based restarts, no rolling updates, no
declarative state management, and no migration lifecycle hooks.

A Helm chart already exists at `deploy/charts/ai-community-platform/` (created in the
`add-dual-docker-kubernetes-deployment` change) but has never been deployed to a real cluster. The
chart also has significant gaps — it only covers core, scheduler, and 3 agents out of the full stack
of ~15 services.

Moving to k3s on the same VPS gives us Kubernetes orchestration with minimal overhead (~500 MB RAM),
while a local container registry eliminates the need for external registry infrastructure.

## What Changes

- Install k3s on existing Hetzner CX32 VPS (single-node cluster)
- Deploy a local container registry inside k3s for image storage
- Extend the Helm chart to cover the full docker-compose stack:
  - Add LiteLLM deployment + service + configmap templates (currently values-only, no templates)
  - Add knowledge-worker deployment (background consumer, same image as knowledge-agent)
  - Add wiki-agent, dev-reporter-agent, dev-agent to the agents config
  - Add OpenSearch and RabbitMQ as optional sub-chart dependencies
  - Add Langfuse observability stack (web + worker)
  - Add OpenClaw gateway deployment
  - Extend migration job to support agent-specific database migrations
  - Update ingress template for Langfuse and OpenClaw host rules
- Create `values-hetzner.yaml` with tight resource budgets for 8 GB RAM
- Switch ingress from nginx to k3s built-in Traefik
- Migrate PostgreSQL data from Docker volume to k3s-managed PostgreSQL
- Update CI/CD workflow (`deploy.yml`) to use `helm upgrade` instead of `docker compose up`
- Create `deploy/build-and-push.sh` for building and pushing images to local registry

## Impact

- Affected specs:
  - Adds capability `k3s-deployment` — full-stack k3s single-node deployment with Helm chart, local registry, Traefik ingress, cert-manager TLS, resource budgeting, data migration, and CI/CD automation
  - Adds capability `self-hosted-deployment` — defines the self-hosted VPS deployment topology with k3s as the orchestration layer
- Affected code:
  - `deploy/charts/ai-community-platform/` — chart templates, values, Chart.yaml
  - `.github/workflows/deploy.yml` — deployment workflow
  - `deploy/build-and-push.sh` — new build script
- Affected infrastructure:
  - Hetzner VPS will be reconfigured from Docker Compose to k3s
  - PostgreSQL data migration required (pg_dumpall → restore)
  - DNS configuration unchanged (same VPS IP)
- Risks:
  - 8 GB RAM is tight for full stack — requires careful resource tuning
  - Downtime during migration (Docker stop → k3s install → deploy)
  - OpenSearch on 512 MB heap may be slow for large indices
