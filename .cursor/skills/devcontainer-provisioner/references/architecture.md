# Devcontainer Architecture Reference

Quick reference for the devcontainer provisioner skill.

## Compose Merge Model

```
devcontainer.json:
  "dockerComposeFile": ["../compose.yaml", "docker-compose.yml"]
```

- `compose.yaml` — infrastructure (postgres, redis, opensearch, rabbitmq, traefik, litellm)
- `.devcontainer/docker-compose.yml` — devcontainer + codex only (NO infra)
- Result: one merged Docker Compose project

## Networks

The devcontainer joins the same networks as infrastructure:

```yaml
# .devcontainer/docker-compose.yml
networks:
  - dev-edge         # external-facing services
  - agents-internal  # internal agent communication
```

This is why `postgres`, `redis`, `opensearch`, `rabbitmq` resolve by hostname
inside the devcontainer — they share the same Docker DNS.

## Environment Detection

```bash
# Are we inside the devcontainer?
test -f /.dockerenv || test -n "$REMOTE_CONTAINERS" || test -n "$CODESPACES" || [[ "$PWD" == /workspaces/* ]]

# Is Docker available?
docker info &>/dev/null
```

## Service Health Checks

```bash
pg_isready -h postgres -U app -q           # PostgreSQL
redis-cli -h redis ping                     # Redis
curl -sf http://opensearch:9200             # OpenSearch
curl -sf http://rabbitmq:15672              # RabbitMQ
```

## Service Recovery

```bash
# Restart one service
docker compose up -d <service>

# Restart all infrastructure
docker compose up -d postgres redis opensearch rabbitmq

# Check logs
docker compose logs <service> --tail 50

# Nuclear option (destroys data)
docker compose down -v && docker compose up -d
```

## Path Resolution

When compose files are merged, all relative paths resolve from the **project root**
(where `compose.yaml` lives), NOT from `.devcontainer/`.

```yaml
# .devcontainer/docker-compose.yml
build:
  context: .devcontainer     # → /project/.devcontainer/
  dockerfile: Dockerfile     # → /project/.devcontainer/Dockerfile
volumes:
  - .:/workspaces/...        # → /project/ (root)
```

## Database Init

Databases are auto-created by `docker/postgres/init/` SQL scripts on first
postgres start. No manual DB creation needed. Scripts run in alphabetical order:

1. `01_create_roles.sql` — creates agent roles (knowledge_agent, news_maker_agent, etc.)
2. `02_create_databases.sql` — creates agent databases
3. `03_create_test_databases.sql` — creates `_test` databases for E2E

## Post-Create Sequence

`post-create.sh` runs once after container creation:

1. OpenCode plugins install
2. Wait for postgres DNS (up to 60s)
3. `composer install`
4. Doctrine migrations (core + knowledge-agent)
5. Playwright install
6. Health check (PostgreSQL, Redis, OpenSearch, RabbitMQ)
7. Print runtime versions

## Common Issues

| Issue | Cause | Fix |
|-------|-------|-----|
| `ENOENT: Dockerfile` | Wrong build context path | `context: .devcontainer` (not `.`) |
| Two compose projects in Docker Desktop | Old devcontainer had its own infra | Delete old volumes, rebuild |
| Port conflict on 5432/6379 | Standalone compose running alongside | `docker compose down` first |
| `could not translate host name "postgres"` | Devcontainer not on correct network | Check `networks:` in docker-compose.yml |
| Services all FAIL in post-create | Postgres not healthy yet | `depends_on` with `service_healthy` |

## Full Documentation

- EN: `docs/guides/devcontainer/en/architecture.md`
- UA: `docs/guides/devcontainer/ua/architecture.md`
