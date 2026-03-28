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

### Requirement: Monitors Visualize Rework Attempts Per Agent

Pipeline monitors SHALL visualize rework loops using per-agent attempt history from `state.json`.

The monitor SHALL group agent entries by attempt number and display attempt boundaries with visual separators.

The monitor SHALL indicate which agent requested rework and which agent is being rerun.

#### Scenario: Rework loop shown in monitor
- **WHEN** a task has `reviewer=rework_requested` and `coder.attempt=2`
- **THEN** the monitor shows that rework was requested by the reviewer
- **AND** indicates that coder is running or pending on attempt 2

#### Scenario: Per-agent attempt history in agents tab
- **WHEN** a task has completed 2 attempts with different agent durations
- **THEN** the agents tab groups entries by attempt number
- **AND** shows attempt headers separating attempt 1 and attempt 2 entries
- **AND** operators can compare agent performance across attempts

#### Scenario: Retry timeline in detail view
- **WHEN** a task has been retried multiple times
- **THEN** the detail view shows a timeline with attempt boundaries
- **AND** each attempt shows its agents with status and duration
- **AND** `rework_requested` entries are highlighted in yellow
