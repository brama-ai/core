# Local Development Runtime

This repository includes a local Docker Compose stack for validating routing, service boundaries, and the real OpenClaw runtime.

The stack is defined as a single Docker Compose project named `ai-community-platform`, so Docker Desktop will group all related containers together under one local environment.

## Prerequisites

- Docker Desktop (or Docker Engine + Compose plugin)
- Git
- Node.js 18+ (for E2E tests and CodeceptJS conventions tests)
- Make

## Quick Start (from scratch)

```bash
# 1. Clone
git clone https://github.com/nmdimas/ai-community-platform.git
cd ai-community-platform

# 2. Build and start the stack
make setup
make up

# 3. Install PHP dependencies
make install

# 4. Run database migrations
make migrate

# 5. Verify everything works
make test
```

After this, the platform is available at `http://localhost/` and the admin panel at `http://localhost/admin/login`.

## AI Agent Skills Setup

Shared skills live in `skills/` and need to be synced to agent-specific directories after cloning:

```bash
# Sync skills for Claude Code
make sync-skills

# Or sync for a specific agent
./scripts/sync-skills.sh claude
```

This copies skills from `skills/` (source of truth) into `.claude/skills/`. When editing skills, always edit in `skills/` and re-run `make sync-skills`.

## Topology

- `Traefik` is the only public entry layer.
- `core` is exposed at `http://localhost/`.
- `Traefik API` is exposed at `http://localhost:8080/api/` (local dev only, insecure mode).
- `admin-stub` is exposed at `http://localhost:8081/`.
- `openclaw` is exposed through `Traefik` at `http://localhost:8082/`.
- `openclaw` is also exposed directly at `http://localhost:18789/`.
- `postgres` is exposed at `localhost:5432`.
- `redis` is exposed at `localhost:6379`.
- `opensearch` is exposed at `http://localhost:9200/`.
- `rabbitmq` is exposed at `localhost:5672` and `http://localhost:15672/`.
- `litellm` is exposed at `http://localhost:4000/`.

Boundary notes:

- `core` remains the future home of the platform-owned runtime.
- `admin-stub` is a technical placeholder only.
- `openclaw` is the real local OpenClaw runtime.
- `litellm` is the local LLM proxy and debug gateway for all future model traffic.

## Start The Stack

Run from the repository root:

```bash
make setup
make up
```

If you want to run it in the foreground instead of detached mode:

```bash
docker compose up --build
```

`make setup` currently prepares:

- `core` by building the local PHP container
- `openclaw` by pulling the current runtime image and preparing its local state directory
- shared infra images such as `Traefik`, `admin-stub`, `Postgres`, `Redis`, `OpenSearch`, `RabbitMQ`, and `LiteLLM`

## Verify Routing

Open these URLs in a browser, or use `curl`:

```bash
curl http://localhost/
curl http://localhost:8081/
curl http://localhost:8082/
curl http://localhost:18789/
curl http://localhost:9200/
open http://localhost:15672/
curl http://localhost:4000/health
```

Expected results:

- `http://localhost/health` returns `{"status":"ok","service":"core-platform","version":"0.1.0"}`
- `http://localhost:8081/` returns the `admin-stub` page
- `http://localhost:8082/` returns the real `OpenClaw Control` UI through `Traefik`
- `http://localhost:18789/` returns the direct `OpenClaw Control` UI
- `http://localhost:9200/` returns the local `OpenSearch` node response
- `http://localhost:15672/` returns the `RabbitMQ` management UI login page
- `http://localhost:4000/health` returns the local `LiteLLM` proxy health response
  (it is expected to show zero healthy endpoints until upstream models are configured)

## Stop The Stack

```bash
make down
```

## OpenClaw Setup

For gateway token setup, provider API keys, and local OpenClaw state details, see:

- `docker/openclaw/README.md`

## Admin Panel

After running migrations, the admin panel is available at:

```
http://localhost/admin/login
```

Default credentials (development only):

| Field    | Value           |
|----------|-----------------|
| Username | `admin`         |
| Password | `test-password` |

Apply the migration before first use:

```bash
make migrate
```

> **Security notice:** The default credentials are seeded in the migration for local
> development only. Rotate the password before deploying to any shared or production
> environment.

## PHP Development

Run PHP tooling inside the core container (stack must be up):

```bash
make migrate     # Apply Doctrine migrations
make test        # Codeception unit + functional tests
make analyse     # PHPStan static analysis (level 8)
make cs-check    # PHP CS Fixer dry-run
make cs-fix      # PHP CS Fixer auto-fix
```

## Playwright E2E Tests

Requires Node.js locally and the Docker stack running:

```bash
make e2e
```

First run installs `@playwright/test` via `npm install`. Tests verify:
- `GET /health` through Traefik → `200 ok`
- Traefik API at `http://localhost:8080/api/http/services` → `core@docker` registered
- `http://localhost:8080/api/http/routers` → `core` router enabled

## Useful Commands

```bash
make help
make ps
make logs
make logs-traefik
make logs-core
make logs-litellm
```

## Notes

- Port `80` must be free on the host.
- The stack now includes local `Postgres`, `Redis`, `OpenSearch`, `RabbitMQ`, and `LiteLLM`.
- The stack also includes the real local `OpenClaw` runtime.
- All local LLM requests should go through `LiteLLM` at `http://localhost:4000/` so they can be debugged in one place.
- The local OpenClaw gateway token is stored in `docker/openclaw/.env`.
- The `core` container runs `PHP 8.5 + Symfony 7 + Composer`. Run `make install` after the first build to install dependencies.
- Default local credentials:
  - `Postgres`: database `ai_community_platform`, user `app`, password `app`
  - `RabbitMQ`: user `app`, password `app`
