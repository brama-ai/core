# Central Scheduler

## Overview

The platform provides a centralized, persistent job scheduler in `apps/core`. Any agent can declare scheduled jobs in its `manifest.json`, and the platform will execute them reliably via the A2A protocol with retry, dead-letter, and admin visibility.

## Architecture

- **`ScheduledJobRepository`** — DBAL-based CRUD for the `scheduled_jobs` PostgreSQL table
- **`CronExpressionHelper`** — Wrapper around `dragonmantank/cron-expression` for cron parsing
- **`AsyncA2ADispatcher`** — Non-blocking HTTP client using ReactPHP for concurrent A2A dispatch
- **`SchedulerService`** — Orchestrates tick logic: find due jobs, dispatch concurrently via AsyncA2ADispatcher, handle retries
- **`SchedulerRunCommand`** (`scheduler:run`) — Long-running Symfony command, polls every 10 seconds, with DB keepalive
- **`core-scheduler`** Docker service — Runs the command as a separate process

## Database Table

The `scheduled_jobs` table stores all job definitions:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `agent_name` | VARCHAR(64) | Agent that owns the job |
| `job_name` | VARCHAR(128) | Unique job name within the agent |
| `skill_id` | VARCHAR(128) | A2A skill to invoke |
| `payload` | JSONB | Input payload for the skill |
| `cron_expression` | VARCHAR(64) | Standard cron expression (NULL = one-shot) |
| `next_run_at` | TIMESTAMPTZ | When the job should next run |
| `last_run_at` | TIMESTAMPTZ | When the job last ran |
| `last_status` | VARCHAR(32) | `completed`, `failed`, `dead_letter` |
| `retry_count` | INTEGER | Current retry attempt count |
| `max_retries` | INTEGER | Maximum retries before dead-lettering |
| `retry_delay_seconds` | INTEGER | Seconds to wait between retries |
| `enabled` | BOOLEAN | Whether the job is active |
| `timezone` | VARCHAR(64) | Timezone for cron evaluation |

Unique constraint: `(agent_name, job_name)` — registration is idempotent.
Index: `(enabled, next_run_at)` — optimized for polling.

## Manifest Format

Agents declare scheduled jobs in `manifest.json` under `scheduled_jobs`:

```json
{
  "name": "my-agent",
  "version": "1.0.0",
  "scheduled_jobs": [
    {
      "name": "daily-sync",
      "skill_id": "my_agent.sync",
      "cron_expression": "0 0 * * *",
      "payload": {"mode": "full"},
      "max_retries": 3,
      "retry_delay_seconds": 60,
      "timezone": "UTC"
    },
    {
      "name": "one-shot-init",
      "skill_id": "my_agent.init",
      "payload": {},
      "max_retries": 1,
      "retry_delay_seconds": 30,
      "timezone": "UTC"
    }
  ]
}
```

### Fields

| Field | Required | Default | Description |
|-------|----------|---------|-------------|
| `name` | yes | — | Unique job name within the agent |
| `skill_id` | yes | — | A2A skill ID to invoke |
| `cron_expression` | no | `null` | Standard cron (5 fields). Null = one-shot |
| `payload` | no | `{}` | JSON payload passed to the skill |
| `max_retries` | no | `3` | Max retry attempts on failure |
| `retry_delay_seconds` | no | `60` | Seconds between retries |
| `timezone` | no | `UTC` | Timezone for cron evaluation |

## Lifecycle Integration

Jobs are automatically managed during agent lifecycle events:

| Event | Action |
|-------|--------|
| **Install** | Registers all `scheduled_jobs` from manifest into DB |
| **Uninstall** | Deletes all scheduled jobs for the agent |
| **Enable** | Sets `enabled = TRUE` and recomputes `next_run_at` |
| **Disable** | Sets `enabled = FALSE` |

## Retry and Dead-Letter Policy

1. On failure (A2A returns `status: failed` or throws exception):
   - `retry_count` is incremented
   - `next_run_at` is set to `now() + retry_delay_seconds`
   - `last_status` is set to `failed`

2. When `retry_count >= max_retries`:
   - Job is disabled (`enabled = FALSE`)
   - `last_status` is set to `dead_letter`
   - A warning is logged

3. On success after failures:
   - `retry_count` is reset to `0`

## Catch-Up Policy

If the scheduler restarts after downtime, overdue jobs (where `next_run_at` is in the past) are executed **once** on the next tick. The `next_run_at` is then recomputed from the current time using the cron expression — missed intervals are not replayed.

## Async Dispatch

The scheduler dispatches all due A2A calls **concurrently** using ReactPHP (`react/http` + `react/async`). A single PHP process handles multiple simultaneous agent calls without blocking.

### How It Works

The `tick()` method executes in two phases:

1. **Phase 1 (transactional):** Find due jobs via `FOR UPDATE SKIP LOCKED`, log starts, compute and update `next_run_at`, commit transaction. This ensures crash-safety — if the process dies during Phase 2, jobs won't be double-picked.

