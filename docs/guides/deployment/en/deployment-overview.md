# Deployment Overview

The AI Community Platform supports two official deployment modes:

| Mode | Best for | Packaging |
|------|----------|-----------|
| **Docker** | Hobby production, single-node self-hosted installs, local development | Docker Compose + Makefile |
| **Kubernetes** | Cluster-native operators, managed databases, rolling upgrades | Helm chart (planned) |

Both modes share the same [deployment contract](./docker-deployment-contract.md): the same
configuration inputs, health expectations, migration behavior, and upgrade verification gates.

---

## Supported Topologies

### Docker (Supported)

```
Single host
  ├── Traefik (edge proxy)
  ├── Core platform + scheduler
  ├── LiteLLM (LLM proxy)
  ├── PostgreSQL, Redis, OpenSearch, RabbitMQ (bundled)
  └── Optional: agents, Langfuse, OpenClaw, Slides
```

All services run on one host. Stateful services are bundled as Docker volumes.

**Operator guides:**
- [Install](./docker-install.md)
- [Upgrade](./docker-upgrade.md)
- [Backup and Restore](./docker-backup-restore.md)
- [Troubleshooting](./docker-troubleshooting.md)
- [Deployment Contract](./docker-deployment-contract.md)

### Kubernetes (Planned)

```
Cluster
  ├── Core platform (Deployment, 1+ replicas)
  ├── Core scheduler (Deployment, 1 replica)
  ├── LiteLLM (Deployment)
  ├── Agents (Deployments)
  ├── Ingress (Traefik or nginx-ingress)
  ├── PostgreSQL (bundled or external managed)
  ├── Redis (bundled or external managed)
  └── Optional: Langfuse, OpenClaw, Slides
```

Stateful services may be replaced by managed external services (RDS, ElastiCache, etc.).

---

## Trade-offs

| Concern | Docker | Kubernetes |
|---------|--------|------------|
| Setup complexity | Low | High |
| Operational overhead | Low | Medium–High |
| Scaling | Single host | Multi-replica, horizontal |
| Stateful services | Bundled volumes | Bundled or external managed |
| Upgrade mechanism | `git pull` + `make up` | `helm upgrade` |
| Rollback | `git checkout` + `make up` | `helm rollback` |
| TLS | Manual (Traefik ACME or external) | Ingress controller + cert-manager |
| Suitable for | Hobby, small teams, single-node | Teams with existing Kubernetes infra |

---

## Shared Deployment Contract

Regardless of deployment mode, the following contract applies:

- **Configuration**: env vars via `.env.local` (Docker) or `values.yaml` (Kubernetes)
- **Secrets**: operator-managed, never committed to the repository
- **Migrations**: explicit commands, not implicit container startup side effects
- **Health**: every HTTP service exposes `GET /health` returning HTTP 200
- **Upgrade gates**: migration success → core health → worker health → public entrypoint health

See [docker-deployment-contract.md](./docker-deployment-contract.md) for the full contract.

---

## Local Development

Local development uses the same Docker Compose stack as the Docker self-hosted path.
See [docs/local-dev.md](../../../local-dev.md) for the local development guide.

The key difference from production:
- Dev defaults are used for secrets (change before going live)
- Source code is mounted as volumes for live reload
- E2E test containers run in parallel with isolated databases
