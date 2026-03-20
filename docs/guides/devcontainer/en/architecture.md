# Devcontainer Architecture

## Overview

The devcontainer provides a fully configured development environment inside a
Docker container. It shares infrastructure services with the main Docker Compose
stack — no duplication.

```
┌─────────────────────────────────────────────────────────────┐
│                 One Docker Compose Project                   │
│                                                             │
│  compose.yaml                  .devcontainer/               │
│  ┌──────────────┐              docker-compose.yml           │
│  │ postgres     │              ┌──────────────┐             │
│  │ redis        │◄── networks ─┤ devcontainer │             │
│  │ opensearch   │   dev-edge   │   (Ubuntu)   │             │
│  │ rabbitmq     │   agents-    │              │             │
│  │ traefik      │   internal   │ PHP 8.5      │             │
│  │ litellm      │              │ Node.js 22   │             │
│  └──────────────┘              │ Python 3.12  │             │
│                                │ Go 1.24      │             │
│                                │ Composer     │             │
│                                │ Bun          │             │
│                                └──────────────┘             │
└─────────────────────────────────────────────────────────────┘
```

## How It Works

`devcontainer.json` merges two compose files into **one project**:

```json
"dockerComposeFile": ["../compose.yaml", "docker-compose.yml"]
```

- `compose.yaml` (root) — defines infrastructure: postgres, redis, opensearch,
  rabbitmq, traefik, litellm
- `.devcontainer/docker-compose.yml` — defines only the devcontainer service
  (and optionally codex)

The devcontainer joins the same Docker networks (`dev-edge`, `agents-internal`)
as infrastructure services. Apps inside the devcontainer reach services by
hostname: `postgres`, `redis`, `opensearch`, `rabbitmq`.

## Key Design Decisions

### Single source of infrastructure

Infrastructure is defined **once** in `compose.yaml`. The devcontainer does NOT
duplicate postgres, redis, etc. This means:

- One postgres instance, one redis instance — no resource waste
- Same init scripts (`docker/postgres/init/`) create all databases
- Same Docker volumes persist data between restarts
- Docker Desktop shows one compose project, not two

### No .env.local overrides

Apps use their default `.env` files which reference Docker hostnames:

```
DATABASE_URL=postgresql://app:app@postgres:5432/ai_community_platform
REDIS_URL=redis://redis:6379
```

Since the devcontainer is on the same Docker network, these hostnames resolve
correctly. No `.env.local` with `127.0.0.1` is needed.

### depends_on with health checks

The devcontainer waits for postgres and redis to be healthy before starting:

```yaml
depends_on:
  postgres:
    condition: service_healthy
  redis:
    condition: service_healthy
```

## Files

| File | Purpose |
|------|---------|
| `.devcontainer/devcontainer.json` | VS Code devcontainer config, merges compose files |
| `.devcontainer/docker-compose.yml` | Devcontainer service definition (no infra) |
| `.devcontainer/Dockerfile` | Ubuntu base with PHP, Node, Python, Go, Composer, Bun |
| `.devcontainer/post-create.sh` | Runs after container creation: installs deps, runs migrations, checks services |
| `compose.yaml` | Infrastructure services (shared with devcontainer) |
| `docker/postgres/init/` | SQL scripts that auto-create all databases and roles |

## Runtimes (pre-installed in Dockerfile)

| Runtime | Version |
|---------|---------|
| PHP | 8.5 (ondrej/php PPA) |
| Node.js | 22 LTS |
| Python | 3.12 (system) |
| Go | 1.24 |
| Composer | 2.x |
| Bun | 1.x |
| Claude Code | native installer |
| OpenCode | npm global |

## Infrastructure Services (from compose.yaml)

| Service | Host | Port | Credentials |
|---------|------|------|-------------|
| PostgreSQL 16 | `postgres` | 5432 | `app` / `app` |
| Redis 7 | `redis` | 6379 | no auth |
| OpenSearch 2.11 | `opensearch` | 9200 | no auth |
| RabbitMQ 3.13 | `rabbitmq` | 5672 / 15672 | `app` / `app` |
| Traefik 3.1 | `traefik` | 80 / 8080 | — |
| LiteLLM | `litellm` | 4000 | `dev-key` |

## Post-Create Lifecycle

When the devcontainer starts for the first time, `post-create.sh` runs:

1. Install OpenCode plugins
2. Wait for postgres DNS resolution (up to 60s)
3. Install PHP dependencies (`composer install`)
4. Run database migrations (core + knowledge-agent)
5. Install E2E test dependencies (Playwright)
6. Health check all infrastructure services
7. Print runtime versions

## Troubleshooting

### All services show FAIL

Services are part of the main compose stack. If they're down:

```bash
docker compose up -d postgres redis opensearch rabbitmq
```

### Docker not available inside devcontainer

Check that Docker-in-Docker feature is enabled in `devcontainer.json` and
the Docker socket is mounted in `docker-compose.yml`.

### DNS resolution fails for "postgres"

The devcontainer must be on the same Docker networks as infrastructure.
Check `.devcontainer/docker-compose.yml` has:

```yaml
networks:
  - dev-edge
  - agents-internal
```

### Port conflicts

If the main compose stack was already running before opening devcontainer,
there may be port conflicts. Stop the standalone stack first:

```bash
docker compose down
```

Then reopen in devcontainer — VS Code will start everything as one project.
