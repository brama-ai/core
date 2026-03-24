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

The pipeline runtime SHALL preserve rework history through explicit step status transitions and attempt counters instead of erasing previous progress.

#### Scenario: Reviewer requests coder rework
- **WHEN** a review phase requests changes after a prior coder attempt
- **THEN** the review step is recorded as `rework_requested`
- **AND** the coder step is set to rerun with an incremented `attempt`
- **AND** prior attempts remain visible in task-local state history

#### Scenario: Monitor renders repeated step execution
- **WHEN** a task reruns a step after rework
- **THEN** monitor state can show the rerun attempt number for that step
- **AND** operators can distinguish first-pass execution from rework execution
