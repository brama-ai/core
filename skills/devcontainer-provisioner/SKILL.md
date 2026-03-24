---
name: devcontainer-provisioner
description: >
  Install missing software and start services on the fly inside the devcontainer
  without rebuilding. Use when a command fails with "not found", a Python import
  fails with "ModuleNotFoundError", a PHP class is missing, npm package is absent,
  or a service (PostgreSQL, Redis, OpenSearch, RabbitMQ) is not running.
  Triggers on: "not found", "ModuleNotFoundError", "No module named",
  "command not found", "could not find", "package not found", "install",
  "provision", "service not running", "connection refused".
---

# Devcontainer Provisioner

Install missing packages, libraries, and runtimes on the fly in the running
devcontainer. Restart stopped Docker Compose services. Make everything available
immediately without requiring a container rebuild.

## When to Use

- A command fails with `command not found` or `No such file or directory`
- Python import fails with `ModuleNotFoundError` or `No module named`
- PHP extension missing: `Class 'X' not found` or `Undefined function`
- Node package missing: `Cannot find module`
- A service is down: `connection refused` on PostgreSQL (5432), Redis (6379),
  OpenSearch (9200), or RabbitMQ (5672)
- User explicitly asks to install/provision something
- A pipeline step needs a tool not in the base image

## When NOT to Use

- Software is already installed (check first!)
- The request is about production deployment (out of scope)
- **Not running inside a devcontainer** (see environment detection below)

## Step 0 — Pre-flight Checks

**Before doing anything else**, run these checks in order. Stop at the first failure.

### 0a. Detect environment

```bash
# Returns 0 (true) inside devcontainer, 1 (false) on host
test -f /.dockerenv || test -n "$REMOTE_CONTAINERS" || test -n "$CODESPACES" || [[ "$PWD" == /workspaces/* ]]
```

If the check **fails** (we are on the host / local machine), **stop immediately**:

> This skill only works inside the devcontainer. Infrastructure services
> (PostgreSQL, Redis, OpenSearch, RabbitMQ) are managed by Docker Compose
> as sidecar containers of the devcontainer.
> To start the devcontainer: open the project in VS Code and use
> "Dev Containers: Reopen in Container", or run `devcontainer up`.

Do NOT attempt to install packages or start services on the host machine.

### 0b. Check Docker availability

```bash
docker info &>/dev/null
```

If Docker is **not available**, infrastructure services cannot be managed.
Report the issue clearly:

> Docker is not available inside this devcontainer. Without Docker,
> infrastructure services (PostgreSQL, Redis, OpenSearch, RabbitMQ)
> cannot be started — they run as Docker Compose sidecar containers.
>
> Possible causes:
> - Docker socket not mounted (check `.devcontainer/docker-compose.yml`
>   has `/var/run/docker.sock:/var/run/docker.sock`)
> - Docker-in-Docker feature not started (check `devcontainer.json`
>   has `ghcr.io/devcontainers/features/docker-in-docker:2`)
> - Container provider (Codespaces, etc.) doesn't support DinD
>
> Fix: rebuild the devcontainer with "Dev Containers: Rebuild Container"
> or ensure Docker socket is accessible.

You can still install **packages** (apt, pip, npm, composer) without Docker.
Only **service management** requires Docker.

### 0c. Quick health check (if Docker is available)

Run this to get a full picture before taking action:

```bash
echo "=== Infrastructure Services ==="
for svc in \
  "PostgreSQL:pg_isready -h postgres -U app -q" \
  "Redis:redis-cli -h redis ping" \
  "OpenSearch:curl -sf http://opensearch:9200" \
  "RabbitMQ:curl -sf http://rabbitmq:15672"; do
  name="${svc%%:*}"; cmd="${svc#*:}"
  if eval "$cmd" &>/dev/null; then echo "  [OK]   $name"
  else echo "  [FAIL] $name"; fi
done
```

If any service is down, restart it:

```bash
docker compose up -d <service>
```

If ALL services are down, restart everything:

```bash
docker compose up -d
```

### 0d. Check OpenCode provider wiring

OpenCode should have at least two configured providers visible from the
devcontainer. This confirms that `.env.local` is being forwarded correctly and
the coding agents have fallback capacity.

```bash
opencode auth list
```

Expected minimum:

- At least `2` provider entries (lines starting with `● `)
- Typical healthy output includes `Environment` providers such as
  `OpenRouter`, `MiniMax`, or `OpenCode`

If fewer than 2 providers are visible:

> OpenCode does not have enough configured providers inside the devcontainer.
> Check `.env.local`, verify `.devcontainer/docker-compose.yml` forwards it via
> `env_file`, and rerun `bash .devcontainer/post-start.sh` after recreating the
> container.

## Environment Summary

This devcontainer runs **Ubuntu (noble)** with these runtimes pre-installed:

