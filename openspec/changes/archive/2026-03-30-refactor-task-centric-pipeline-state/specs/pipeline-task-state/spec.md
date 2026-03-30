## ADDED Requirements

### Requirement: Pipeline Tasks Use Task-Scoped Runtime Directories

The pipeline runtime SHALL persist each task run inside its own directory under `tasks/`.

Each task directory SHALL use the naming pattern:
- `tasks/<task-slug>--foundry/`
- `tasks/<task-slug>--ultraworks/`

Each task directory SHALL contain:
- `task.md`
- `handoff.md`
- `state.json`
- `events.jsonl`
- `summary.md`
- `meta.json`

#### Scenario: Foundry task creates task directory
- **WHEN** an operator creates or starts a Foundry task
- **THEN** the runtime creates `tasks/<task-slug>--foundry/`
- **AND** initializes the required task-local runtime files there

#### Scenario: Ultraworks task creates task directory
- **WHEN** an operator launches an Ultraworks task
- **THEN** the runtime creates `tasks/<task-slug>--ultraworks/`
- **AND** initializes the required task-local runtime files there

### Requirement: State JSON Is The Canonical Resume Source

The pipeline runtime SHALL use `state.json` as the canonical source of truth for task status, current step, resume point, and step attempts.

`handoff.md` SHALL remain a readable continuity journal but SHALL NOT be the primary machine-readable resume source.

#### Scenario: Resume after interruption
- **WHEN** a task is interrupted while `state.json` shows a non-terminal `current_step`
- **THEN** the runtime resumes from the step indicated by `resume_from` or `current_step`
- **AND** does not require parsing `handoff.md` as the primary resume source

#### Scenario: Fallback recovery from readable artifacts
- **WHEN** `state.json` is unavailable or invalid
- **THEN** the runtime may attempt best-effort recovery from `handoff.md` and `events.jsonl`
- **AND** marks the resumed task as recovered-from-fallback in machine state

### Requirement: Task State Preserves Rework History

The pipeline runtime SHALL preserve rework history by appending agent entries with per-attempt tracking instead of overwriting previous attempt data.

Each agent entry in `state.json` `agents[]` SHALL include an `attempt` field indicating which task-level attempt the entry belongs to.

On retry, new agent entries SHALL be appended with the new attempt number. Previous attempt entries SHALL NOT be removed or overwritten.

Queries for current agent status SHALL filter by `attempt == state.attempt` to return only the active attempt's data.

#### Scenario: Reviewer requests coder rework
- **WHEN** a review phase requests changes after a prior coder attempt
- **THEN** the review step is recorded as `rework_requested`
- **AND** the coder step is set to rerun with an incremented `attempt`
- **AND** prior attempts remain visible in the `agents[]` array with their original attempt number

#### Scenario: Monitor renders repeated step execution
- **WHEN** a task reruns a step after rework
- **THEN** monitor state can show the rerun attempt number for that step
- **AND** operators can distinguish first-pass execution from rework execution

#### Scenario: Agent history survives retry
- **WHEN** a task has completed attempt 1 with u-coder duration 30s
- **AND** the task is retried (attempt 2) and u-coder runs again with duration 45s
- **THEN** `state.json` `agents[]` contains two u-coder entries: one with `attempt: 1, duration_seconds: 30` and one with `attempt: 2, duration_seconds: 45`

### Requirement: Atomic Task Directory Initialization

The pipeline runtime SHALL create task directories atomically to prevent ghost directories on interrupted initialization.

The runtime SHALL write all initial task files to a temporary directory, then move the temporary directory to the final path using an atomic filesystem rename.

The runtime SHALL install SIGINT and SIGTERM trap handlers that clean up incomplete temporary directories on interrupt.

On startup, the runtime SHALL scan for and remove leftover temporary directories (`.tmp.*` pattern) from previous interrupted runs.

#### Scenario: Interrupt during task directory creation
- **WHEN** the pipeline process receives SIGINT during task directory initialization
- **THEN** the incomplete temporary directory is removed
- **AND** no ghost directory remains at the final task path

#### Scenario: Ghost directory cleanup on startup
- **WHEN** the pipeline starts and finds leftover `.tmp.*` directories in the tasks root
- **THEN** the runtime removes the stale temporary directories before proceeding
- **AND** logs a warning about cleaned-up ghost directories

#### Scenario: Normal task creation completes atomically
- **WHEN** a task is created without interruption
- **THEN** the task directory appears at its final path with all required files present
- **AND** no intermediate state is visible to concurrent processes

### Requirement: Task Description Is Immutable After Creation

The pipeline runtime SHALL treat `task.md` as immutable after initial creation. No retry, cleanup, or archival operation SHALL delete or modify `task.md`.

The runtime SHALL assert that `task.md` exists and is non-empty before proceeding with retry or agent execution. If the assertion fails, the runtime SHALL refuse to proceed and emit a `task_md_missing` event.

#### Scenario: Task.md preserved after retry
- **WHEN** an agent fails and the task is retried
- **THEN** `task.md` remains unchanged in the task directory
- **AND** the retry proceeds using the original task description

#### Scenario: Missing task.md blocks execution
- **WHEN** the runtime attempts to execute an agent for a task
- **AND** `task.md` is missing or empty in the task directory
- **THEN** the runtime refuses to proceed
- **AND** emits a `task_md_missing` event to `events.jsonl`
- **AND** logs an error describing the missing file

### Requirement: Archive Requires Non-Empty Summary

The pipeline runtime SHALL NOT archive a task unless `summary.md` exists and is non-empty.

This guard SHALL be enforced in both the Bash archival path (`foundry-cleanup.sh`) and the TypeScript archival path (`archiveTask()` in the monitor).

If the guard fails, the runtime SHALL report the incomplete task to the operator instead of silently archiving it.

#### Scenario: Archive blocked for empty summary
- **WHEN** an operator or automated process attempts to archive a task
- **AND** `summary.md` is missing or has zero bytes
- **THEN** the archive operation is refused
- **AND** the task remains in the active `tasks/` directory
- **AND** the operator is informed that the task has an incomplete summary

#### Scenario: Archive succeeds for complete task
- **WHEN** an operator archives a task
- **AND** `summary.md` exists and contains content
- **AND** the task status is `completed`
- **THEN** the task directory is moved to `tasks/archives/<date>/<slug>/`

### Requirement: Meta JSON Contains Session And Lineage Metadata

`meta.json` SHALL contain immutable or slow-changing metadata for the task run, including workflow identity, session context, and resume lineage.

The required fields SHALL be:
- `workflow` — `"foundry"` or `"ultraworks"`
- `task_slug` — the task slug without workflow suffix
- `created_at` — ISO 8601 timestamp of task creation
- `branch_name` — git branch associated with the task (nullable)
- `worktree_path` — path to git worktree if applicable (nullable)
- `profile` — pipeline profile used (e.g., `"full"`, `"quick"`)
- `run_id` — unique identifier for the pipeline run

On resume, `meta.json` SHALL include a `resumed_from` field pointing to the original task identifier for lineage tracking.

#### Scenario: Foundry task meta.json on creation
- **WHEN** a Foundry task is created
- **THEN** `meta.json` contains `workflow: "foundry"`, `task_slug`, `created_at`, `branch_name`, `profile`, and `run_id`
- **AND** `worktree_path` is null
- **AND** `resumed_from` is null

#### Scenario: Resumed task includes lineage
- **WHEN** a task is resumed from a previous run
- **THEN** `meta.json` includes `resumed_from` with the original task's identifier
- **AND** the `run_id` is updated to reflect the new run
