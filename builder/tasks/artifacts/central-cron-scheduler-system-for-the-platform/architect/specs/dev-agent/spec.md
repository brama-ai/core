# Capability: Dev Agent

Development orchestration agent — creates tasks, runs pipeline with live monitoring, and creates GitHub PRs.

## ADDED Requirements

### Requirement: Task Creation

The agent MUST accept task creation requests via A2A and admin UI.

#### Scenario: Create task via A2A

- **WHEN** Core sends an A2A message with intent `dev.create_task` containing `title` and `description`
- **THEN** the agent stores the task in `dev_tasks` table with status `draft`
- **THEN** the agent returns `{ "status": "completed", "data": { "task_id": <id> } }`

#### Scenario: Create task via admin UI

- **WHEN** a user fills in the task creation form and submits
- **THEN** the task is stored with title, description, and pipeline options
- **THEN** the user is redirected to the task detail page

#### Scenario: Create task with missing fields

- **WHEN** a create request is missing `title` or `description`
- **THEN** the agent returns `{ "status": "failed", "error": "title and description are required" }`

---

### Requirement: Task Refinement with LLM

The agent MUST support multi-turn task refinement via Claude Opus 4.6 through LiteLLM.

#### Scenario: Refine task description

- **WHEN** a user sends a message to the refinement endpoint with task description and chat history
- **THEN** the agent sends the conversation to Claude Opus 4.6 via LiteLLM
- **THEN** the agent returns the LLM response and updated chat history

#### Scenario: Accept refined specification

- **WHEN** a user accepts the Opus-refined specification
- **THEN** the refined spec is stored in the `refined_spec` column
- **THEN** the chat history is stored in the `chat_history` column

#### Scenario: LLM service unavailable

- **WHEN** the LiteLLM service returns an error or is unreachable
- **THEN** the refinement endpoint returns HTTP 502 with an error message
- **THEN** the task creation flow continues to work without refinement

---

### Requirement: Pipeline Execution

The agent MUST execute the development pipeline as a subprocess and capture output in real-time.

#### Scenario: Queue task for pipeline execution

- **WHEN** Core sends an A2A message with intent `dev.run_pipeline` and a valid `task_id`
- **THEN** the agent sets the task status to `pending`
- **THEN** the background worker picks up the task within 5 seconds

#### Scenario: Pipeline execution with live logging

- **WHEN** the worker starts a pipeline for a pending task
- **THEN** the runner executes `pipeline.sh` via `proc_open`
- **THEN** each stdout/stderr line is stored in `dev_task_logs` with agent step and level
- **THEN** the task status is updated to `running` with branch and pipeline_id

#### Scenario: Pipeline success

- **WHEN** the pipeline process exits with code 0
- **THEN** the task status is updated to `success`
- **THEN** the `finished_at` and `duration_seconds` are recorded
- **THEN** the agent attempts to create a GitHub PR

#### Scenario: Pipeline failure

- **WHEN** the pipeline process exits with non-zero code
- **THEN** the task status is updated to `failed`
- **THEN** the `finished_at` and `duration_seconds` are recorded
- **THEN** the agent does NOT attempt to create a PR

#### Scenario: Reject running pipeline for non-draft/failed tasks

- **WHEN** a run_pipeline request targets a task not in `draft` or `failed` status
- **THEN** the agent returns `{ "status": "failed", "error": "Task must be in draft or failed status to run" }`

---

### Requirement: Live Log Streaming via SSE

The agent MUST provide a Server-Sent Events endpoint for streaming pipeline logs.

#### Scenario: Connect to log stream

- **WHEN** a client connects to `GET /admin/tasks/api/{id}/logs/stream?last_id=0`
- **THEN** the response has `Content-Type: text/event-stream`
- **THEN** new log entries are sent as SSE data events with JSON payload

#### Scenario: Incremental log delivery

