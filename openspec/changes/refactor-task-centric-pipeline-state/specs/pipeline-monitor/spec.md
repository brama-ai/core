## ADDED Requirements
### Requirement: Monitors Read Task-Scoped Runtime State

Pipeline monitors SHALL read active task status from task-local runtime files rather than relying on global latest-summary scans or implicit folder placement alone.

Task-local monitor inputs include:
- `state.json`
- `meta.json`
- `events.jsonl`
- `handoff.md`
- `summary.md`

#### Scenario: Monitor shows current step from state file
- **WHEN** a task is running and its `state.json` indicates `current_step=tester`
- **THEN** the monitor displays `tester` as the active step
- **AND** does not infer the active step only from handoff section order

#### Scenario: Monitor opens current task artifacts
- **WHEN** an operator requests the active task handoff or summary
- **THEN** the monitor opens `handoff.md` or `summary.md` from that task's own directory
- **AND** does not select an unrelated latest summary from another task

### Requirement: Monitors Visualize Rework Attempts

Pipeline monitors SHALL visualize rework loops using step statuses and attempt counters from `state.json`.

#### Scenario: Rework loop shown in monitor
- **WHEN** a task has `reviewer=rework_requested` and `coder.attempt=2`
- **THEN** the monitor shows that rework was requested
- **AND** indicates that coder is running or pending on attempt 2
