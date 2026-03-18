## ADDED Requirements

### Requirement: Scheduled Jobs Database Table

The platform SHALL persist scheduled jobs in a `scheduled_jobs` PostgreSQL table with columns for agent name, job name, skill ID, payload, cron expression, next/last run timestamps, retry state, and enabled flag. The table SHALL have a unique constraint on `(agent_name, job_name)` and an index on `(enabled, next_run_at)`.

#### Scenario: Migration creates scheduled_jobs table

- **WHEN** the Doctrine migration `Version20260310000001` is executed
- **THEN** the `scheduled_jobs` table exists with all required columns, the unique constraint, and the polling index

#### Scenario: Duplicate job registration is idempotent

- **WHEN** a job with the same `(agent_name, job_name)` is registered twice
- **THEN** the second registration updates the existing row instead of creating a duplicate

### Requirement: Scheduler Polling Command

The platform SHALL provide a long-running Symfony command `scheduler:run` that polls the `scheduled_jobs` table every 10 seconds for due jobs (where `enabled = TRUE` and `next_run_at <= now()`), invokes each job's skill via `A2AClient`, and updates the job's state after execution.

#### Scenario: Due job is executed

- **WHEN** a job has `enabled = TRUE` and `next_run_at` is in the past or present
- **THEN** the scheduler invokes `A2AClient::invoke()` with the job's `skill_id` and `payload`
- **AND** updates `last_run_at` to the current time and `last_status` to `completed`
- **AND** computes `next_run_at` from the cron expression (if periodic)

#### Scenario: One-shot job completes

- **WHEN** a job has `cron_expression = NULL` and executes successfully
- **THEN** the job is disabled (`enabled = FALSE`) after execution since there is no next run

#### Scenario: Graceful shutdown on SIGTERM

- **WHEN** the scheduler process receives SIGTERM or SIGINT
- **THEN** it finishes the current tick and exits without killing in-flight A2A calls

### Requirement: Scheduler Retry and Dead-Letter Policy

The scheduler SHALL retry failed jobs according to their `max_retries` and `retry_delay_seconds` configuration. When retries are exhausted, the job SHALL be disabled (dead-lettered).

#### Scenario: Failed job is retried

- **WHEN** an A2A invocation fails (exception or `status: failed` response)
- **THEN** `retry_count` is incremented by 1
- **AND** `next_run_at` is set to `now() + retry_delay_seconds`
- **AND** `last_status` is set to `failed`

#### Scenario: Job exceeds max retries

- **WHEN** `retry_count >= max_retries` after a failure
- **THEN** the job is disabled (`enabled = FALSE`)
- **AND** a warning is logged with the job details

#### Scenario: Successful run resets retry count

- **WHEN** a job executes successfully after previous failures
- **THEN** `retry_count` is reset to 0

### Requirement: Scheduler Catch-Up Policy

The scheduler SHALL handle missed runs (e.g., after a restart) by executing the job once and computing the next run from the current time, rather than replaying all missed intervals.

#### Scenario: Scheduler restarts with overdue job

- **WHEN** the scheduler starts and a job has `next_run_at` in the past
- **THEN** the job is executed once on the next tick
- **AND** `next_run_at` is computed from the current time using the cron expression

### Requirement: Scheduler Concurrency Control

The scheduler SHALL prevent duplicate concurrent execution of the same job using PostgreSQL row-level locking.

#### Scenario: Concurrent scheduler instances

- **WHEN** two scheduler instances poll for due jobs simultaneously
- **THEN** each job is picked by at most one instance (via `FOR UPDATE SKIP LOCKED`)
- **AND** no job is executed twice for the same scheduled run

### Requirement: Manifest-Driven Job Registration

Agents SHALL declare scheduled jobs in their `manifest.json` under a `scheduled_jobs` array. The platform SHALL register these jobs during agent install and remove them during uninstall.

#### Scenario: Agent install registers scheduled jobs

- **WHEN** an agent with `scheduled_jobs` in its manifest is installed
- **THEN** each declared job is inserted into the `scheduled_jobs` table with `enabled = TRUE` and `next_run_at` computed from the cron expression

#### Scenario: Agent uninstall removes scheduled jobs

- **WHEN** an agent is uninstalled
- **THEN** all rows in `scheduled_jobs` with that agent's name are deleted

#### Scenario: Agent disable pauses scheduled jobs

- **WHEN** an agent is disabled
- **THEN** all its scheduled jobs are set to `enabled = FALSE`

#### Scenario: Agent enable resumes scheduled jobs

- **WHEN** a previously disabled agent is enabled
- **THEN** all its scheduled jobs are set to `enabled = TRUE` and `next_run_at` is recomputed

### Requirement: Scheduler Admin Page

