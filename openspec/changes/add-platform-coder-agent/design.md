# Design: Built-in Platform Coder Agent

## Context

The project already has a mature bash-based builder workflow:

- `builder/pipeline.sh` executes the multi-agent pipeline
- `builder/pipeline-batch.sh` manages parallel execution and worktrees
- `builder/monitor/pipeline-monitor.sh` provides a terminal monitor
- `builder/tasks/*` stores the task queue and task outputs

The goal of this change is to wrap that workflow inside Core admin without breaking the existing CLI tooling.

## Goals

- Provide a Core-admin UI for creating and monitoring coding tasks
- Store task state, worker state, and logs in the Core database
- Keep builder filesystem artifacts operational during the first release
- Provide near-real-time visibility through SSE
- Support worker commands and task state transitions from Core

## Non-Goals

- Replacing the builder scripts with a pure PHP pipeline
- Building an in-browser code editor
- Supporting external repositories in the first release
- Requiring Redis pub/sub for the initial SSE implementation

## Architecture

```text
Core Admin UI
    |
    v
[Coder Controllers] <--SSE--> [Browser EventSource]
    |
    v
[CoderTaskService]
    |
    +---> [CoderCompatibilityBridge] ---> builder/tasks/* + .opencode/pipeline/*
    |
    +---> [DB-backed Task Repository]
    |          |
    |          v
    |      [Worker Commands]
    |          |
    |          v
    |      [BuilderPipelineRunner] ---> builder/pipeline.sh
    |
    +---> Postgres: coder_tasks, coder_task_logs, coder_workers
```

## Data Model

### `coder_tasks`

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| title | VARCHAR(255) | Short task name |
| description | TEXT | Markdown task description |
| template_type | VARCHAR(64) NULL | `feature`, `bugfix`, `refactor`, `custom` |
| priority | INTEGER | Higher = more urgent, default 1 |
| status | VARCHAR(32) | `draft`, `queued`, `in_progress`, `done`, `failed`, `cancelled` |
| current_stage | VARCHAR(32) NULL | Current observed pipeline stage |
| stage_progress | JSONB | Observed stage state map |
| pipeline_config | JSONB | Task configuration |
| compat_state | JSONB NULL | Reconciliation info with builder filesystem |
| builder_task_path | VARCHAR(512) NULL | Builder markdown file path |
| summary_path | VARCHAR(512) NULL | Summary file path |
| artifacts_path | VARCHAR(512) NULL | Artifact directory path |
| branch_name | VARCHAR(255) NULL | Builder pipeline branch |
| worktree_path | VARCHAR(512) NULL | Builder worktree path |
| worker_id | VARCHAR(64) NULL | Current worker |
| error_message | TEXT NULL | Last failure |
| retry_count | INTEGER | Retry attempts |
| started_at | TIMESTAMPTZ NULL | |
| finished_at | TIMESTAMPTZ NULL | |
| created_at | TIMESTAMPTZ | |
| updated_at | TIMESTAMPTZ | |

### `coder_task_logs`

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| task_id | UUID | FK to task |
| stage | VARCHAR(32) NULL | Observed stage |
| level | VARCHAR(16) | `info`, `warning`, `error` |
| source | VARCHAR(32) | `pipeline`, `bridge`, `system` |
| message | TEXT | Log line |
| metadata | JSONB NULL | Structured details |
| created_at | TIMESTAMPTZ | |

### `coder_workers`

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(64) | PK |
| status | VARCHAR(32) | `idle`, `busy`, `stopped`, `dead`, `stopping` |
| current_task_id | UUID NULL | FK to task |
| pid | INTEGER NULL | Worker process PID |
| worktree_path | VARCHAR(512) NULL | Current worktree path |
| started_at | TIMESTAMPTZ | |
| last_heartbeat_at | TIMESTAMPTZ | |

## Compatibility Bridge

Core remains the primary application model, but builder artifacts stay active.

### Task file rendering

On task creation, Core writes a builder-compatible markdown file to `builder/tasks/todo/`:

```md
<!-- coder_task_id: <uuid> -->
<!-- priority: 5 -->
<!-- template: feature -->
<!-- status_hint: queued -->
# Task title

Task description...
```

### Reconciliation rules

- If a task is `queued` or `draft` in DB, Core may recreate a missing builder file
- If builder folders indicate `in-progress`, `done`, or `failed`, Core reconciles DB state to match runtime facts
- Divergence is recorded as warning log entries with source `bridge`

## Execution Model

Phase 1 uses `builder/pipeline.sh` as the execution engine.

Core worker responsibilities:

1. claim a queued task atomically from the DB
2. ensure the builder task file exists
3. launch the builder pipeline subprocess
4. capture stdout/stderr lines into `coder_task_logs`
5. detect stage transitions from output and artifacts
6. update task state, summary path, branch, and worktree metadata
7. keep worker heartbeat current

## Stage Detection

Core does not reimplement stage logic; it observes the builder runtime.

Tracked stages:

- `planner`
- `architect`
- `coder`
- `auditor`
- `validator`
- `tester`
- `documenter`
- `summarizer`

Sources:

- subprocess output
- `builder/tasks/artifacts/<task-slug>/checkpoint.json`
- known summary and artifact files

## SSE

`GET /admin/coder/events` streams incremental updates based on DB state changes.

Event types:

- `task.status_changed`
- `task.stage_changed`
- `task.log`
- `worker.status_changed`
- `worker.heartbeat`
- `task.reconciled_warning`

The initial implementation can read incremental DB changes and does not require Redis pub/sub.