| Runtime | Version | Manager |
|---------|---------|---------|
| PHP | 8.5 | `apt` (ondrej/php PPA) |
| Node.js | 22 LTS | `apt` (NodeSource) + `npm` |
| Python | 3.12 | system (`apt` for system pkgs, `pip` for libraries) |
| Go | 1.24 | `/usr/local/go/bin/go` |
| Composer | 2.x | `composer` |
| Bun | 1.x | `bun` |

Infrastructure services are defined in the root `compose.yaml` and shared with the
devcontainer via merged Docker Compose project (not duplicated):

| Service | Host | Port | Image |
|---------|------|------|-------|
| PostgreSQL 16 | `postgres` | 5432 | postgres:16-alpine |
| Redis 7 | `redis` | 6379 | redis:7-alpine |
| OpenSearch 2.11 | `opensearch` | 9200 | opensearchproject/opensearch:2.11.1 |
| RabbitMQ 3.13 | `rabbitmq` | 5672/15672 | rabbitmq:3.13-management-alpine |

Apps use Docker service hostnames (`postgres`, `redis`, `opensearch`, `rabbitmq`)
as defined in their `.env` files — no `.env.local` overrides needed.

See `references/runtime-map.md` for the full command reference.
See `references/service-map.md` for service management commands.
See `references/architecture.md` for compose merge model and path resolution.

## Workflow

### Step 1 — Identify What's Missing

Parse the error message or user request to determine:

1. **What** is missing: package name, library, extension, binary, service
2. **Which ecosystem**: apt (system), pip (Python), npm (Node), composer (PHP),
   go install (Go), PHP extension (apt php8.5-*), or a Docker Compose service

### Step 2 — Check If Already Installed

Before installing, verify the package isn't already present:

```bash
# System binary
which <binary> || command -v <binary>

# Python module
python3 -c "import <module>" 2>&1

# PHP extension
php -m | grep -i <extension>

# Node module (global)
npm list -g <package> 2>/dev/null

# apt package
dpkg -l | grep <package>

# Docker Compose service status
docker compose ps <service>

# Service connectivity
pg_isready -h postgres                    # PostgreSQL
redis-cli -h redis ping                   # Redis
curl -sf http://opensearch:9200 >/dev/null  # OpenSearch
```

If already installed, tell the user and stop. Do NOT reinstall.

### Step 3 — Install

Use the correct package manager. Always use non-interactive flags.

**System packages (apt):**
```bash
sudo apt-get update -qq && sudo apt-get install -y -qq <package>
```

**Python packages (pip):**
```bash
pip3 install --break-system-packages -q <package>
```
> `--break-system-packages` is required in this devcontainer because Python
> is the system Python (no virtualenv). This is intentional for dev use.

**PHP extensions:**
```bash
sudo apt-get update -qq && sudo apt-get install -y -qq php8.5-<extension>
```

**Node packages (global):**
```bash
npm install -g <package>
```

**Node packages (project-local):**
```bash
npm install <package>
# or for a specific app:
cd apps/<app> && npm install
```

**Composer packages:**
```bash
composer require <package>
# or install from existing lock:
composer install
```

**Go tools:**
```bash
go install <package>@latest
```

**Bun packages:**
```bash
bun add <package>
# or global:
bun add -g <package>
```

**Docker Compose services (restart a stopped service):**
```bash
docker compose up -d <service>
```

### Step 4 — Verify Installation

After installing, verify the package is available:

```bash
# Binaries
which <binary> && <binary> --version

# Python
python3 -c "import <module>; print(<module>.__version__)" 2>/dev/null \
  || python3 -c "import <module>; print('OK')"

# PHP
php -m | grep -i <extension>

# Node
node -e "require('<package>')" 2>/dev/null || npm list -g <package>

# Docker Compose services
docker compose ps <service>
pg_isready -h postgres        # PostgreSQL
redis-cli -h redis ping       # Redis
```

If verification fails, check error output and report to the user.

### Step 5 — Start Services (if needed)

If the missing piece is a Docker Compose service that is down:

```bash
# Restart a specific service
docker compose up -d <service>

# Restart all infrastructure services
docker compose up -d postgres redis opensearch rabbitmq

# View service logs for debugging
docker compose logs <service> --tail 50
```

See `references/service-map.md` for full details including database operations.

### Step 6 — Report

Tell the user:
- What was installed and the version
- How to use it
- Whether it persists (it does NOT survive container rebuild)
- If they want persistence, suggest adding it to `.devcontainer/Dockerfile`
  or `.devcontainer/post-create.sh`

## Multi-Package Installs

