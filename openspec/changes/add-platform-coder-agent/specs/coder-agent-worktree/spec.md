## ADDED Requirements

### Requirement: Worktree Creation for Task Isolation

The system SHALL create a git worktree for each task before pipeline execution begins. The worktree SHALL be created at `.opencode/pipeline/worktrees/<task-slug>` on a new branch named `pipeline/<task-slug>`. The task slug SHALL be derived from the task title using kebab-case conversion, truncated to 60 characters, with non-ASCII characters transliterated. The worktree path SHALL be recorded in `coder_tasks.worktree_path`.

#### Scenario: Worktree created for new task
- **WHEN** a worker begins processing a task titled "Add streaming support to A2A"
- **THEN** a git worktree is created at `.opencode/pipeline/worktrees/add-streaming-support-to-a2a`
- **AND** a branch `pipeline/add-streaming-support-to-a2a` is created from the current HEAD
- **AND** `coder_tasks.worktree_path` is updated with the absolute path

#### Scenario: Task slug handles non-ASCII characters
- **WHEN** a task has a Ukrainian title
- **THEN** the slug is transliterated to ASCII kebab-case
- **AND** the worktree and branch are created with the transliterated slug

### Requirement: Worktree Dependency Installation

The system SHALL run dependency installation (e.g., `composer install`) in the newly created worktree before pipeline execution begins. Dependency installation failures SHALL cause the task to fail with a clear error message.

#### Scenario: Dependencies installed successfully
- **WHEN** a worktree is created for a task
- **THEN** `composer install` is executed in the worktree directory
- **AND** pipeline execution begins after dependencies are ready

#### Scenario: Dependency installation fails
- **WHEN** `composer install` fails in the worktree
- **THEN** the task moves to `failed` status
- **AND** the error output is recorded in the task log

### Requirement: Worktree Cleanup

The system SHALL automatically remove the worktree and its associated branch when a task completes successfully. For failed tasks, the worktree SHALL be preserved for debugging purposes. A scheduled cleanup command SHALL detect and remove stale worktrees (associated with tasks that have been in `failed` or `cancelled` status for more than 7 days).

#### Scenario: Successful task worktree cleanup
- **WHEN** a task completes with `done` status
- **THEN** the git worktree is removed via `git worktree remove`
- **AND** the branch `pipeline/<task-slug>` is preserved for review
- **AND** `coder_tasks.worktree_path` is set to NULL

#### Scenario: Failed task worktree preserved
- **WHEN** a task moves to `failed` status
- **THEN** the worktree is NOT removed
- **AND** the admin can inspect the worktree contents for debugging

#### Scenario: Stale worktree cleanup
- **WHEN** the scheduled cleanup command runs
- **AND** a worktree is associated with a task that has been `failed` for more than 7 days
- **THEN** the worktree is removed
- **AND** a log entry is created recording the cleanup

### Requirement: Concurrent Worktree Isolation

The system SHALL ensure that multiple workers operating on different tasks use separate worktrees with no filesystem overlap. Each worktree SHALL operate on its own branch, preventing git conflicts between concurrent tasks.

#### Scenario: Two workers run concurrently
- **WHEN** worker-1 processes task A and worker-2 processes task B simultaneously
- **THEN** task A operates in worktree `.opencode/pipeline/worktrees/<slug-a>`
- **AND** task B operates in worktree `.opencode/pipeline/worktrees/<slug-b>`
- **AND** changes in one worktree do not affect the other