2. **Phase 2 (async):** `AsyncA2ADispatcher::dispatchAll()` fires all A2A HTTP POST requests in parallel using `React\Http\Browser`. Results are collected when all promises resolve, then each job's log and retry state are updated.

### Concurrency Limit

The `SCHEDULER_CONCURRENCY_LIMIT` environment variable (default: `20`) caps the number of simultaneous in-flight A2A requests. If more jobs are due, they are dispatched in batches as in-flight calls complete.

### Per-Job Timeout

Each async HTTP request has a 30-second timeout. A slow agent does not block other concurrent calls.

### Error Isolation

Each promise is individually wrapped — one agent's failure (timeout, connection error, application error) does not affect other concurrent jobs. Failed jobs follow the standard retry/dead-letter policy.

### Dependencies

- `react/http` ^1.11 — Non-blocking HTTP client
- `react/async` ^4.3 — `await()` bridge for synchronous code

## Concurrency Control

The scheduler uses `SELECT ... FOR UPDATE SKIP LOCKED` when fetching due jobs. This ensures that if multiple scheduler instances run simultaneously (e.g., during rolling restarts), each job is picked by at most one instance.

## Execution Logs

Every job execution is recorded in the `scheduler_job_logs` table:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `job_id` | UUID | FK to `scheduled_jobs.id` (SET NULL on delete) |
| `agent_name` | VARCHAR(64) | Agent name (denormalized for audit trail) |
| `skill_id` | VARCHAR(128) | Skill invoked |
| `job_name` | VARCHAR(128) | Job name (denormalized) |
| `payload_sent` | JSONB | Payload sent to the agent |
| `response_received` | JSONB | Response from the agent |
| `status` | VARCHAR(32) | `running`, `completed`, `failed` |
| `error_message` | TEXT | Error details on failure |
| `started_at` | TIMESTAMPTZ | When execution started |
| `finished_at` | TIMESTAMPTZ | When execution finished |

Indexes: `(job_id, created_at DESC)` for per-job queries, `(created_at)` for retention.

Log entries are created before the A2A call (`status = 'running'`) and updated after completion. If a job is deleted, existing log entries remain with `job_id = NULL`.

### Log Viewer

Navigate to `/admin/scheduler/{id}/logs` to view execution history for a specific job. The log viewer shows:
- Start/finish timestamps and duration
- Status badges (completed, failed, running)
- Error messages (truncated with hover tooltip)
- Payload sent

Pagination: 50 entries per page.

## Admin UI

Navigate to `/admin/scheduler` to view all scheduled jobs. Available actions:

- **Логи** — View execution history for the job
- **Запустити** (Run Now) — Sets `next_run_at = now()` so the job runs on the next tick
- **Увімкнути / Вимкнути** (Toggle) — Enables or disables the job
- **Видалити** (Delete) — Remove admin-created jobs (manifest jobs cannot be deleted)

### Visual Cron Builder

When creating a job, click **"Візуальний"** to switch from the text cron input to a visual builder powered by `@vue-js-cron/light`. The builder provides clickable controls for minute, hour, day-of-month, month, and day-of-week. Changes in the visual builder sync bidirectionally with the text input.

CDN dependencies (loaded only on the scheduler page):
- Vue 3 via `esm.sh`
- `@vue-js-cron/light` v5.1.1 via `esm.sh`

### Stale Job Detection

If an agent updates its manifest and removes a skill that a scheduled job references, the job is flagged as **stale** in the admin UI:
- Row highlighted in red with a ⚠ warning icon
- Status badge shows "stale"
- Tooltip explains the reason (agent missing or skill missing)

### Job Sources

Jobs have a `source` column: `manifest` (from agent manifest, cannot be deleted) or `admin` (created via admin UI, can be deleted).

## Internal API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `POST /api/v1/internal/scheduler/{id}/run` | POST | Trigger job immediately |
| `POST /api/v1/internal/scheduler/{id}/toggle` | POST | Toggle enabled state |
| `DELETE /api/v1/internal/scheduler/{id}` | DELETE | Delete admin-created job |
| `POST /api/v1/internal/scheduler/create` | POST | Create a new admin job |
| `GET /api/v1/internal/scheduler/{id}/logs` | GET | Paginated execution logs |
| `GET /api/v1/internal/agents/{name}/skills` | GET | List agent skills from manifest |

All endpoints require `ROLE_ADMIN`.

Toggle request body:
```json
{"enabled": true}
```

## Docker Service

The scheduler runs as a separate Docker Compose service:

```yaml
core-scheduler:
  command: ["php", "bin/console", "scheduler:run"]
  restart: unless-stopped
  depends_on:
    - core
```

The service restarts automatically on crash. Graceful shutdown is handled via `SIGTERM`/`SIGINT` — the current tick completes before the process exits.

## One-Shot Jobs

Jobs with `cron_expression: null` are one-shot: they run once and are then disabled (`enabled = FALSE`). Use these for initialization tasks that should run once after agent install.
