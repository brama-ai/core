# Dev Agent

## Purpose
Dev Agent orchestrates development tasks through the platform's multi-agent pipeline. It provides a web interface for creating tasks with LLM-powered refinement, executing pipelines with live log streaming, and auto-creating GitHub PRs on success.

## Features
- `POST /api/v1/a2a` — A2A endpoint accepting 4 skills
- `GET /health` — standard health check (`{"status": "ok", "service": "dev-agent"}`)
- `GET /api/v1/manifest` — Agent Card per platform conventions
- `GET /admin/tasks` — task list with status filtering and stats
- `GET /admin/tasks/create` — task creation with Opus chat refinement
- `GET /admin/tasks/{id}` — task detail with SSE live pipeline logs
- `GET /admin/tasks/api/{id}/logs/stream` — SSE endpoint for real-time log streaming

## Skills

| Skill ID | Description | Key Inputs |
|---|---|---|
| `dev.create_task` | Create a development task | `title`, `description`, `pipeline_options` |
| `dev.run_pipeline` | Queue task for pipeline execution (async) | `task_id` |
| `dev.get_status` | Get task status, branch, PR URL, log count | `task_id` |
| `dev.list_tasks` | List recent tasks with optional filter | `status_filter`, `limit` |

### `dev.create_task` Payload

```json
{
  "intent": "dev.create_task",
  "payload": {
    "title": "Add search pagination",
    "description": "Knowledge search returns all results. Add limit/offset.",
    "pipeline_options": { "skip_architect": false, "audit": false }
  }
}
```

Returns `{ "status": "completed", "data": { "task_id": 42 } }`.

### `dev.run_pipeline` Payload

```json
{
  "intent": "dev.run_pipeline",
  "payload": { "task_id": 42 }
}
```

Task must be in `draft` or `failed` status. Returns `{ "status": "completed", "data": { "task_id": 42, "pipeline_status": "pending" } }`.

### `dev.get_status` Response

```json
{
  "status": "completed",
  "data": {
    "task_id": 42,
    "title": "Add search pagination",
    "task_status": "success",
    "branch": "pipeline/add-search-pagination",
    "pr_url": "https://github.com/nmdimas/ai-community-platform/pull/15",
    "log_count": 234,
    "created_at": "2026-03-09T10:00:00Z",
    "started_at": "2026-03-09T10:05:00Z",
    "finished_at": "2026-03-09T10:47:00Z"
  }
}
```

## Database

### Table: `dev_tasks`

| Column | Type | Notes |
|---|---|---|
| `id` | SERIAL PK | Auto-increment |
| `title` | VARCHAR(200) | Task title |
| `description` | TEXT | Original description |
| `refined_spec` | TEXT | Opus-refined specification (nullable) |
| `status` | VARCHAR(20) | `draft`, `pending`, `running`, `success`, `failed`, `cancelled` |
| `branch` | VARCHAR(100) | Git branch name (nullable) |
| `pipeline_id` | VARCHAR(30) | Timestamp-based pipeline ID (nullable) |
| `pr_url` | VARCHAR(500) | GitHub PR URL (nullable) |
| `pr_number` | INTEGER | PR number (nullable) |
| `pipeline_options` | JSONB | Options: `skip_architect`, `audit` |
| `chat_history` | JSONB | Opus refinement conversation |
| `error_message` | TEXT | Error details (nullable) |
| `duration_seconds` | INTEGER | Pipeline duration in seconds (nullable) |
| `created_at` | TIMESTAMPTZ | Task creation time |
| `started_at` | TIMESTAMPTZ | Pipeline start (nullable) |
| `finished_at` | TIMESTAMPTZ | Pipeline end (nullable) |

