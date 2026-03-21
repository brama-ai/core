# Local Development

Local Docker Compose stack for Brama Agent Platform with OpenClaw runtime, Telegram integration,
and all platform services.

## Prerequisites

- Docker Desktop (or Docker Engine + Compose plugin)
- Git
- Make
- Node.js 18+ (only for E2E tests)

## Quick Start (from scratch)

```bash
# 1. Clone
git clone https://github.com/nmdimas/ai-community-platform.git
cd ai-community-platform

# 2. Configure secrets (one file, one time)
cp .env.local.example .env.local
#    Edit .env.local — add your LLM key and Telegram bot token

# 3. Bootstrap (distributes secrets to all the right places)
make bootstrap

# 4. Build and start the stack
make setup
make up

# 5. Ensure LiteLLM DB exists (idempotent)
make litellm-db-init

# 6. Run database migrations
make migrate

# 7. Verify
make test
```

The repository slug is still `ai-community-platform` for now. The product brand and public domain
are `Brama Agent Platform` and `brama.dev`.

After this:
- Brama platform: `http://localhost/`
- Admin: `http://localhost/admin/login` (`admin` / `test-password`)
- Wiki Agent: `http://localhost/wiki`
- Wiki Admin: `http://localhost/wiki-admin/login` (`admin` / `test-password`)
- Wiki Agent direct debug: `http://localhost:8090/wiki`
- OpenClaw UI: `http://localhost:8082/`
- Langfuse UI: `http://localhost:8086/` (edge login + JWT cookie)
- LiteLLM API: `http://localhost:4000/`

## Default Credentials (Local Dev Only)

Use these only for local development:

| Surface | URL | Default credentials |
|---------|-----|---------------------|
| Brama admin login | `http://localhost/admin/login` | `admin` / `test-password` |
| Wiki admin login | `http://localhost/wiki-admin/login` | `admin` / `test-password` |
| Edge login for tools | `http://localhost/edge/auth/login` | `admin` / `test-password` |
| Langfuse app login | `http://localhost:8086/` | `admin@local.dev` / `test-password` |
| OpenClaw Control UI | `http://localhost:8082/` | Gateway token from `docker/openclaw/.env` (`OPENCLAW_GATEWAY_TOKEN`) |
| Traefik dashboard | `http://localhost:8080/dashboard/` | No auth in local dev |
| LiteLLM API | `http://localhost:4000/` | `Authorization: Bearer dev-key` |
| LiteLLM UI login | `http://localhost:4000/ui/login` | `admin` / `dev-key` |

Примітка: `dev-key` задається як `LITELLM_MASTER_KEY` у `compose.yaml` (local default).

## What Goes in .env.local

Copy `.env.local.example` → `.env.local` and fill in:

