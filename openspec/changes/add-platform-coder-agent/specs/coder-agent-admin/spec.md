## ADDED Requirements

### Requirement: Task Management Dashboard

The system SHALL provide an admin page at `/admin/coder/` that displays all coding tasks in a sortable, filterable table. The table SHALL show task title, status, priority, current stage, worker assignment, and timing (created, started, finished). The table SHALL support filtering by status and sorting by priority or creation date.

#### Scenario: Admin views task list
- **WHEN** an admin navigates to `/admin/coder/`
- **THEN** a table of all coding tasks is displayed
- **AND** tasks are sorted by priority (descending) then creation date (ascending) by default
- **AND** each row shows title, status badge, priority, current stage, worker ID, and timestamps

#### Scenario: Admin filters tasks by status
- **WHEN** an admin selects "in_progress" from the status filter
- **THEN** only tasks with `in_progress` status are shown

### Requirement: Task Creation and Editing

The system SHALL provide forms for creating and editing coding tasks. The creation form SHALL include a template selector, title field, markdown description editor, priority selector, and pipeline configuration options (skip stages, model overrides). Task templates SHALL pre-fill the description and pipeline config based on the selected template type.

#### Scenario: Admin creates a task from template
- **WHEN** an admin selects the "Feature" template on the task creation form
- **THEN** the description field is pre-filled with the feature template markdown
- **AND** all 5 pipeline stages are enabled by default
- **AND** the admin can modify any field before submitting

#### Scenario: Admin creates a bug fix task
- **WHEN** an admin selects the "Bug Fix" template
- **THEN** the description is pre-filled with the bug fix template
- **AND** only coder, validator, and tester stages are enabled (architect and documenter skipped)

#### Scenario: Admin edits task priority
- **WHEN** an admin changes a task's priority from 1 to 10
- **AND** the task status is `todo` or `queued`
- **THEN** the task's position in the queue is updated to reflect the new priority

### Requirement: Task Detail and Log Viewer

The system SHALL provide a task detail page showing the full task description, pipeline stage progress timeline, and a scrollable log viewer. The log viewer SHALL display log entries filtered by stage and severity level. The page SHALL update in real-time via SSE when the task is in progress.

#### Scenario: Admin views in-progress task detail
- **WHEN** an admin opens the detail page for an in-progress task
- **THEN** the page shows the task description, a stage progress timeline with the current stage highlighted, and a live log stream
- **AND** new log entries appear automatically without page refresh

#### Scenario: Admin views completed task logs
- **WHEN** an admin opens the detail page for a completed task
- **THEN** the full log history is available, filterable by stage
- **AND** each stage shows its duration, model used, and pass/fail status

### Requirement: Task Actions

The system SHALL provide action buttons on the task list and detail pages. Available actions SHALL depend on task status: `todo` tasks can be queued, edited, or deleted; `queued` tasks can be cancelled; `in_progress` tasks can be cancelled; `failed` tasks can be retried or deleted; `done` tasks can be deleted.

#### Scenario: Admin queues a todo task
- **WHEN** an admin clicks "Queue" on a task with `todo` status
- **THEN** the task status changes to `queued`
- **AND** the task is added to the Redis priority queue

#### Scenario: Admin cancels a running task
- **WHEN** an admin clicks "Cancel" on a task with `in_progress` status
- **THEN** the worker processing the task receives a cancellation signal
- **AND** the task status changes to `cancelled`
- **AND** the worktree is preserved for inspection

#### Scenario: Admin retries a failed task
- **WHEN** an admin clicks "Retry" on a task with `failed` status
- **THEN** the task status resets to `queued`
- **AND** the retry count is incremented
- **AND** the task re-enters the priority queue

### Requirement: Real-Time Monitoring via SSE

The system SHALL provide a Server-Sent Events endpoint at `GET /admin/coder/events` that streams task and worker status updates to connected admin browsers. Events SHALL include task status changes, stage transitions, log entries, and worker heartbeats. The SSE endpoint SHALL use Redis pub/sub internally to avoid database polling.

#### Scenario: Dashboard receives live task update
- **WHEN** an admin has the coder dashboard open
- **AND** a worker transitions a task from `in_progress` to `done`
- **THEN** the dashboard receives a `task.status_changed` SSE event
- **AND** the task row updates without page refresh

#### Scenario: Task detail receives live log
- **WHEN** an admin has a task detail page open
- **AND** the worker writes a new log entry for that task
- **THEN** the page receives a `task.log` SSE event
- **AND** the new log entry appears in the log viewer

### Requirement: Worker Management Panel

The system SHALL provide a worker management section within the coder dashboard showing all registered workers with their status, current task, and last heartbeat time. The admin SHALL be able to adjust the maximum worker count. Dead workers (no heartbeat for > 2 minutes) SHALL be visually flagged.

#### Scenario: Admin views worker status
- **WHEN** an admin opens the coder dashboard
- **THEN** a worker panel shows each worker's ID, status (idle/busy/stopped), current task (if any), and last heartbeat
- **AND** workers with no heartbeat for over 2 minutes are shown with a warning indicator

#### Scenario: Admin adjusts max workers
- **WHEN** an admin changes the max worker count from 2 to 4
- **THEN** the setting is persisted
- **AND** new workers can be started up to the new limit

### Requirement: Task Templates

The system SHALL provide predefined task templates: ADR (Architecture Decision Record), HLD (High-Level Design), Feature, Bug Fix, and Refactor. Each template SHALL define a default description scaffold and default pipeline stages to include. Templates SHALL be selectable during task creation.

#### Scenario: Template list displayed on creation form
- **WHEN** an admin opens the task creation form
- **THEN** a template selector shows all available templates plus a "Custom" option
- **AND** selecting a template populates the description and pipeline config

#### Scenario: Custom task without template
- **WHEN** an admin selects "Custom" from the template selector
- **THEN** the description field is empty
- **AND** all 5 pipeline stages are enabled by default
