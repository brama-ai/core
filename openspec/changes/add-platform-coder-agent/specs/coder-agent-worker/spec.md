## ADDED Requirements

### Requirement: Background Worker Process

The system SHALL provide a Symfony console command `coder:worker:start` that runs a long-lived background process. The worker SHALL poll the Redis priority queue for tasks, claim tasks atomically, execute the pipeline, and return to polling upon completion. Each worker SHALL have a unique identifier (e.g., "worker-1"). The worker SHALL register itself in the `coder_workers` table on startup and deregister on shutdown.

#### Scenario: Worker starts and polls for tasks
- **WHEN** `bin/console coder:worker:start --id=worker-1` is executed
- **THEN** a worker record with id "worker-1" and status "idle" is created in `coder_workers`
- **AND** the worker begins polling the Redis priority queue

#### Scenario: Worker claims and processes a task
- **WHEN** a task is available in the priority queue
- **THEN** the worker atomically claims the task (preventing other workers from claiming it)
- **AND** the worker status changes to "busy" with `current_task_id` set
- **AND** the task status changes to `in_progress`
- **AND** the pipeline orchestrator is invoked for the task

#### Scenario: Worker completes a task and returns to polling
- **WHEN** the pipeline completes successfully for a task
- **THEN** the task status changes to `done`
- **AND** the worker status changes to "idle" with `current_task_id` cleared
- **AND** the worker resumes polling the queue

### Requirement: Worker Heartbeat

The system SHALL update the `last_heartbeat_at` field in `coder_workers` every 30 seconds while a worker is running. Workers that have not sent a heartbeat for more than 2 minutes SHALL be considered dead. Dead workers' in-progress tasks SHALL be eligible for reassignment.

#### Scenario: Active worker sends heartbeat
- **WHEN** a worker is running (idle or busy)
- **THEN** the worker updates `last_heartbeat_at` every 30 seconds

#### Scenario: Dead worker detected
- **WHEN** a worker's `last_heartbeat_at` is more than 2 minutes old
- **THEN** the worker is flagged as dead in the admin UI
- **AND** any task assigned to the dead worker is eligible for reassignment to another worker

### Requirement: Worker Lifecycle Management

The system SHALL provide commands to stop workers gracefully (`coder:worker:stop`) and query worker status (`coder:worker:status`). A graceful stop SHALL wait for the current task to complete before shutting down. The system SHALL enforce a configurable maximum worker count; attempts to start workers beyond the limit SHALL be rejected.

#### Scenario: Graceful worker shutdown
- **WHEN** `bin/console coder:worker:stop --id=worker-1` is executed
- **AND** worker-1 is processing a task
- **THEN** the worker finishes the current task
- **AND** the worker status changes to "stopped"
- **AND** the worker process exits

#### Scenario: Worker count limit enforced
- **WHEN** the max worker count is set to 2
- **AND** 2 workers are already running
- **AND** `bin/console coder:worker:start --id=worker-3` is executed
- **THEN** the command exits with an error indicating the worker limit has been reached

### Requirement: Priority Queue

The system SHALL maintain a Redis sorted set for task ordering. Tasks SHALL be scored by a composite key of priority (descending) and creation timestamp (ascending), ensuring higher-priority tasks are dequeued first and equal-priority tasks are processed in FIFO order. Tasks waiting longer than 24 hours SHALL receive an automatic priority boost to prevent starvation.

#### Scenario: Higher priority task dequeued first
- **WHEN** task A has priority 1 and task B has priority 10
- **AND** both are in the queue
- **THEN** task B is dequeued before task A

#### Scenario: Equal priority FIFO ordering
- **WHEN** task A and task B both have priority 5
- **AND** task A was created before task B
- **THEN** task A is dequeued before task B

#### Scenario: Starvation prevention
- **WHEN** a task has been in the queue for more than 24 hours
- **THEN** the task's effective priority is boosted
- **AND** the task moves up in the queue relative to newer tasks at the same original priority

### Requirement: Task State Machine

The system SHALL enforce valid task state transitions. The allowed transitions are: `todo` -> `queued`, `queued` -> `in_progress`, `queued` -> `cancelled`, `in_progress` -> `done`, `in_progress` -> `failed`, `in_progress` -> `cancelled`, `failed` -> `queued` (retry), `cancelled` -> `queued` (re-queue). Invalid transitions SHALL be rejected with an error.

#### Scenario: Valid state transition
- **WHEN** a task in `queued` status is claimed by a worker
- **THEN** the status transitions to `in_progress`

#### Scenario: Invalid state transition rejected
- **WHEN** an attempt is made to move a `done` task to `in_progress`
- **THEN** the transition is rejected with an error message
- **AND** the task status remains `done`