| Variable | Required | Where to get |
|----------|----------|--------------|
| `OPENROUTER_API_KEY` | One LLM key required (OpenClaw + LiteLLM) | [openrouter.ai](https://openrouter.ai/) |
| `OPENAI_API_KEY` | (alternative) | [platform.openai.com](https://platform.openai.com/) |
| `ANTHROPIC_API_KEY` | (alternative) | [console.anthropic.com](https://console.anthropic.com/) |
| `GOOGLE_API_KEY` | Optional for `google/*` models in OpenCode / OmO | [aistudio.google.com](https://aistudio.google.com/) |
| `MINIMAX_API_KEY` | Optional for `minimax/*` models in OpenCode / OmO | MiniMax platform |
| `OPENCODE_API_KEY` | Optional for `opencode/*` and `opencode-go/*` models | OpenCode account |
| `TELEGRAM_BOT_TOKEN` | Optional | Telegram @BotFather → `/newbot` |
| `OPENCLAW_GATEWAY_TOKEN` | Auto-generated | Leave empty for auto-generation |

`make bootstrap` reads this file and:
- Generates gateway token (if not set)
- Writes `docker/openclaw/.env`
- Creates `.local/openclaw/state/openclaw.json` with LLM provider + Telegram channel config
- Runs OpenClaw onboard (if stack is already up)

## Telegram Setup

If you provided `TELEGRAM_BOT_TOKEN` in `.env.local`, the channel is already configured after `make bootstrap`.

### Create a Bot (if you don't have one yet)

1. Open Telegram → find **@BotFather**
2. Send `/newbot`
3. Choose a name and username (must end with `bot`)
4. Copy the token → paste into `.env.local`
5. Run `make bootstrap` again

### Pair Your Account

After the stack is up:

1. Message your bot `/start` in Telegram
2. The bot replies with a **pairing code**
3. Approve:

```bash
docker compose exec openclaw-cli openclaw pairing approve telegram <CODE>
```

4. Send a message — the bot responds.

### Add Bot to a Community Group

1. Add the bot to your Telegram group
2. Promote to **Admin** (needs **Delete messages** permission for streaming)
3. Mention: `@your_bot привіт`

The bot only reacts to `@mentions` in groups.

### Telegram Channel Settings

These are set automatically by `make bootstrap`:

| Setting | Value | Description |
|---------|-------|-------------|
| `dmPolicy` | `pairing` | New DM users must be approved |
| `groupPolicy` | `open` | Anyone in a group can interact |
| `requireMention` | `true` | Responds only to `@mentions` in groups |
| `streaming` | `partial` | Streams responses (edits message live) |

To change settings manually:

```bash
docker compose exec openclaw-cli openclaw config set channels.telegram.<key> <value>
docker compose restart openclaw-gateway
```

## Topology

| Service | URL | Notes |
|---------|-----|-------|
| Brama core platform | `http://localhost/` | Symfony app via Traefik |
| Core E2E runtime | `http://localhost:18080/` | E2E Core runtime (`ai_community_platform_test`) |
| Knowledge Agent E2E | `http://localhost:18083/` | E2E Knowledge Agent (`knowledge_agent_test`) |
| News-Maker Agent E2E | `http://localhost:18084/` | E2E News-Maker Agent (`news_maker_agent_test`) |
| Hello Agent E2E | `http://localhost:18085/` | E2E Hello Agent (stateless) |
| Dev Reporter Agent | `http://localhost:8087/` | Pipeline run history + Telegram notifications |
| Dev Reporter Admin | `http://localhost:8087/admin/pipeline` | Pipeline runs admin panel |
| Wiki Agent | `http://localhost/wiki` | Standalone TypeScript wiki app + grounded chat |
| Wiki Admin | `http://localhost/wiki-admin` | Wiki-specific admin surface |
| Wiki Agent direct debug | `http://localhost:8090/wiki` | Dedicated Traefik wiki entrypoint |
| OpenClaw Gateway E2E | `http://localhost:28789/` | E2E OpenClaw Gateway |
| Brama admin panel | `http://localhost/admin/login` | `admin` / `test-password` |
| OpenClaw UI | `http://localhost:8082/` | Via Traefik + edge login |
| Langfuse UI | `http://localhost:8086/` | Via Traefik + edge login |
| OpenClaw direct | `http://localhost:18789/` | Direct container port |
| Traefik dashboard / API | `http://localhost:8080/dashboard/`, `http://localhost:8080/api/` | Insecure mode, local only |
| Postgres | `localhost:5432` | `app` / `app` / `ai_community_platform` + `*_test` DBs |
| Redis | `localhost:6379` | |
| OpenSearch | `http://localhost:9200/` | |
| RabbitMQ | `localhost:5672`, `http://localhost:15672/` | `app` / `app` |
| LiteLLM | `http://localhost:4000/` | LLM proxy (`Authorization: Bearer dev-key`) |

## LiteLLM Model Preset

`docker/litellm/config.yaml` includes the first OpenRouter model preset aligned with OpenClaw:

- `minimax/minimax-m2.5` → `openrouter/minimax/minimax-m2.5`
- `gpt-4o-mini` → compatibility alias to the same OpenRouter Minimax model

## LiteLLM Credentials And Ops

- Gateway API key (for clients/agents): `dev-key` (`LITELLM_MASTER_KEY`)
- Provider key (for LiteLLM -> OpenRouter): `OPENROUTER_API_KEY` from `.env.local`
- Full operational runbook: `docs/features/litellm/ua/litellm.md`

### Troubleshooting: `Authentication Error, Not connected to DB!`

This error on `http://localhost:4000/ui/login` means LiteLLM cannot use Postgres DB metadata.

Fix:

```bash
docker compose up -d postgres
make litellm-db-init
docker compose logs --tail=100 litellm
```

Then retry login (`admin` / `dev-key` in local dev).

### Existing Stack Upgrade: enable LiteLLM UI login

If your Postgres volume was created before LiteLLM DB bootstrap was added, create the DB once:

```bash
make litellm-db-init
```

After that, `http://localhost:4000/ui/login` can use DB-backed login flow.

## OpenClaw Manual Setup

If you prefer manual setup over `make bootstrap`:

### Gateway Token

```bash
openssl rand -hex 32
# Put in docker/openclaw/.env as OPENCLAW_GATEWAY_TOKEN=<token>
```

### Control UI Login

Open `http://localhost:8082/`. Paste the gateway token from `docker/openclaw/.env`.

## Edge Login for Tools

1. Open any protected tools URL, for example `http://localhost:8086/` or `http://localhost:8082/`.
2. You will be redirected to `/edge/auth/login`.
3. Sign in with admin credentials:
   - username: `admin`
   - password: `test-password`
4. After login, Traefik stores JWT cookie `ACP_EDGE_TOKEN` and redirects to requested tool URL.

Exception: OpenClaw messenger webhook routes under `http://localhost:8082/api/channels/*` are intentionally not behind edge login, so bot/webhook traffic works without browser cookies.

## Change Default Credentials After Setup

### 1) Core Admin + Edge Login (`admin` / `test-password`)

Both `/admin/login` and `/edge/auth/login` use the same `admin_users` record in core DB.

1. Generate a new password hash:

```bash
docker compose exec core php bin/console security:hash-password --no-interaction '<NEW_ADMIN_PASSWORD>' 'App\Security\AdminUser'
```

2. Copy `Password hash` from command output and update DB:

```bash
docker compose exec postgres psql -U app -d ai_community_platform \
  -c "UPDATE admin_users SET password = '<PASTE_PASSWORD_HASH>' WHERE username = 'admin';"
```

3. (Optional) Change username too:

```bash
docker compose exec postgres psql -U app -d ai_community_platform \
  -c "UPDATE admin_users SET username = '<NEW_ADMIN_USERNAME>' WHERE username = 'admin';"
```

### 2) Langfuse (`admin@local.dev` / `test-password`)

- Preferred: log into Langfuse UI and change password in account settings.
- Alternative (reset local bootstrap credentials): change `LANGFUSE_INIT_USER_EMAIL` and `LANGFUSE_INIT_USER_PASSWORD` in `compose.yaml`, then recreate Langfuse data volumes.

```bash
docker compose down
docker volume rm \
  ai-community-platform_langfuse-postgres-data \
  ai-community-platform_langfuse-clickhouse-data \
  ai-community-platform_langfuse-redis-data \
  ai-community-platform_langfuse-minio-data
docker compose up -d langfuse-postgres langfuse-clickhouse langfuse-redis langfuse-minio langfuse-minio-init langfuse-web langfuse-worker
```

Warning: volume reset removes local Langfuse data (traces, prompts, datasets, media).

### 3) OpenClaw Gateway Token

```bash
# 1) Generate a new token
openssl rand -hex 32

# 2) Put it into docker/openclaw/.env as OPENCLAW_GATEWAY_TOKEN=<NEW_TOKEN>

# 3) Apply token in OpenClaw runtime config
docker compose exec openclaw-cli openclaw config set gateway.auth.token <NEW_TOKEN>

# 4) Restart gateway
docker compose restart openclaw-gateway
```

## Langfuse App Login

After edge login, sign in to Langfuse itself with bootstrap user:

- email: `admin@local.dev`
- password: `test-password`

### Onboard (LLM Provider)

```bash
docker compose exec openclaw-cli openclaw onboard
```

Or non-interactive:

```bash
docker compose exec openclaw-cli openclaw onboard \
  --non-interactive --accept-risk --mode local --flow quickstart \
  --gateway-auth token --gateway-bind lan --gateway-port 18789 \
  --gateway-token '<OPENCLAW_GATEWAY_TOKEN>' \
  --openrouter-api-key 'sk-or-...' \
  --skip-channels --skip-skills
```

### Verify

```bash
docker compose logs openclaw-gateway --tail 20
# Should show: [telegram] [default] starting provider (@your_bot)
```

## PHP Development

```bash
make migrate     # Doctrine migrations
make test        # Codeception unit + functional
make analyse     # PHPStan level 8
make cs-check    # PHP CS Fixer dry-run
make cs-fix      # PHP CS Fixer auto-fix
```

### Dev Reporter Agent

```bash
make dev-reporter-setup    # Build container + install dependencies (first time)
make dev-reporter-migrate  # Run Doctrine migrations for pipeline_runs table
make dev-reporter-test     # Run Codeception tests
make dev-reporter-analyse  # PHPStan level 8
make dev-reporter-cs-check # PHP CS Fixer dry-run
make dev-reporter-cs-fix   # PHP CS Fixer auto-fix
```

## E2E Tests

```bash
make e2e-prepare # Provision test DBs, RabbitMQ vhost, run migrations, register agents
make e2e         # Playwright + CodeceptJS against full E2E stack (all agents isolated)
make e2e-smoke   # Smoke/REST-only E2E
make e2e-cleanup # Stop all E2E containers
```

`make e2e` and `make e2e-smoke` automatically run `make e2e-prepare` first.
E2E tests run against duplicate containers with isolated data stores (`_test` databases,
odd Redis DBs, `/test` RabbitMQ vhost). See `docs/agent-requirements/e2e-testing.md` for details.

## AI Agent Skills

```bash
make sync-skills  # Sync skills/ → .claude/skills/
```

Edit in `skills/` (source of truth), then re-run sync.

## Useful Commands

```bash
make help             # All targets
make bootstrap        # Configure secrets from .env.local
make setup            # Build/pull containers
make up / make down   # Start / stop
make ps               # Running services
make logs             # Follow all logs
make logs-openclaw    # OpenClaw gateway logs
make logs-langfuse    # Langfuse web/worker logs
make up-observability # Start only Langfuse services (if stack is already up)
```

## Notes

- Port `80` must be free on the host.
- All LLM requests go through LiteLLM at `http://localhost:4000/`.
- Secrets: `.env.local` (your input) → `docker/openclaw/.env` + `.local/openclaw/state/` (generated, gitignored).
