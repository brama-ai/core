## ADDED Requirements
### Requirement: Ultraworks Monitor Shows Task Isolation Metadata

The pipeline monitor SHALL expose the active worktree path and branch name for an Ultraworks task launched through the runtime wrapper.

#### Scenario: Status shows branch and worktree for active run
- **WHEN** an Ultraworks task is running through the launcher
- **THEN** the status view shows the active branch name
- **AND** the status view shows the active worktree path

#### Scenario: Failure report references preserved worktree
- **WHEN** an Ultraworks task fails
- **THEN** the monitor or summary output shows the preserved worktree path
- **AND** the operator can use that path to inspect the failed state