- **WHEN** the client provides a `last_id` parameter
- **THEN** only log entries with `id > last_id` are sent
- **THEN** each event includes the entry `id` for reconnection tracking

#### Scenario: Stream completion

- **WHEN** the task reaches a terminal status (success, failed, cancelled)
- **THEN** the server sends an `event: complete` with the final status
- **THEN** the server closes the connection

#### Scenario: Heartbeat for connection keepalive

- **WHEN** no new logs are available for 15 seconds
- **THEN** the server sends a comment line (`: heartbeat`) to keep the connection alive

---

### Requirement: GitHub PR Creation

The agent MUST create GitHub PRs after successful pipeline runs when configured.

#### Scenario: Auto-create PR on success

- **WHEN** a pipeline completes successfully and `GH_TOKEN` is set
- **THEN** the agent pushes the branch to origin
- **THEN** the agent creates a PR using `gh pr create`
- **THEN** the PR URL is stored in the task's `pr_url` column

#### Scenario: Skip PR when GH_TOKEN not set

- **WHEN** a pipeline completes successfully and `GH_TOKEN` is empty
- **THEN** the agent logs an info message and skips PR creation
- **THEN** the task status remains `success` without a PR URL

#### Scenario: PR creation failure

- **WHEN** the `gh pr create` command fails
- **THEN** the agent logs a warning but does NOT change the task status
- **THEN** the error is recorded in the task logs

---

### Requirement: Task Status Queries

The agent MUST respond to status queries via A2A.

#### Scenario: Query task status

- **WHEN** Core sends an A2A message with intent `dev.get_status` and `task_id`
- **THEN** the agent returns task details including status, branch, PR URL, and log count

#### Scenario: List recent tasks

- **WHEN** Core sends an A2A message with intent `dev.list_tasks`
- **THEN** the agent returns the most recent tasks (default 20) with id, title, status, branch, PR URL, and creation date

#### Scenario: Filter tasks by status

- **WHEN** a list request includes `status_filter`
- **THEN** only tasks matching the filter are returned

---

### Requirement: Agent Manifest

The agent MUST expose a valid manifest following the platform's Agent Card specification.

#### Scenario: Manifest endpoint

- **WHEN** a GET request is made to `/api/v1/manifest`
- **THEN** the response contains agent name `dev-agent`, version, description, A2A URL, and declared skills
- **THEN** the skills array includes `dev.create_task`, `dev.run_pipeline`, `dev.get_status`, and `dev.list_tasks`

---

### Requirement: Health Check

The agent MUST provide a health endpoint.

#### Scenario: Health check

- **WHEN** a GET request is made to `/health`
- **THEN** the response is `{"status": "ok", "service": "dev-agent"}` with HTTP 200

---

### Requirement: Admin Interface

The agent MUST provide admin views for managing development tasks.

#### Scenario: Task list page

- **WHEN** an admin navigates to `/admin/tasks`
- **THEN** the page displays stats (total, active, success, failed, draft) for the last 7 days
- **THEN** the page displays a table of tasks sorted by date descending
- **THEN** each row shows: id, title, status (colored badge), branch, duration, PR link, creation date

#### Scenario: Filter tasks by status in admin

- **WHEN** an admin selects a status filter (all, running, success, failed, draft)
- **THEN** the table updates to show only matching tasks

#### Scenario: Task creation page

- **WHEN** an admin navigates to `/admin/tasks/create`
- **THEN** the page displays a form with title, description, and pipeline options
- **THEN** the page includes a "Refine with Opus" button that opens a chat interface
- **THEN** the page includes a "Create Task" button

#### Scenario: Task detail page with live logs

- **WHEN** an admin navigates to `/admin/tasks/{id}`
- **THEN** the page displays task metadata (status, branch, pipeline ID, duration, PR link)
- **THEN** the page displays the refined specification (if present)
- **THEN** the page displays a log panel with all pipeline log entries
- **WHEN** the task is running
- **THEN** the log panel auto-updates via SSE with live entries
