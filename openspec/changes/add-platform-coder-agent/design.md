# Design: Built-in Platform Coder Agent

## Context

The project has a mature bash-based multi-agent pipeline (`scripts/pipeline.sh`) that orchestrates 5 AI coding agents in sequence: architect (creates OpenSpec proposals), coder (implements code), validator (runs PHPStan + CS Fixer), tester (runs tests), and documenter (writes docs). A batch runner (`pipeline-batch.sh`) adds folder-based kanban (todo/in-progress/done/failed) with parallel workers via git worktrees. A TUI monitor (`pipeline-monitor.sh`) provides terminal-based observation.

This design wraps that pipeline logic into a first-class platform service accessible from the admin panel, with proper database-backed state management, real-time monitoring, and A2A integration.

## Goals / Non-Goals

### Goals

- Provide a web-based admin UI for managing coding tasks (create, edit, delete, prioritize, monitor)
- Wrap the existing 5-stage pipeline in a PHP/Symfony service with proper state tracking
- Support multiple concurrent workers using git worktrees for isolation
- Provide real-time progress monitoring via Server-Sent Events (SSE)
- Integrate with the platform's A2A protocol so other agents can submit and query tasks
- Support task templates for common work types (ADR, HLD, feature spec, bug fix, refactor)
- Provide stage gate verification between pipeline stages
- Use the existing LiteLLM gateway for model selection with fallback chains

### Non-Goals

- Replacing the CLI-based pipeline scripts (they remain as standalone tools)
- Supporting non-PHP codebases or external repositories in the first version
- Implementing a full CI/CD system (the agent is a coding assistant, not a deployment tool)
- Multi-tenant task isolation (single platform instance)
- Web-based code editor or diff viewer in the first version
- Automatic merge/deploy of completed work

## Architecture

```text
Admin Panel UI
    |
    v
[Task Management Controller]  <--SSE-->  [Browser EventSource]
    |
    v
[CoderTaskService]
    |
    +---> [TaskQueue (Redis)]
    |         |
    |         v
    |     [WorkerManager]
    |         |
    |         +---> [Worker 1] ---> [GitWorktreeManager] ---> worktree-1/
    |         |         |
    |         |         v
    |         |     [PipelineOrchestrator]
    |         |         |
    |         |         +---> Stage 1: Architect (LiteLLM)
    |         |         +---> Gate Check
    |         |         +---> Stage 2: Coder (LiteLLM)
    |         |         +---> Gate Check
    |         |         +---> Stage 3: Validator (PHPStan/CS)
    |         |         +---> Gate Check
    |         |         +---> Stage 4: Tester (Codeception)
    |         |         +---> Gate Check
    |         |         +---> Stage 5: Documenter (LiteLLM)
    |         |
    |         +---> [Worker 2] ---> [GitWorktreeManager] ---> worktree-2/
    |         ...
    |
    +---> [A2A Skills]
    |         - coder.submit_task
    |         - coder.task_status
    |         - coder.cancel_task
    |
    +---> Postgres: coder_tasks, coder_task_logs, coder_workers
```

## Data Model

All tables live in the core platform database under the existing schema.

### `coder_tasks`

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| title | VARCHAR(255) | Short task name |
| description | TEXT | Full markdown task description |
| template_type | VARCHAR(64) NULL | adr, hld, feature, bugfix, refactor, custom |
| priority | INTEGER | Higher = more urgent, default 1 |
| status | VARCHAR(32) | todo, queued, in_progress, done, failed, cancelled |
| branch_name | VARCHAR(255) NULL | Git branch created for this task |
| worktree_path | VARCHAR(512) NULL | Absolute path to worktree |
| worker_id | VARCHAR(64) NULL | Which worker is processing |
| current_stage | VARCHAR(32) NULL | architect, coder, validator, tester, documenter |
| stage_progress | JSONB | Per-stage status and timing |
| pipeline_config | JSONB | Override defaults: skip stages, model selection, timeouts |
| error_message | TEXT NULL | Last error if failed |
| retry_count | INTEGER | Default 0 |
| started_at | TIMESTAMPTZ NULL | |
| finished_at | TIMESTAMPTZ NULL | |
| created_at | TIMESTAMPTZ | |
| updated_at | TIMESTAMPTZ | |

### `coder_task_logs`

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| task_id | UUID | FK to coder_tasks |
| stage | VARCHAR(32) | Pipeline stage name |
| level | VARCHAR(16) | info, warning, error |
| message | TEXT | Log line |
| metadata | JSONB NULL | Structured data (model used, tokens, timing) |
| created_at | TIMESTAMPTZ | |

### `coder_workers`

