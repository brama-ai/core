## ADDED Requirements
### Requirement: Task Handoff And Summary Are Task-Scoped

Both Foundry and Ultraworks SHALL write human-readable continuity artifacts into the active task directory instead of treating a global handoff or global summary location as the primary operator-facing artifact location.

The task-scoped readable artifacts are:
- `handoff.md`
- `summary.md`

#### Scenario: Summarizer writes task-local summary
- **WHEN** the summarizer completes for a task
- **THEN** it writes `summary.md` inside that task's own directory
- **AND** the summary belongs only to that task run

#### Scenario: Agents append to task-local handoff
- **WHEN** a pipeline agent writes its continuity update
- **THEN** it appends to the active task directory's `handoff.md`
- **AND** it does not overwrite an unrelated task's handoff history
