# Docker Self-Hosted Install Guide

This guide covers installing the AI Community Platform on a single host using Docker Compose.
This is the supported path for hobby production and simple self-hosted installs.

For the full deployment contract (service topology, env vars, secrets, health, migrations), see
[docker-deployment-contract.md](./docker-deployment-contract.md).

## Supported Topology

```
Internet → Traefik (port 80) → Application Services
             ├── core (PHP/Symfony)         — main platform
             ├── core-scheduler             — background tasks
             ├── litellm (Python)           — LLM routing proxy
             └── [optional agents and add-ons]

Infrastructure (bundled):
  PostgreSQL 16, Redis 7, OpenSearch 2.11, RabbitMQ 3.13

Optional add-ons (separate compose fragments):
  Langfuse (LLM observability), OpenClaw (Telegram bot), Slides
```

## Prerequisites

- Ubuntu 22.04+ or any Linux with Docker support
- Docker Engine 24+ with Compose plugin v2
- Git
- Make
- Domain name with DNS pointing to the server IP (optional but recommended for production)
- SSH access (key-based recommended)

## Step 1: Install Docker

```bash
curl -fsSL https://get.docker.com | sh
```

Verify:

```bash
docker --version
docker compose version
```

## Step 2: Clone the Repository

```bash
mkdir -p /root/app
cd /root/app
git clone https://github.com/nmdimas/ai-community-platform.git
cd ai-community-platform
```

## Step 3: Configure Secrets

```bash
cp .env.local.example .env.local
nano .env.local
```

Required variables:

| Variable | Required | Description |
|----------|----------|-------------|
| `OPENROUTER_API_KEY` | Yes (one LLM key) | API key from [openrouter.ai](https://openrouter.ai/) |
| `TELEGRAM_BOT_TOKEN` | Optional | Token from Telegram @BotFather (needed for OpenClaw add-on) |
| `OPENCLAW_GATEWAY_TOKEN` | Auto-generated | Leave empty — `make bootstrap` generates it |
| `LANGFUSE_PUBLIC_URL` | For production | `https://langfuse.yourdomain.org` |

## Step 4: Configure Production Domains

Create `compose.override.yaml` (gitignored) with your domain settings:

```bash
cat > compose.override.yaml << 'EOF'
services:
  core:
    environment:
      EDGE_AUTH_LOGIN_BASE_URL: https://yourdomain.org
      EDGE_AUTH_COOKIE_DOMAIN: .yourdomain.org

  langfuse-web:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.yourdomain.org
      NEXTAUTH_URL: https://langfuse.yourdomain.org

  langfuse-worker:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.yourdomain.org
      NEXTAUTH_URL: https://langfuse.yourdomain.org
EOF
```

Replace `yourdomain.org` with your actual domain. Skip this step for local-only installs.

## Step 5: Bootstrap Secrets

```bash
make bootstrap
```

This reads `.env.local` and:
- Generates the OpenClaw gateway token (if not set)
- Writes `docker/openclaw/.env`
- Creates `.local/openclaw/state/openclaw.json`

Run `make bootstrap` once before first startup. Re-run only when the release explicitly requires
secret redistribution.

## Step 6: Build and Start the Stack

```bash
make setup    # Pull/build all images and install dependencies
make up       # Start the full stack in the background
```

`make up` runs `docker compose ... up --build -d` for the full supported bundle.

## Step 7: Initialize Databases

```bash
make litellm-db-init    # Create LiteLLM database (idempotent)
```

## Step 8: Run Migrations

Run migrations for the services you have installed:

```bash
make migrate               # Core platform
make knowledge-migrate     # Knowledge agent (if installed)
make dev-reporter-migrate  # Dev reporter agent (if installed)
make dev-agent-migrate     # Dev agent (if installed)
make news-migrate          # News maker agent (if installed)
```

## Step 9: Verify

Check all services are running:

```bash
make ps
```

Check health endpoints:

```bash
curl -sf http://localhost/health && echo "core OK"
curl -sf http://localhost:8083/health && echo "knowledge-agent OK"
curl -sf http://localhost:8085/health && echo "hello-agent OK"
```

Or check all at once:

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo "OK" || echo "FAIL"
done
```

## DNS and Routing

Create A records pointing your domain and subdomains to the server IP:

| Subdomain | Service |
|-----------|---------|
| `yourdomain.org` | Core platform |
| `langfuse.yourdomain.org` | Langfuse (if enabled) |
| `openclaw.yourdomain.org` | OpenClaw (if enabled) |
| `litellm.yourdomain.org` | LiteLLM |
| `slides.yourdomain.org` | Slides (if enabled) |
| `traefik.yourdomain.org` | Traefik dashboard |

## TLS

TLS is not configured by default. Options:

- **Traefik built-in Let's Encrypt (ACME)**: add ACME configuration to `docker/traefik/traefik.yml`
- **Cloudflare proxy**: enable Cloudflare orange-cloud for automatic TLS
- **External nginx**: terminate TLS upstream and proxy to port 80

## Security Checklist

Before going live, change these development defaults:

- [ ] `APP_SECRET` in `apps/core/.env` and each agent `.env`
- [ ] `EDGE_AUTH_JWT_SECRET` in `apps/core/.env`
- [ ] `EDGE_AUTH_COOKIE_DOMAIN` — set to `.yourdomain.org`
- [ ] Admin password: `docker compose exec core php bin/console security:hash-password`
- [ ] Langfuse password — change in Langfuse UI account settings
- [ ] Configure TLS
- [ ] Restrict OpenSearch, RabbitMQ, Redis ports to localhost (firewall)
- [ ] Restrict Traefik dashboard access

## Default Credentials (Change Before Production)

| Surface | Default credentials |
|---------|---------------------|
| Core admin / edge login | `admin` / `test-password` |
| Langfuse app login | `admin@local.dev` / `test-password` |
| LiteLLM UI | `admin` / `dev-key` |

## Next Steps

- [Docker Upgrade Guide](./docker-upgrade.md)
- [Docker Backup and Restore](./docker-backup-restore.md)
- [Docker Troubleshooting](./docker-troubleshooting.md)
- [Deployment Contract](./docker-deployment-contract.md)
