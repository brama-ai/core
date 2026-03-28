## Context

The platform has a Helm umbrella chart (`brama-core/deploy/charts/brama`) with Bitnami sub-charts
for PostgreSQL, Redis, RabbitMQ, and OpenSearch. A `values-k3s-dev.yaml` profile targets local
Rancher Desktop k3s. Makefile targets (`k8s-build`, `k8s-load`, `k8s-secrets`, `k8s-deploy`,
`k8s-setup`, `k8s-status`) automate the build-load-deploy cycle. The devcontainer uses
Docker-outside-of-Docker to access the host's Docker daemon and `rdctl` for k3s image import.

This validation change does not introduce new code — it defines the acceptance criteria and
operational runbook that prove the existing Helm charts and devcontainer integration work correctly.

## Goals / Non-Goals

- **Goals**:
  - Define a repeatable 5-stage validation flow (cluster → infra → core → agent → runbook)
  - Provide concrete success signals and failure inspection commands for every stage
  - Produce a bilingual operator runbook that a new developer can follow without tribal knowledge
  - Cover all components enabled in `values-k3s-dev.yaml`: core, core-scheduler, migrations,
    hello-agent, news-maker-agent, PostgreSQL, Redis, RabbitMQ, Traefik ingress

- **Non-Goals**:
  - Production deployment validation (covered by `migrate-to-k3s-hetzner`)
  - CI/CD pipeline integration for k3s tests (covered by `add-k3s-test-workflow`)
  - OpenSearch in-cluster validation (disabled in k3s-dev profile)
  - Automated test scripts (this is a manual operator validation path)

## Decisions

- **Five-stage validation model**: Cluster → Infrastructure → Core → Agent → Runbook.
  Each stage has clear entry criteria (previous stage passes) and exit criteria (documented
  success signals). This mirrors the dependency order: cluster must be healthy before infra
  can run, infra must be healthy before core can connect, etc.

- **Makefile as the primary interface**: Operators use `make k8s-setup` for initial deployment
  and `make k8s-status` for observation. Individual targets (`k8s-build`, `k8s-load`, etc.)
  allow surgical re-runs. This avoids introducing new tooling.

- **`rdctl shell` for image import**: Local images are built with Docker, then piped into k3s
  containerd via `rdctl shell sudo k3s ctr images import -`. This avoids needing a registry
  for local development.

- **Secrets via `kubectl create secret generic`**: The `k8s-secrets` target generates random
  APP_SECRET and JWT secrets and constructs DATABASE_URL, REDIS_URL, RABBITMQ_URL from
  Helm release naming conventions. This is appropriate for local dev but not for production.

- **Traefik ingress with `core.localhost`**: k3s ships with Traefik. The ingress routes
  `core.localhost` to the core service. Operators add a `/etc/hosts` entry for browser access.
  Port-forward is the fallback for environments where `/etc/hosts` modification is not desired.

- **Agent discovery via Kubernetes labels**: The core pod uses RBAC (Role + RoleBinding) to
  list Services with label `ai.platform.agent=true`. Agent Services are created by the Helm
  chart with this label automatically. Validation confirms the label exists and core can
  reach the agent via cluster DNS.

## Component Interactions

```
Rancher Desktop (host)
  └── k3s cluster
       ├── kube-system (Traefik, CoreDNS, local-path-provisioner)
       └── brama namespace
            ├── brama-postgresql (Bitnami sub-chart, PVC on local-path)
            ├── brama-redis-master (Bitnami sub-chart, PVC on local-path)
            ├── brama-rabbitmq (Bitnami sub-chart, PVC on local-path)
            ├── brama-migrate-N (Job, pre-upgrade hook)
            ├── brama-core (Deployment, envFrom: brama-core-secrets)
            ├── brama-core-scheduler (Deployment, scheduler:run)
            ├── brama-agent-hello (Deployment, label: ai.platform.agent=true)
            ├── brama-agent-newsmaker (Deployment, label: ai.platform.agent=true)
            └── brama (Ingress, Traefik, core.localhost)

Devcontainer
  └── Docker-outside-of-Docker feature
       ├── docker build → local images
       └── rdctl shell → k3s ctr images import
```

## Risks / Trade-offs

- **Risk**: Rancher Desktop version differences may cause `rdctl` or k3s behavior changes.
  **Mitigation**: Document minimum Rancher Desktop version; known issues table in runbook.

- **Risk**: Apple Silicon users may hit `exec format error` if images are built on x86.
  **Mitigation**: Document in known issues; images must be built on same architecture.

- **Risk**: `local-path` StorageClass may not be available if Rancher Desktop provisioner is broken.
  **Mitigation**: Validation step 1.3 checks system pods; runbook documents `kubectl get storageclass`.

- **Trade-off**: Manual validation vs automated test suite. Manual is chosen for this proposal
  because it establishes the baseline; automation is deferred to `add-k3s-test-workflow`.

## Open Questions

None — all decisions are resolved based on the current Helm chart structure and devcontainer config.