| Column | Type | Notes |
|--------|------|-------|
| id | VARCHAR(64) | PK, e.g. "worker-1" |
| status | VARCHAR(32) | idle, busy, stopped |
| current_task_id | UUID NULL | FK to coder_tasks |
| pid | INTEGER NULL | OS process ID |
| started_at | TIMESTAMPTZ | |
| last_heartbeat_at | TIMESTAMPTZ | |

## Pipeline Orchestration

### Stage Execution

Each stage runs as a subprocess calling an AI coding tool (Claude Code, OpenCode, or similar) via the LiteLLM gateway. The orchestrator:

1. Prepares the stage prompt from the task description and previous stage output
2. Sets up the environment (worktree path, model selection, timeout)
3. Executes the AI tool as a subprocess with timeout
4. Captures stdout/stderr and persists to `coder_task_logs`
5. Runs the stage gate check
6. Proceeds to next stage or fails with retry

### Stage Gate System

Between each stage, a gate check verifies the output:

| After Stage | Gate Check |
|-------------|-----------|
| Architect | OpenSpec proposal exists and validates (`openspec validate --strict`) |
| Coder | Code changes present, no syntax errors |
| Validator | PHPStan level 8 passes, CS Fixer has no violations |
| Tester | Codeception suites pass |
| Documenter | Documentation files updated |

If a gate check fails, the stage can be retried up to `MAX_RETRIES` times. If all retries fail, the task moves to `failed` status.

### Model Selection

Each stage has a default model and fallback chain, matching the existing pipeline configuration:

- Architect: claude-sonnet-4-6 -> gpt-5.3-codex -> free -> cheap
- Coder: gpt-5.3-codex -> claude-opus-4-6 -> free -> cheap
- Validator: claude-sonnet-4-6 -> codex-mini-latest -> free -> cheap
- Tester: claude-sonnet-4-6 -> codex-mini-latest -> free -> cheap
- Documenter: claude-opus-4-6 -> free -> cheap

These are configurable per-task via `pipeline_config` JSONB column.

## Worker Management

### Symfony Commands

- `bin/console coder:worker:start [--id=worker-1]` -- Starts a worker that polls the task queue
- `bin/console coder:worker:stop [--id=worker-1]` -- Gracefully stops a worker
- `bin/console coder:worker:status` -- Shows all worker statuses

### Worker Lifecycle

1. Worker starts and registers in `coder_workers` with `idle` status
2. Polls Redis queue for next task (ordered by priority DESC, created_at ASC)
3. Claims task (atomic operation), sets status to `in_progress`
4. Creates git worktree for the task
5. Runs pipeline stages sequentially
6. On completion: moves task to `done`, cleans up worktree, returns to polling
7. On failure: moves task to `failed`, preserves worktree for debugging, returns to polling
8. Heartbeat: updates `last_heartbeat_at` every 30 seconds

### Concurrency

- Redis-based queue ensures no two workers claim the same task
- Git worktrees provide filesystem isolation between concurrent workers
- Configurable max worker count via admin settings (default: 2)

## Git Worktree Management

### Lifecycle

1. **Create**: `git worktree add .opencode/pipeline/worktrees/<task-slug> -b pipeline/<task-slug>`
2. **Track**: Store path in `coder_tasks.worktree_path`
3. **Install deps**: Run `composer install` / dependency setup in worktree
4. **Execute**: All pipeline stages run within the worktree directory
5. **Cleanup (success)**: Remove worktree after task completes and branch is available for review
6. **Preserve (failure)**: Keep worktree for debugging, flag for manual cleanup

### Branch Naming

Pattern: `pipeline/<task-slug>` where task-slug is derived from the task title (kebab-case, max 60 chars).

## Real-Time Monitoring (SSE)

### Endpoint

`GET /admin/coder/events` -- SSE stream for real-time updates.

### Event Types

- `task.status_changed` -- Task moved to a new status
- `task.stage_changed` -- Pipeline stage transition
- `task.log` -- New log line appended
- `worker.status_changed` -- Worker idle/busy/stopped
- `worker.heartbeat` -- Worker health pulse

### Implementation

A lightweight SSE controller that queries for changes since the client's last event ID. Uses Redis pub/sub to push events from workers to the SSE endpoint without polling the database.

## Task Templates

Predefined task description templates that pre-fill the description field:

| Template | Description | Default Stages |
|----------|-------------|----------------|
| ADR | Architecture Decision Record | architect only |
| HLD | High-Level Design document | architect only |
| Feature | New feature implementation | all 5 stages |
| Bug Fix | Fix an existing bug | coder, validator, tester |
| Refactor | Code restructuring | coder, validator, tester |

Templates are stored as Twig partials and rendered into the task creation form. Each template can specify which pipeline stages to include/skip.