When multiple packages are needed (e.g., a Python project's requirements.txt):

```bash
# Python project dependencies
pip3 install --break-system-packages -q -r apps/<app>/requirements.txt

# Node project dependencies
cd apps/<app> && npm install

# PHP project dependencies
composer install
```

## Persistence Guidance

Packages installed at runtime do NOT survive a devcontainer rebuild.
If the user wants persistence, guide them to add the install command to
the appropriate file:

| Package Type | Persist In |
|-------------|-----------|
| apt packages | `.devcontainer/Dockerfile` (in an `apt-get install` block) |
| pip packages | `.devcontainer/Dockerfile` or app-specific `requirements.txt` |
| npm global tools | `.devcontainer/Dockerfile` (in `npm install -g` line) |
| npm project deps | App's `package.json` (already persisted) |
| PHP extensions | `.devcontainer/Dockerfile` (in `apt-get install php8.5-*` block) |
| composer deps | `composer.json` (already persisted) |
| Infrastructure services | `compose.yaml` (already persisted) |
| Database init scripts | `docker/postgres/init/` SQL files (already persisted) |

## Common Scenarios

### Service is down (connection refused)

```bash
# Check which services are running
docker compose ps

# Restart the specific service
docker compose up -d <service>

# If a service fails to start, check logs
docker compose logs <service> --tail 50
```

### Python app needs dependencies (e.g., news-maker-agent)

```bash
pip3 install --break-system-packages -q psycopg2-binary
pip3 install --break-system-packages -q -r apps/news-maker-agent/requirements.txt
```

### PostgreSQL database missing for an agent

Databases are auto-created by init scripts in `docker/postgres/init/`.
If you need to manually create one:

```bash
psql -h postgres -U postgres -c "CREATE DATABASE <dbname>;"
psql -h postgres -U postgres -c "CREATE USER <user> WITH PASSWORD '<pass>';" 2>/dev/null || true
psql -h postgres -U postgres -c "GRANT ALL ON DATABASE <dbname> TO <user>;"
psql -h postgres -U postgres -c "ALTER DATABASE <dbname> OWNER TO <user>;"
```

### pgvector extension needed

pgvector must be installed inside the postgres container:

```bash
docker compose exec postgres \
  sh -c "apk add --no-cache postgresql16-pgvector"
psql -h postgres -U postgres -d <dbname> -c "CREATE EXTENSION IF NOT EXISTS vector;"
```

### Doctrine/Alembic migrations

```bash
# PHP (Doctrine — Core)
php apps/brama-core/bin/console doctrine:migrations:migrate --no-interaction

# PHP (Doctrine — Knowledge Agent)
php apps/knowledge-agent/bin/console doctrine:migrations:migrate --no-interaction

# Python (Alembic — news-maker-agent)
cd apps/news-maker-agent && alembic upgrade head
```

### Playwright/E2E test dependencies

```bash
cd tests/e2e && npm install && npx playwright install --with-deps
```

## Error Patterns Quick Reference

| Error Pattern | Action |
|--------------|--------|
| `bash: <cmd>: command not found` | `sudo apt-get install -y <package>` or check `references/runtime-map.md` |
| `ModuleNotFoundError: No module named '<pkg>'` | `pip3 install --break-system-packages <pkg>` |
| `Connection refused` on port 5432 | `docker compose up -d postgres` |
| `Connection refused` on port 6379 | `docker compose up -d redis` |
| `Connection refused` on port 9200 | `docker compose up -d opensearch` |
| `Connection refused` on port 5672 | `docker compose up -d rabbitmq` |
| `could not translate host name "postgres"` | Service DNS not ready — `docker compose up -d postgres` |
| `FATAL: database "<db>" does not exist` | `psql -h postgres -U postgres -c "CREATE DATABASE <db>;"` |
| `FATAL: role "<user>" does not exist` | `psql -h postgres -U postgres -c "CREATE USER <user> WITH PASSWORD '<pass>';"` |
| `Class 'X' not found` (PHP) | `sudo apt-get install -y php8.5-<extension>` |
| `Cannot find module '<pkg>'` (Node) | `npm install <pkg>` |

## Rules

- Always check if software is already installed BEFORE attempting install
- Use non-interactive flags (`-y`, `-q`, `--no-interaction`) for all installs
- Use `sudo` for apt-get (current user is `vscode`)
- Use `--break-system-packages` for pip3 (no virtualenv in devcontainer)
- Infrastructure services (postgres, redis, opensearch, rabbitmq) are Docker Compose
  containers — use `docker compose` commands, NOT `sudo pg_ctlcluster` or `sudo redis-server`
- Service hostnames are Docker DNS names: `postgres`, `redis`, `opensearch`, `rabbitmq`
- Never modify the Dockerfile, docker-compose.yml, or post-create.sh without user's explicit request
- Report what was installed and mention it won't persist after rebuild
- If multiple related packages are needed, install them in a single command
- For database operations, use `psql -h postgres -U postgres`
