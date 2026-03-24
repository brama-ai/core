# Production Deployment Guide

## Overview

Brama Agent Platform runs as a Docker Compose stack on a single VPS. Deployment is automated via
GitHub Actions on push to `main`. This guide covers initial server setup, configuration, and
ongoing operations.

## Prerequisites

- Ubuntu 22.04+ (or any Linux with Docker support)
- Docker Engine 24+ with Compose plugin v2
- Git
- Make
- Domain name with DNS pointing to the server IP (optional but recommended)
- SSH access (key-based recommended)

## Architecture

```
Internet → Traefik (port 80) → Services
             ├── core (PHP/Symfony)         — main platform
             ├── knowledge-agent (PHP)      — knowledge base
             ├── hello-agent (PHP)          — greeting agent
             ├── news-maker-agent (Python)  — news aggregation
             ├── wiki-agent (Node.js)       — wiki + grounded chat
             ├── dev-agent (PHP)            — dev assistant
             ├── dev-reporter-agent (PHP)   — pipeline reporter
             ├── langfuse-web (Next.js)     — LLM observability
             ├── openclaw-gateway (Node.js) — Telegram bot
             ├── litellm (Python)           — LLM routing proxy
             └── traefik dashboard
Infrastructure:
  PostgreSQL 16, Redis 7, OpenSearch 2.11, RabbitMQ 3.13
  Langfuse: ClickHouse, MinIO, dedicated Postgres + Redis
```

## Initial Server Setup

### 1. Install Docker

```bash
curl -fsSL https://get.docker.com | sh
```

### 2. Clone Repository

```bash
mkdir -p /root/app
cd /root/app
git clone <workspace-repo>
cd brama-workspace
```

### 3. Create Environment File

```bash
cp .env.local.example .env.local
nano .env.local
```

Required variables:

