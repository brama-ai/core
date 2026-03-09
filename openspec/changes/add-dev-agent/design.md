# Design: Dev Agent

## Context

The AI Community Platform has a shell-based development pipeline (`scripts/pipeline.sh`) that orchestrates multiple AI coding agents. While the dev-reporter-agent provides observability (reporting on completed runs), there is no agent that allows users to create, refine, execute, and manage development tasks through the platform's web interface.

The dev-agent fills this role as the development orchestration layer.

## Goals

1. **Web-based task management** with full CRUD lifecycle (create, list, detail, start pipeline)
2. **LLM-powered task refinement** using Claude Opus 4.6 through LiteLLM proxy
3. **Real-time pipeline monitoring** via Server-Sent Events (SSE)
4. **Automated PR creation** on GitHub after successful pipeline execution
5. **Consistent patterns** — follows dev-reporter-agent scaffold, no new infrastructure

## Architecture

```
Browser ──(HTTP)──→ dev-agent (Admin UI)
                        │
                        ├──→ LiteLLM ──→ Claude Opus 4.6 (task refinement)
                        │
                        ├──→ Postgres (dev_tasks + dev_task_logs)
                        │
                        ├──(SSE)──→ Browser (live pipeline logs)
                        │
                        ├──(proc_open)──→ pipeline.sh (subprocess execution)
                        │
                        └──(gh CLI)──→ GitHub (PR creation)

Core ──(A2A)──→ dev-agent (skill invocation)
```

### Task Lifecycle

```
draft → [Opus refinement] → pending → running → success/failed
                                                    │
                                                    └──→ PR created (on success)
```

1. **Draft**: User creates task with title and description
2. **Refinement** (optional): Multi-turn chat with Opus clarifies requirements
3. **Pending**: Task queued for pipeline execution
4. **Running**: Background worker picks up task, runs `pipeline.sh` via `proc_open`
5. **Success/Failed**: Pipeline completes, logs stored, status updated
6. **PR Created**: On success, `git push` + `gh pr create` via GitHubService

### Pipeline Execution

The `PipelineRunner` service wraps `scripts/pipeline.sh` as a subprocess:

1. Writes `refined_spec` to a temp file → passed as `--task-file` argument
2. Generates branch name from task title → passed as `--branch` argument
3. Opens process with `proc_open`, reads stdout/stderr line-by-line
4. Each line is stored in `dev_task_logs` table with parsed agent step
5. On completion, updates task status and duration

A background `PipelineWorkerCommand` (Symfony Console) polls for pending tasks every 5 seconds. It runs as a background process in the container's entrypoint.

### SSE Streaming

First SSE implementation in the platform:

- **Endpoint**: `GET /admin/tasks/api/{id}/logs/stream?last_id=0`
- **Response**: `StreamedResponse` with `Content-Type: text/event-stream`
- **Polling**: Queries `dev_task_logs` every 1 second for new entries after `last_id`
- **Heartbeat**: Comment line every 15 seconds to keep connection alive
- **Completion**: Sends `event: complete` with task status when pipeline finishes
- **Frontend**: `EventSource` API with auto-reconnect via `last_id`

### LLM Integration

Task refinement uses Claude Opus 4.6 via LiteLLM proxy:

- **System prompt**: Guides Opus to ask clarifying questions and produce structured task specs
- **Multi-turn**: Frontend maintains `chat_history` array, sends to `/admin/tasks/api/refine`
- **Endpoint**: `LlmService` calls `POST http://litellm:4000/v1/chat/completions`
- **Cost control**: User-initiated only, max_tokens capped at 4096

## Data Model

### Table: `dev_tasks`

| Column | Type | Description |
|--------|------|-------------|
| id | SERIAL PRIMARY KEY | Auto-increment |
| title | VARCHAR(200) | Task title |
| description | TEXT | Original user description |
| refined_spec | TEXT NULL | Opus-refined specification |
| status | VARCHAR(20) | draft, pending, running, success, failed, cancelled |
| branch | VARCHAR(100) NULL | Git branch name |
| pipeline_id | VARCHAR(30) NULL | Timestamp-based pipeline ID |
| pr_url | VARCHAR(500) NULL | GitHub PR URL |
| pr_number | INTEGER NULL | PR number |
| pipeline_options | JSONB | Options: skip_architect, audit |
| chat_history | JSONB | Opus refinement conversation |
| error_message | TEXT NULL | Error details on failure |
| duration_seconds | INTEGER NULL | Pipeline execution time |
| created_at | TIMESTAMPTZ | Task creation time |
| started_at | TIMESTAMPTZ NULL | Pipeline start time |
| finished_at | TIMESTAMPTZ NULL | Pipeline end time |

### Table: `dev_task_logs`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGSERIAL PRIMARY KEY | Auto-increment |
| task_id | INTEGER FK | Reference to dev_tasks |
| agent_step | VARCHAR(30) NULL | architect, coder, validator, tester, documenter |
| level | VARCHAR(10) | info, warn, error |
| message | TEXT | Log line content |
| created_at | TIMESTAMPTZ | Log entry time |

### Indexes

- `idx_dev_tasks_status` on `(status)`
- `idx_dev_tasks_created_at` on `(created_at DESC)`
- `idx_dev_task_logs_task_id` on `(task_id)`
- `idx_dev_task_logs_task_created` on `(task_id, created_at)`

## Skill Contracts

### `dev.create_task`

**Input**:
```json
{
  "title": "Add search pagination",
  "description": "Knowledge search returns all results. Add limit/offset.",
  "pipeline_options": { "skip_architect": false, "audit": false }
}
```

**Output**: `{ "status": "completed", "data": { "task_id": 42 } }`

### `dev.run_pipeline`

**Input**: `{ "task_id": 42 }`

**Output**: `{ "status": "completed", "data": { "task_id": 42, "pipeline_status": "pending" } }`

### `dev.get_status`

**Input**: `{ "task_id": 42 }`

**Output**:
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

### `dev.list_tasks`

**Input**: `{ "status_filter": "running", "limit": 10 }`

**Output**: `{ "status": "completed", "data": { "tasks": [...], "count": 3 } }`

## Trade-offs

| Decision | Alternative | Reasoning |
|----------|-------------|-----------|
| PHP agent (Symfony) | Python (FastAPI) | Consistency with dev-reporter, reuse DBAL/logging patterns |
| Background worker via `php bin/console` | RabbitMQ consumer | Simpler, no new infra; RabbitMQ consumer can be added later |
| SSE for live logs | WebSocket | SSE is simpler, auto-reconnects, sufficient for append-only logs |
| `proc_open` for pipeline | Docker exec | Direct subprocess gives real-time stdout capture; pipeline.sh handles Docker internally |
| `gh` CLI for PR creation | GitHub API (REST) | CLI is simpler, already authenticated; avoids HTTP client complexity |
| Chat history in JSONB column | Separate table | Bounded by conversation length; single query retrieves full context |

## Security

- Admin panel behind Core's edge auth middleware (Traefik `edge-auth` middleware)
- A2A endpoint authenticated via `X-Platform-Internal-Token`
- `GH_TOKEN` loaded from environment, not committed (`.env.local`)
- Repo volume mounted read-write (needed for pipeline); container is dev-only
- LiteLLM API key is the shared `dev-key` master key