### Table: `dev_task_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGSERIAL PK | Auto-increment |
| `task_id` | INTEGER FK | References `dev_tasks(id)` |
| `agent_step` | VARCHAR(30) | architect, coder, validator, tester, documenter (nullable) |
| `level` | VARCHAR(10) | info, warn, error |
| `message` | TEXT | Log line content |
| `created_at` | TIMESTAMPTZ | Log entry time |

Indexes: `idx_dev_tasks_status`, `idx_dev_tasks_created_at`, `idx_dev_task_logs_task_id`, `idx_dev_task_logs_task_created`

## Tech Stack
- PHP 8.5 + Symfony 7
- Doctrine DBAL 4 (PostgreSQL)
- Apache (Docker)
- Traefik routing on port **8088**
- Claude Opus 4.6 via LiteLLM (OpenRouter)
- `gh` CLI for GitHub PR creation

## Authentication

The A2A endpoint is protected by the `X-Platform-Internal-Token` header. Admin pages are protected by Core's edge auth middleware (Traefik `edge-auth`).

```bash
curl -X POST http://localhost:8088/api/v1/a2a \
  -H "Content-Type: application/json" \
  -H "X-Platform-Internal-Token: ${APP_INTERNAL_TOKEN}" \
  -d '{"intent": "dev.list_tasks", "payload": {}}'
```

## Task Lifecycle

```
draft → [Opus refinement] → pending → running → success / failed
                                                     │
                                                     └→ PR created (on success, if GH_TOKEN set)
```

1. **Draft** — user creates task with title and description
2. **Refinement** (optional) — multi-turn chat with Claude Opus 4.6 refines the spec
3. **Pending** — task queued for background pipeline worker
4. **Running** — `pipeline.sh` executing via `proc_open`, logs streaming to DB
5. **Success/Failed** — pipeline finished, logs stored, status updated
6. **PR** — on success, `git push` + `gh pr create`

## SSE Live Logs

The agent provides Server-Sent Events for real-time pipeline log streaming:

```
GET /admin/tasks/api/{id}/logs/stream?last_id=0
```

- `Content-Type: text/event-stream`
- Each log entry sent as JSON data event
- Heartbeat every 15 seconds
- `event: complete` when task reaches terminal status
- Client reconnects using `last_id` from the last received event

## Environment Variables

| Variable | Description | Default |
|---|---|---|
| `DATABASE_URL` | Postgres connection string | `postgresql://dev_agent:dev_agent@postgres:5432/dev_agent` |
| `LITELLM_BASE_URL` | LiteLLM proxy URL | `http://litellm:4000` |
| `LITELLM_API_KEY` | LiteLLM API key | `dev-key` |
| `LLM_MODEL` | Model for task refinement | `claude-opus-4-6` |
| `REPO_ROOT` | Repository root for pipeline execution | `/repo` |
| `GH_TOKEN` | GitHub token for PR creation | (empty — PRs skipped) |

## Makefile Commands
- `make dev-agent-setup` — build container and install dependencies
- `make dev-agent-install` — install PHP dependencies
- `make dev-agent-migrate` — run Doctrine migrations
- `make dev-agent-test` — run Codeception tests
- `make dev-agent-analyse` — PHPStan analysis (level 8)
- `make dev-agent-cs-check` / `make dev-agent-cs-fix` — check/fix code style

## Admin Panel

### Task List (`/admin/tasks`)
- Stats row: total, active, success, failed, draft (last 7 days)
- Filterable table: All / Running / Success / Failed / Draft
- Per-row: id, title, status badge, branch, duration, PR link, date

### Task Creation (`/admin/tasks/create`)
- Title + description form
- Pipeline options: skip architect, include auditor, auto-start
- "Refine with Opus" — opens multi-turn chat interface
- "Accept & Create Task" — stores refined spec and creates task

### Task Detail (`/admin/tasks/{id}`)
- Metadata card: status, branch, pipeline ID, duration, PR link
- Specification card: refined spec or original description
- Live log panel: monospace log viewer with SSE auto-update
- Start Pipeline button (for draft/failed tasks)
