## ADDED Requirements

### Requirement: Task Handoff Is Task-Scoped Without Global Symlink

Both Foundry and Ultraworks SHALL write human-readable continuity artifacts directly into the active task directory. Agents SHALL read and write `handoff.md` from the task directory path (`<task_dir>/handoff.md`) instead of through a global symlink.

The global symlink at `.opencode/pipeline/handoff.md` SHALL be removed as the primary agent handoff path. Agent prompt templates SHALL reference the task-scoped handoff path directly.

On startup, the runtime SHALL clean up stale `.opencode/pipeline/handoff.md` symlinks from previous runs.

#### Scenario: Agents write to task-local handoff
- **WHEN** a pipeline agent writes its continuity update
- **THEN** it writes to the active task directory's `handoff.md` directly
- **AND** it does not create or update a global symlink at `.opencode/pipeline/handoff.md`

#### Scenario: Concurrent tasks have isolated handoffs
- **WHEN** two pipeline tasks run simultaneously in the same workspace
- **THEN** each task's agents write to their own `<task_dir>/handoff.md`
- **AND** no cross-contamination occurs between the two handoff files

#### Scenario: Agent prompts reference task-scoped path
- **WHEN** the pipeline constructs agent prompts
- **THEN** handoff references use the task directory path (e.g., `tasks/<slug>--foundry/handoff.md`)
- **AND** no agent prompt references `.opencode/pipeline/handoff.md`

#### Scenario: Stale symlink cleanup on startup
- **WHEN** the pipeline starts and finds a stale `.opencode/pipeline/handoff.md` symlink
- **THEN** the symlink is removed
- **AND** the pipeline proceeds without error

### Requirement: Task Summary Is Task-Scoped

Both Foundry and Ultraworks SHALL write summary artifacts into the active task directory.

#### Scenario: Summarizer writes task-local summary
- **WHEN** the summarizer completes for a task
- **THEN** it writes `summary.md` inside that task's own directory
- **AND** the summary belongs only to that task run
