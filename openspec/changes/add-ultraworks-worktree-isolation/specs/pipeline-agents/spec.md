## ADDED Requirements
### Requirement: Ultraworks Tasks Use Isolated Git Worktrees By Default

The `Ultraworks` runtime SHALL execute each launched task inside its own git worktree by default instead of reusing the shared repository checkout.

Each task worktree SHALL be created on its own reviewable branch using the `pipeline/<task-slug>` naming convention unless the operator explicitly overrides the branch name.

#### Scenario: Launch creates isolated worktree
- **WHEN** an operator starts `Ultraworks` with a task description through the launcher
- **THEN** the runtime creates a dedicated git worktree for that task
- **AND** the task runs inside that worktree instead of the repository root
- **AND** the runtime records the worktree path and branch name

#### Scenario: Parallel Ultraworks tasks do not share checkout state
- **WHEN** two Ultraworks tasks run in parallel
- **THEN** each task uses a different worktree path and branch
- **AND** git changes from one task do not appear in the other task's working tree

### Requirement: Ultraworks Continuity Files Are Task-Scoped

The `Ultraworks` runtime SHALL keep continuity artifacts task-scoped through the active worktree so concurrent tasks do not overwrite each other's state.

Task-scoped continuity artifacts include:
- `.opencode/pipeline/handoff.md`
- `.opencode/pipeline/plan.json`
- `.opencode/pipeline/logs/*`
- `.opencode/pipeline/reports/*`

#### Scenario: Concurrent runs keep separate handoff files
- **WHEN** two Ultraworks runs are active at the same time
- **THEN** each run writes to its own task-scoped `.opencode/pipeline/handoff.md`
- **AND** updates from one run do not overwrite the other run's handoff

#### Scenario: Failed run preserves task worktree
- **WHEN** an Ultraworks task fails
- **THEN** the runtime preserves the task worktree by default for debugging
- **AND** the operator can inspect the saved worktree path and branch metadata