## A2A Integration

### Skills

#### `coder.submit_task`

Submit a new coding task to the queue.

Input:
```json
{
  "title": "Add streaming support",
  "description": "Full markdown task description...",
  "priority": 5,
  "template": "feature",
  "pipeline_config": {
    "skip_stages": ["documenter"],
    "model_overrides": { "coder": "claude-opus-4-6" }
  }
}
```

Output:
```json
{
  "task_id": "uuid",
  "status": "queued",
  "position": 3
}
```

#### `coder.task_status`

Query the status of a task or list recent tasks.

Input:
```json
{
  "task_id": "uuid"
}
```

Output:
```json
{
  "task_id": "uuid",
  "status": "in_progress",
  "current_stage": "validator",
  "stage_progress": { "architect": "done", "coder": "done", "validator": "running" },
  "worker_id": "worker-1",
  "started_at": "2026-03-09T12:00:00Z"
}
```

#### `coder.cancel_task`

Cancel a queued or running task.

Input:
```json
{
  "task_id": "uuid"
}
```

## Decisions

### Decision: PHP service inside core, not a standalone app

- **Why**: The coder agent manages the platform's own codebase and needs direct access to git, filesystem, and the existing admin panel. A separate service would add unnecessary network hops and deployment complexity for what is fundamentally a platform-internal tool.
- **Alternative**: Standalone Python or PHP service like news-maker-agent, which would be more isolated but harder to integrate with admin UI and git operations.

### Decision: Redis queue for task ordering, not RabbitMQ

- **Why**: Tasks are simple priority-ordered work items with low throughput (tens per day, not thousands). Redis sorted sets provide natural priority ordering. RabbitMQ is overkill and adds message broker complexity for this use case.
- **Alternative**: RabbitMQ with priority queues, which provides better durability guarantees but unnecessary complexity.

### Decision: SSE for real-time updates, not WebSocket

- **Why**: Updates flow one direction (server to browser). SSE is simpler to implement, works through proxies/load balancers, and requires no additional infrastructure. The admin panel already uses standard HTTP.
- **Alternative**: WebSocket, which supports bidirectional communication but adds complexity without benefit here.

### Decision: Subprocess execution for AI stages, not in-process

- **Why**: AI coding tools (Claude Code, OpenCode) run as CLI processes with their own context management. Wrapping them as subprocesses preserves their existing behavior, allows timeout control, and prevents memory leaks from affecting the main PHP process.
- **Alternative**: HTTP API calls to AI providers directly, which would require reimplementing all the prompt engineering and context management currently in the bash scripts.

### Decision: Worktrees in .opencode/pipeline/worktrees/, matching existing convention

- **Why**: The existing `pipeline-batch.sh` already uses this path. Maintaining consistency avoids confusion and allows the CLI and web-based systems to coexist.
- **Alternative**: A new path like `.coder-agent/worktrees/`, which would separate concerns but fragment the worktree layout.

## Risks / Trade-offs

- Long-running PHP processes (workers) may hit memory limits or become unstable.
  - Mitigation: Workers restart after each task completion; Symfony's `kernel.reset` clears service state; configurable max tasks per worker lifetime.
- Subprocess execution of AI tools is hard to observe and control.
  - Mitigation: Capture all stdout/stderr, enforce timeouts, write structured logs to database.
- Git worktrees can accumulate and consume disk space.
  - Mitigation: Automatic cleanup on success; admin UI shows worktree status; scheduled cleanup command for stale worktrees.
- Priority queue starvation: low-priority tasks may never run.
  - Mitigation: Age-based priority boost (tasks waiting >24h get priority bump).
- SSE connections may not scale well with many concurrent admin users.
  - Mitigation: Acceptable for single-admin MVP; can add WebSocket layer later if needed.

## Migration Plan

1. Add database migrations for `coder_tasks`, `coder_task_logs`, `coder_workers`.
2. Implement core services: `CoderTaskService`, `PipelineOrchestrator`, `GitWorktreeManager`.
3. Implement worker command and queue integration.
4. Build admin UI: task list, create/edit forms, dashboard.
5. Add SSE endpoint for real-time monitoring.
6. Implement A2A skills and register in agent card.
7. Add stage gate checks.
8. Add task templates.
9. Write tests and documentation.

## Open Questions

- Should completed task branches be automatically pushed to the remote, or left as local branches for manual review?
- Should the admin UI include a diff viewer for reviewing completed work, or rely on external git tools (GitLab MR)?
- What is the maximum number of concurrent workers the platform should support?
- Should failed tasks be automatically retried after a cooldown period, or always require manual intervention?
- Should there be integration with the dev-reporter-agent for pipeline result notifications?
