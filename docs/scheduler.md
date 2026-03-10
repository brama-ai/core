# Central Scheduler

## Overview

The platform includes a centralized job scheduler that allows any agent to register periodic or one-shot tasks. The scheduler runs as a long-lived Symfony Command (`scheduler:run`) inside the `core-scheduler` Docker service and invokes agent skills via the A2A protocol.

## Architecture

- **Scheduler process**: `php bin/console scheduler:run` — polls the database every 10 seconds
- **Persistence**: Job state is stored in the `scheduled_jobs` PostgreSQL table — survives restarts
- **Invocation**: Jobs are executed via `A2AClient::invoke($skillId, $payload, ...)` — same path as regular A2A calls
- **Concurrency**: `SELECT ... FOR UPDATE SKIP LOCKED` prevents duplicate execution across multiple scheduler instances
- **Graceful shutdown**: Handles `SIGTERM`/`SIGINT` via `SignalableCommandInterface`

## Manifest Integration

Agents declare scheduled jobs in their `manifest.json` under the `scheduled_jobs` key:

```json
{
  "name": "my-agent",
  "version": "1.0.0",
  "scheduled_jobs": [
    {
      "job_name": "crawl_pipeline",
      "skill_id": "my_agent.trigger_crawl",
      "cron": "0 */4 * * *",
      "payload": {},
      "max_retries": 3,
      "retry_delay_seconds": 120,
      "timezone": "UTC"
    }
  ]
}
```

### Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `job_name` | string | ✓ | — | Unique name within the agent (max 128 chars) |
| `skill_id` | string | ✓ | — | A2A skill ID to invoke |
| `cron` | string | — | null | Crontab expression (null = one-shot job) |
| `payload` | object | — | `{}` | Arguments passed to the skill |
| `max_retries` | integer | — | 3 | Max retry attempts before dead-lettering |
| `retry_delay_seconds` | integer | — | 60 | Seconds between retry attempts |
| `timezone` | string | — | `"UTC"` | Timezone for cron expression evaluation |

### Cron Expression Format

Standard 5-field crontab format: `minute hour day-of-month month day-of-week`

Examples:
- `* * * * *` — every minute
- `0 * * * *` — every hour
- `0 */4 * * *` — every 4 hours
- `0 12 * * 1-5` — weekdays at noon
- `@daily` — once a day (alias)
- `@hourly` — once an hour (alias)

Powered by [`dragonmantank/cron-expression`](https://github.com/dragonmantank/cron-expression).

## Lifecycle Integration

Jobs are automatically managed as part of the agent lifecycle:

| Event | Action |
|-------|--------|
| `install` | Register all `scheduled_jobs` from manifest (upsert) |
| `uninstall` | Delete all jobs for the agent |
| `enable` | Enable all jobs for the agent |
| `disable` | Disable all jobs for the agent |

## Retry & Dead-Letter Policy

1. On failure: `retry_count` is incremented, `next_run_at` is set to `now() + retry_delay_seconds`
2. When `retry_count >= max_retries`: job is disabled (`enabled = false`) and logged as dead-lettered
3. Dead-lettered jobs can be re-enabled manually via the Admin UI

## Catch-Up Policy

If the scheduler was stopped and `next_run_at` is in the past:
- The job runs **once** immediately
- `next_run_at` is computed from the current time (no replay of missed runs)

## Admin UI

Navigate to `/admin/scheduler` to view all scheduled jobs.

| Column | Description |
|--------|-------------|
| Agent | Agent that registered the job |
| Job | Job name |
| Skill | A2A skill ID |
| Cron | Cron expression (or `—` for one-shot) |
| Next Run | Scheduled next execution time |
| Last Run | Last execution time |
| Status | `completed` / `failed` / `—` |
| Retry | `current/max` retry count |
| Enabled | Toggle button |
| Actions | "Run Now" button for manual trigger |

### Manual Trigger

Click **▶ Зараз** to set `next_run_at = now()`. The scheduler will pick it up within 10 seconds.

### Enable/Disable

Click the toggle button (✓/✗) to enable or disable a specific job without affecting other jobs of the same agent.

## Docker Service

The scheduler runs as a separate service in `compose.core.yaml`:

```yaml
core-scheduler:
  command: ["php", "bin/console", "scheduler:run"]
  restart: unless-stopped
  depends_on:
    - core
```

The `unless-stopped` restart policy ensures the scheduler recovers automatically from crashes.

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/admin/scheduler` | Admin UI page |
| `POST` | `/api/v1/internal/scheduler/{id}/run` | Trigger job immediately |
| `POST` | `/api/v1/internal/scheduler/{id}/toggle` | Enable/disable job |

Both API endpoints require `ROLE_ADMIN` authentication.

## Database Schema

```sql
CREATE TABLE scheduled_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    agent_name VARCHAR(64) NOT NULL,
    job_name VARCHAR(128) NOT NULL,
    skill_id VARCHAR(128) NOT NULL,
    payload JSONB NOT NULL DEFAULT '{}',
    cron_expression VARCHAR(64) DEFAULT NULL,
    next_run_at TIMESTAMPTZ NOT NULL,
    last_run_at TIMESTAMPTZ DEFAULT NULL,
    last_status VARCHAR(32) DEFAULT NULL,
    retry_count INTEGER NOT NULL DEFAULT 0,
    max_retries INTEGER NOT NULL DEFAULT 3,
    retry_delay_seconds INTEGER NOT NULL DEFAULT 60,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_scheduled_jobs_agent_job UNIQUE (agent_name, job_name)
);

CREATE INDEX idx_scheduled_jobs_enabled_next_run ON scheduled_jobs (enabled, next_run_at);
```
