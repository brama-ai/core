# Service Map

Infrastructure services are defined in the root `compose.yaml` and shared with the
devcontainer via a merged Docker Compose project. The devcontainer joins the same
`dev-edge` and `agents-internal` networks, so services are reachable by hostname.

## General Commands

```bash
# Check all service statuses
docker compose ps

# Restart a specific service
docker compose up -d <service>

# Restart all infrastructure services
docker compose up -d postgres redis opensearch rabbitmq

# View logs
docker compose logs <service> --tail 50

# Stop a service
docker compose stop <service>
```

---

## PostgreSQL 16

**Host:** `postgres` (Docker DNS)
**Port:** 5432
**Superuser:** `postgres` (no password, trust auth from devcontainer network)
**Image:** postgres:16-alpine
**Data:** `postgres-data` Docker volume
**Init scripts:** `docker/postgres/init/` (auto-run on first start)

### Connectivity Check

```bash
pg_isready -h postgres -U app -d ai_community_platform
```

### Start / Restart

```bash
docker compose up -d postgres
```

### Create Database and User

Databases are auto-created by init scripts in `docker/postgres/init/`.
To manually create:

```bash
# Create user (idempotent with || true)
psql -h postgres -U postgres -c "CREATE USER <user> WITH PASSWORD '<pass>';" 2>/dev/null || true

# Create database
psql -h postgres -U postgres -c "CREATE DATABASE <dbname> OWNER <user>;"

# Grant privileges
psql -h postgres -U postgres -c "GRANT ALL ON DATABASE <dbname> TO <user>;"
```

### Known Databases in This Project

| Database | User | Password | Used By |
|----------|------|----------|---------|
| `ai_community_platform` | `app` | `app` | Core (PHP/Symfony) |
| `ai_community_platform_test` | `app` | `app` | Core E2E tests |
| `knowledge_agent` | `knowledge_agent` | `knowledge_agent` | Knowledge Agent |
| `knowledge_agent_test` | `knowledge_agent` | `knowledge_agent` | Knowledge Agent tests |
| `news_maker_agent` | `news_maker_agent` | `news_maker_agent` | News-Maker Agent |
| `dev_reporter_agent` | `dev_reporter_agent` | `dev_reporter_agent` | Dev Reporter Agent |
| `dev_agent` | `dev_agent` | `dev_agent` | Dev Agent |
| `litellm` | `app` | `app` | LiteLLM proxy |

All created automatically by `docker/postgres/init/` scripts on first postgres start.

### Extensions

pgvector must be installed inside the postgres container:

```bash
docker compose exec postgres \
  sh -c "apk add --no-cache postgresql16-pgvector"

psql -h postgres -U postgres -d <dbname> -c "CREATE EXTENSION IF NOT EXISTS vector;"
psql -h postgres -U postgres -d <dbname> -c "CREATE EXTENSION IF NOT EXISTS pg_trgm;"
psql -h postgres -U postgres -d <dbname> -c "CREATE EXTENSION IF NOT EXISTS uuid-ossp;"
```

### Run Migrations

```bash
# Doctrine (PHP/Symfony — Core)
php apps/brama-core/bin/console doctrine:migrations:migrate --no-interaction

# Doctrine (PHP/Symfony — Knowledge Agent)
php apps/knowledge-agent/bin/console doctrine:migrations:migrate --no-interaction

# Alembic (Python — news-maker-agent, run from app dir)
cd apps/news-maker-agent && alembic upgrade head
```

### Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Connection refused` on 5432 | `docker compose up -d postgres` |
| `could not translate host name "postgres"` | Service not running — start it with command above |
| `FATAL: database "X" does not exist` | Check init scripts ran: `docker compose logs postgres` |
| `FATAL: role "X" does not exist` | `psql -h postgres -U postgres -c "CREATE USER X WITH PASSWORD 'X';"` |
| `permission denied to create extension` | Use superuser: `psql -h postgres -U postgres -d <db> -c "CREATE EXTENSION ..."` |
| Data corrupted / need fresh start | `docker compose down -v` then `up -d` (destroys data!) |

---

## Redis 7

**Host:** `redis` (Docker DNS)
**Port:** 6379
**Auth:** None (no password)
**Image:** redis:7-alpine
**Persistence:** AOF enabled (`--appendonly yes`)
**Data:** `redis-data` Docker volume

### Connectivity Check

```bash
redis-cli -h redis ping
# Expected: PONG
```

### Start / Restart

```bash
docker compose up -d redis
```

### Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Connection refused` on 6379 | `docker compose up -d redis` |
| `Could not connect to Redis` | Same as above |
| Redis using too much memory | `redis-cli -h redis FLUSHALL` to clear |

---

## OpenSearch 2.11

**Host:** `opensearch` (Docker DNS)
**Port:** 9200
**Security plugin:** Disabled
**Image:** opensearchproject/opensearch:2.11.1
**Data:** `opensearch-data` Docker volume

### Connectivity Check

```bash
curl -sf http://opensearch:9200
```

### Start / Restart

```bash
docker compose up -d opensearch
```

### Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Connection refused` on 9200 | `docker compose up -d opensearch` |
| OOM / crash on start | OpenSearch needs `vm.max_map_count=262144` on host |
| Index issues | `curl -X DELETE http://opensearch:9200/<index>` to reset |

---

## RabbitMQ 3.13

**Host:** `rabbitmq` (Docker DNS)
**AMQP Port:** 5672
**Management UI:** 15672
**User:** `app` / **Password:** `app`
**Image:** rabbitmq:3.13-management-alpine
**Data:** `rabbitmq-data` Docker volume

### Connectivity Check

```bash
docker compose exec rabbitmq rabbitmq-diagnostics -q ping
```

### Start / Restart

```bash
docker compose up -d rabbitmq
```

### Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Connection refused` on 5672 | `docker compose up -d rabbitmq` |
| Queue stuck / need reset | `docker compose exec rabbitmq rabbitmqctl reset` |

---

## Docker (Docker-in-Docker)

The devcontainer has Docker CLI access via mounted Docker socket.
This is used for managing the platform's Docker Compose stack.

```bash
# Check Docker is available
docker info

# Check devcontainer compose services
docker compose ps

# Start the full production-like stack (separate from devcontainer services)
docker compose up -d

# View logs
docker compose logs <service> --tail 50
```

**Note:** Docker commands may fail if the Docker socket is not mounted
or the Docker-in-Docker feature didn't start properly.
Restart the devcontainer if Docker commands fail.

---

## Service Startup Sequence

Services start automatically via `depends_on` in `.devcontainer/docker-compose.yml`.
The devcontainer waits for postgres and redis to be healthy before starting.

If services need manual restart:

```bash
# 1. Start all infrastructure
docker compose up -d postgres redis opensearch rabbitmq

# 2. Wait for postgres
until pg_isready -h postgres -U app -q 2>/dev/null; do sleep 1; done

# 3. Run migrations
php apps/brama-core/bin/console doctrine:migrations:migrate --no-interaction 2>/dev/null || true
php apps/knowledge-agent/bin/console doctrine:migrations:migrate --no-interaction 2>/dev/null || true
```

Init scripts in `docker/postgres/init/` auto-create all databases and roles
on first postgres container start — no manual DB creation needed.
