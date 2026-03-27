# Agent Storage Provisioning

## Overview

Each agent declares its storage requirements in the Agent Card's `storage` section. When an admin installs an agent, the platform provisions the required resources (database, Redis DB, OpenSearch indices) before the agent can be enabled.

## Agent Card `storage` Section

The `storage` key is optional. If present, it declares what infrastructure the agent needs:

```json
{
  "name": "my-agent",
  "version": "1.0.0",
  "storage": {
    "postgres": {
      "db_name": "my_agent",
      "user": "my_agent",
      "password": "my_agent"
    },
    "redis": {
      "db_number": 1
    },
    "opensearch": {
      "collections": ["chunks", "pages"]
    }
  }
}
```

### Postgres

| Field | Type | Required | Constraint |
|-------|------|----------|------------|
| `db_name` | string | yes | `/^[a-z][a-z0-9_]*$/` |
| `user` | string | yes | `/^[a-z][a-z0-9_]*$/` |
| `password` | string | yes | non-empty |
| `startup_migration.enabled` | boolean | yes (for Postgres-backed agents) | must be `true` |
| `startup_migration.mode` | string | yes (for Postgres-backed agents) | use `best_effort` |
| `startup_migration.command` | string | yes (for Postgres-backed agents) | non-empty migration command |

Provisioning creates: user (CREATE USER), database (CREATE DATABASE), GRANT ALL PRIVILEGES.

### Redis

| Field | Type | Required | Constraint |
|-------|------|----------|------------|
| `db_number` | integer | yes | 0-15 |

Redis databases exist implicitly. Provisioning verifies connectivity and records the assignment.

### OpenSearch

| Field | Type | Required | Constraint |
|-------|------|----------|------------|
| `collections` | string[] | yes | non-empty, each `/^[a-z][a-z0-9_-]*$/` |

Index names are prefixed with the agent name: `{agent_name}_{collection}` (e.g., `knowledge_agent_chunks`).

## Provisioning Lifecycle

1. Admin clicks **Install** on the agent
2. Core checks `installed_at` — if null and Agent Card has `storage`:
   - **PostgresInstallStrategy**: CREATE USER + CREATE DATABASE (idempotent)
   - **RedisInstallStrategy**: verify DB number reachable
   - **OpenSearchInstallStrategy**: PUT index for each collection (idempotent)
3. Core calls agent's `POST /api/v1/internal/migrate` endpoint
4. Agent runs its Doctrine migrations (schema + seed data)
5. Core marks `installed_at = now()` in registry
6. Admin can enable the installed agent (`POST /api/v1/internal/agents/{name}/enable`)

Discovery/registration alone does **not** provision storage. Provisioning is intentionally deferred to the **Install** action to avoid side effects from passive discovery.

Re-enabling a previously installed agent **skips provisioning** (installed_at already set).

## Agent Migration Endpoint

Agents that declare `storage.postgres` **must** expose:

```
POST /api/v1/internal/migrate
Header: X-Platform-Internal-Token: {token}
Response: {"status": "ok", "output": "..."} or {"status": "error", "error": "..."}
```

This endpoint runs `doctrine:migrations:migrate --no-interaction` (or equivalent).

## Startup Migration On Container Restart

For Postgres-backed agents, migration command must also run on each container start in best-effort mode.
This keeps schema up to date after code updates when operators do container restart.

Recommended pattern:

```sh
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
```

After pulling updated code, run:

```bash
docker compose restart <agent-service>
```

If startup migration fails, container startup should continue (`best_effort`), and failure should be logged.

## Docker Compose Environment Variables

Agent storage credentials come from compose.yaml environment vars:

```yaml
my-agent:
  environment:
    DATABASE_URL: postgresql://my_agent:my_agent@postgres:5432/my_agent
    REDIS_URL: redis://redis:6379/1
    OPENSEARCH_URL: http://opensearch:9200
    OPENSEARCH_INDEX: my_agent_chunks
```

This enables multi-instance deployments with different credentials per environment.

## Scheduled Jobs

Agents can declare periodic or one-shot tasks in their manifest under `scheduled_jobs`. These are registered into the platform's central scheduler during install and removed during uninstall.

See [docs/features/scheduler/en/scheduler.md](../features/scheduler/en/scheduler.md) for the full manifest format, retry policy, and admin UI documentation.

```json
{
  "scheduled_jobs": [
    {
      "name": "daily-sync",
      "skill_id": "my_agent.sync",
      "cron_expression": "0 0 * * *",
      "payload": {},
      "max_retries": 3,
      "retry_delay_seconds": 60,
      "timezone": "UTC"
    }
  ]
}
```

Scheduled jobs do **not** require any storage provisioning — they are stored in the platform's `scheduled_jobs` table in core's database.

## Deprovisioning Strategy

Storage cleanup happens on explicit uninstall (`DELETE /api/v1/internal/agents/{name}`):

- Postgres: drops primary DB, test DB, and role
- Redis: flushes configured DB
- OpenSearch: deletes managed indices

Disabling an agent (`POST /api/v1/internal/agents/{name}/disable`) does not deprovision storage.