| Variable | Required | Description |
|----------|----------|-------------|
| `OPENROUTER_API_KEY` | Yes (one LLM key) | API key from [openrouter.ai](https://openrouter.ai/) |
| `TELEGRAM_BOT_TOKEN` | Optional | Token from Telegram @BotFather |
| `OPENCLAW_GATEWAY_TOKEN` | Auto-generated | Leave empty for auto-generation |
| `LANGFUSE_PUBLIC_URL` | For production | `https://langfuse.brama.dev` |

### 4. Create Compose Override (Domain Config)

Production domains are configured via `compose.override.yaml` (gitignored):

```bash
cat > compose.override.yaml << 'EOF'
services:
  core:
    environment:
      EDGE_AUTH_LOGIN_BASE_URL: https://brama.dev

  langfuse-web:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.brama.dev
      NEXTAUTH_URL: https://langfuse.brama.dev

  langfuse-worker:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.brama.dev
      NEXTAUTH_URL: https://langfuse.brama.dev
EOF
```

Adjust these examples if you use a different production domain than `brama.dev`.

### 5. Bootstrap & Start

```bash
make bootstrap    # Distributes secrets to all services
make setup        # Builds all Docker images
make up           # Starts the full stack
make litellm-db-init  # Initialize LiteLLM database
make migrate      # Run database migrations
```

### 6. Verify

```bash
# Check all services are running
docker compose -f compose.yaml -f compose.core.yaml \
  $(for f in compose.agent-*.yaml compose.langfuse.yaml compose.openclaw.yaml; do [ -f "$f" ] && echo -n "-f $f "; done) ps

# Test health endpoints
curl -s http://localhost/health
curl -s http://localhost:8083/health    # knowledge-agent
curl -s http://localhost:8085/health    # hello-agent
```

## Domain & Routing

Traefik routes traffic by hostname. Each compose file defines dual `Host()` rules that work both locally and in production:

| Service | Production URL | Local URL |
|---------|---------------|-----------|
| Brama core platform | `https://brama.dev` | `http://localhost` |
| Langfuse | `https://langfuse.brama.dev` | `http://langfuse.localhost` |
| OpenClaw | `https://openclaw.brama.dev` | `http://openclaw.localhost` |
| LiteLLM | `https://litellm.brama.dev` | `http://litellm.localhost` |
| Traefik | `https://traefik.brama.dev` | `http://traefik.localhost` |

**DNS**: Create A records pointing `brama.dev` and the subdomains `langfuse.`, `openclaw.`,
`litellm.`, and `traefik.` to the server IP.

**TLS**: Not yet configured. Options:
- Traefik built-in Let's Encrypt (ACME)
- External reverse proxy (Cloudflare, nginx)

## GitHub Actions Auto-Deploy

Pushes to `main` trigger automatic deployment via `.github/workflows/deploy.yml`.

### Setup GitHub Secrets

In your repository Settings → Environments → `production`:

| Secret | Value |
|--------|-------|
| `SSH_HOST` | Server IP (e.g., `46.62.135.86`) |
| `SSH_PORT` | SSH port (default: `22`) |
| `SSH_USER` | `root` |
| `SSH_PRIVATE_KEY` | Ed25519 private key content |

### How It Works

1. Detects which services changed (by analyzing modified files)
2. SSHs to server
3. Runs `git fetch && git checkout origin/main`
4. Builds and restarts only changed services: `docker compose up -d --build --no-deps <services>`

### Manual Deploy

Trigger via GitHub Actions UI → "Run workflow" → optionally specify services (comma-separated, or `all`).

Or SSH manually:

```bash
ssh root@your-server
cd /root/app/brama-workspace
git pull origin main
docker compose -f compose.yaml -f compose.core.yaml \
  $(for f in compose.agent-*.yaml compose.langfuse.yaml compose.openclaw.yaml; do [ -f "$f" ] && echo -n "-f $f "; done) \
  up -d --build
```

## Configuration Files

### Files in Git (with dev defaults)

| File | What to change for production |
|------|-------------------------------|
| `apps/brama-core/.env` | `APP_SECRET`, `EDGE_AUTH_JWT_SECRET` |
| `apps/*/env` | `APP_SECRET` per agent |
| `docker/litellm/config.yaml` | Model definitions (uses `OPENROUTER_API_KEY` from env) |

### Files NOT in Git (server-only)

| File | Purpose | Created by |
|------|---------|------------|
| `.env.local` | API keys, tokens | Manual |
| `compose.override.yaml` | Domain-specific config | Manual |
| `docker/openclaw/.env` | Gateway + Telegram tokens | `make bootstrap` |
| `.local/openclaw/state/openclaw.json` | OpenClaw runtime config | `make bootstrap` |

## Database Migrations

```bash
# Core platform
make migrate

# Per-agent (if applicable)
make knowledge-migrate
make dev-reporter-migrate
make news-migrate
```

## Monitoring & Health

### Health Endpoints

Every agent exposes `GET /health`:

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo " OK" || echo " FAIL"
done
```

### Langfuse (LLM Observability)

- URL: `https://langfuse.brama.dev`
- Login: edge auth → Langfuse app login
- Default: `admin@local.dev` / `test-password`

### Logs

```bash
# All services
docker compose logs -f --tail 100

# Specific service
docker compose logs -f core
docker compose logs -f openclaw-gateway
docker compose logs -f litellm
```

### OpenSearch (Structured Logs)

```bash
curl -s 'http://localhost:9200/platform_logs_*/_search?size=5&sort=@timestamp:desc' | jq '.hits.hits[]._source'
```

## Security Checklist

For production, change these dev defaults:

- [ ] `APP_SECRET` in `apps/brama-core/.env` and each agent `.env`
- [ ] `EDGE_AUTH_JWT_SECRET` in `apps/brama-core/.env`
- [ ] `EDGE_AUTH_COOKIE_DOMAIN` — set to `.brama.dev` for cross-subdomain cookies
- [ ] Admin password — change via `docker compose exec core php bin/console security:hash-password`
- [ ] Langfuse password — change in Langfuse UI account settings
- [ ] Configure TLS (Let's Encrypt or Cloudflare)
- [ ] Restrict OpenSearch, RabbitMQ, Redis ports to localhost (firewall)
- [ ] Restrict Traefik dashboard access

## External Agents

To add an externally maintained agent to the deployment:

```bash
# Clone the agent repository into projects/
make external-agent-clone repo=https://github.com/your-org/my-agent name=my-agent

# Review and adjust the compose fragment
nano compose.fragments/my-agent.yaml

# Configure agent secrets
cp projects/my-agent/.env.local.example projects/my-agent/.env.local
nano projects/my-agent/.env.local

# Start the agent
make external-agent-up name=my-agent

# Trigger discovery
make agent-discover
```

External agent checkouts (`projects/`) and compose fragments (`compose.fragments/*.yaml`) are
gitignored and operator-local. They are not committed to the platform repository.

See `docs/guides/external-agents/en/onboarding.md` for the full guide.

## Troubleshooting

### Service won't start

```bash
docker compose logs <service-name> --tail 50
```

### Database connection issues

```bash
docker compose exec postgres pg_isready
docker compose exec postgres psql -U app -d ai_community_platform -c "SELECT 1"
```

### OpenClaw not responding to Telegram

```bash
docker compose logs openclaw-gateway --tail 30
# Check if webhook is set:
docker compose exec openclaw-cli openclaw channels status
```

### LiteLLM "Not connected to DB"

```bash
make litellm-db-init
docker compose restart litellm
```
