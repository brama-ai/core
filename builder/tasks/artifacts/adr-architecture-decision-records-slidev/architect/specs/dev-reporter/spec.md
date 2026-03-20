# Capability: Dev Reporter

Reports pipeline execution results, persists run history, and delivers notifications to Telegram through the platform's A2A infrastructure.

## ADDED Requirements

### Requirement: Pipeline Run Ingestion

The agent MUST accept pipeline run reports via A2A and persist them.

#### Scenario: Successful pipeline report ingestion

- **WHEN** Core sends an A2A message with intent `devreporter.ingest` containing `pipeline_id`, `task`, `branch`, `status`, `duration_seconds`, and `agent_results`
- **THEN** the agent stores the run in `pipeline_runs` table
- **THEN** the agent returns `{ "status": "completed", "run_id": <id> }`

#### Scenario: Pipeline report with failure details

- **WHEN** Core sends an ingest request with `status: "failed"` and `failed_agent: "validator"`
- **THEN** the agent stores the run with the failure details
- **THEN** the agent includes `failed_agent` in the stored record

#### Scenario: Invalid ingest payload

- **WHEN** Core sends an ingest request missing required fields (`task`, `status`)
- **THEN** the agent returns `{ "status": "failed", "error": "Missing required field: ..." }`

---

### Requirement: Development Status Queries

The agent MUST respond to status queries with aggregated pipeline data.

#### Scenario: Query recent pipeline runs

- **WHEN** Core sends an A2A message with intent `devreporter.status` and `query: "recent"`
- **THEN** the agent returns the last N pipeline runs (default 10) with task, branch, status, and duration

#### Scenario: Query with status filter

- **WHEN** Core sends a status query with `status_filter: "failed"`
- **THEN** the agent returns only failed pipeline runs

#### Scenario: Query with time range

- **WHEN** Core sends a status query with `days: 7`
- **THEN** the agent returns only runs from the last 7 days
- **THEN** the response includes aggregate stats: total runs, pass count, fail count, pass rate, average duration

---

### Requirement: Telegram Notification Delivery

The agent MUST deliver formatted messages to Telegram through the platform's messaging infrastructure.

#### Scenario: Notify on successful pipeline completion

- **WHEN** an ingest request completes with `status: "completed"`
- **THEN** the agent formats an HTML summary with task, branch, duration, and per-agent results
- **THEN** the agent sends the formatted message via Core's A2A to OpenClaw for Telegram delivery

#### Scenario: Notify on pipeline failure

- **WHEN** an ingest request completes with `status: "failed"`
- **THEN** the notification includes the failed agent name and a resume command hint
- **THEN** the message uses a red indicator to distinguish from success

#### Scenario: Custom notification via notify skill

- **WHEN** Core sends an A2A message with intent `devreporter.notify` and a `message` field
- **THEN** the agent delivers the message to Telegram as-is (HTML format)

#### Scenario: Notification delivery failure

- **WHEN** the Telegram delivery fails (OpenClaw unreachable or error response)
- **THEN** the agent logs the failure but does NOT fail the ingest operation
- **THEN** the stored pipeline run is not affected

---

### Requirement: Agent Manifest

The agent MUST expose a valid manifest following the platform's Agent Card specification.

#### Scenario: Manifest endpoint

- **WHEN** a GET request is made to `/api/v1/manifest`
- **THEN** the response contains agent name `dev-reporter-agent`, version, description, A2A URL, and declared skills
- **THEN** the skills array includes `devreporter.ingest`, `devreporter.status`, and `devreporter.notify`

---

### Requirement: Health Check

The agent MUST provide a health endpoint.

#### Scenario: Health check

- **WHEN** a GET request is made to `/health`
- **THEN** the response is `{"status": "ok"}` with HTTP 200

---

### Requirement: Admin Interface

The agent MUST provide an admin view for browsing pipeline run history.

#### Scenario: Admin pipeline runs list

- **WHEN** an admin navigates to the dev-reporter admin page
- **THEN** the page displays a table of pipeline runs sorted by date descending
- **THEN** each row shows: date, task, branch, status (colored), duration, and agent count

#### Scenario: Admin filter by status

- **WHEN** an admin selects a status filter (passed/failed/all)
- **THEN** the table updates to show only matching runs