The platform SHALL provide an admin page at `/admin/scheduler` showing all scheduled jobs with their current state, and controls for manual triggering and enabling/disabling.

#### Scenario: Admin views scheduler dashboard

- **WHEN** an authenticated admin navigates to `/admin/scheduler`
- **THEN** a table is displayed with columns: Agent, Job, Skill, Cron, Next Run, Last Run, Status, Enabled

#### Scenario: Admin triggers job manually

- **WHEN** an admin clicks "Run Now" for a job
- **THEN** the job's `next_run_at` is set to `now()` so it executes on the next scheduler tick

#### Scenario: Admin toggles job enabled state

- **WHEN** an admin toggles a job's enabled state
- **THEN** the job's `enabled` flag is updated and the change takes effect on the next scheduler tick

### Requirement: Scheduler Execution Logs

The platform SHALL record every job execution attempt in a `scheduler_job_logs` table, capturing start time, finish time, status, error details, payload sent, and response received. Each log entry SHALL reference the originating job.

#### Scenario: Successful execution is logged

- **WHEN** the scheduler executes a job successfully
- **THEN** a log entry is created with `status = 'completed'`, `started_at`, `finished_at`, the payload sent to the agent, and the response received

#### Scenario: Failed execution is logged

- **WHEN** a job execution fails (A2A error or exception)
- **THEN** a log entry is created with `status = 'failed'`, `started_at`, `finished_at`, and `error_message` containing the failure details

#### Scenario: Log entry is created before execution starts

- **WHEN** the scheduler picks a due job for execution
- **THEN** a log entry with `status = 'running'` and `started_at` is created before the A2A call is made
- **AND** the entry is updated with final status and `finished_at` after the call completes

#### Scenario: Logs persist when job is deleted

- **WHEN** a scheduled job is deleted (agent uninstall or admin deletion)
- **THEN** existing log entries remain in the database with `job_id = NULL` (FK SET NULL) for audit trail

### Requirement: Scheduler Log Viewer Admin Page

The platform SHALL provide an admin page at `/admin/scheduler/{id}/logs` showing the execution history of a specific job, with pagination and status badges.

#### Scenario: Admin views job execution history

- **WHEN** an authenticated admin navigates to `/admin/scheduler/{id}/logs`
- **THEN** a table is displayed with columns: Started, Finished, Duration, Status (badge), Error (truncated), Payload
- **AND** results are paginated with 50 entries per page

#### Scenario: Admin navigates to logs from scheduler dashboard

- **WHEN** an admin clicks the "Логи" link for a job on `/admin/scheduler`
- **THEN** the browser navigates to `/admin/scheduler/{id}/logs` for that job

#### Scenario: Log status badges match job outcomes

- **WHEN** a log entry has `status = 'completed'`
- **THEN** it displays a green/info badge
- **WHEN** a log entry has `status = 'failed'`
- **THEN** it displays a red/error badge
- **WHEN** a log entry has `status = 'running'`
- **THEN** it displays a yellow/warning badge (stuck or in-progress execution)

### Requirement: Visual Cron Builder

The scheduler admin UI SHALL provide a visual cron expression builder alongside the classic text input, allowing non-technical users to configure cron schedules via a clickable interface. The builder SHALL use @vue-js-cron/light loaded via CDN.

#### Scenario: Visual builder is available in create modal

- **WHEN** an admin opens the "Створити завдання" modal
- **THEN** a visual cron builder is displayed alongside the text input for cron expression
- **AND** the builder provides clickable controls for minute, hour, day-of-month, month, and day-of-week

#### Scenario: Builder and text input are bidirectionally synced

- **WHEN** the user changes the cron expression via the visual builder
- **THEN** the text input updates to reflect the new expression
- **WHEN** the user types a valid cron expression in the text input
- **THEN** the visual builder updates to reflect the typed expression

#### Scenario: Mode toggle between visual and text

- **WHEN** the user clicks the mode toggle button
- **THEN** the UI switches between "Візуальний" (visual builder) and "Текстовий" (classic text input) modes
- **AND** the cron expression value is preserved across mode switches

#### Scenario: Vue is loaded only on scheduler page

- **WHEN** the admin navigates to any page other than `/admin/scheduler`
- **THEN** Vue 3 and @vue-js-cron/light scripts are NOT loaded
- **AND** no global JavaScript state is affected

### Requirement: Scheduler Docker Service

The scheduler SHALL run as a separate Docker Compose service (`core-scheduler`) using the same image as core, with restart policy `unless-stopped`.

#### Scenario: Scheduler service starts with Docker Compose

- **WHEN** `docker compose up` is run
- **THEN** the `core-scheduler` service starts and begins polling for due jobs
- **AND** the service restarts automatically if it crashes
