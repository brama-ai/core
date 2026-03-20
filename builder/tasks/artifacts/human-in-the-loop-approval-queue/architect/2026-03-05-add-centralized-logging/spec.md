## ADDED Requirements

### Requirement: Centralized Log Collection
All platform applications (core, knowledge-agent, hello-agent, news-maker-agent) SHALL send structured log entries to OpenSearch via HTTP `_bulk` API. Log entries SHALL include: timestamp, level, message, channel, app_name, trace_id, request_id. Handlers SHALL buffer entries (max 50) and flush on close or buffer overflow. Handlers SHALL fail silently — logging MUST NOT crash the application.

#### Scenario: PHP app logs to OpenSearch
- **WHEN** a PHP application emits a log via Monolog
- **THEN** the OpenSearchHandler buffers the entry and flushes to OpenSearch `_bulk` API
- **AND** the log entry includes trace_id, request_id, app_name, request_uri, request_method, client_ip

#### Scenario: Python app logs to OpenSearch
- **WHEN** the news-maker-agent emits a log via Python stdlib logging
- **THEN** the OpenSearchHandler sends the entry to OpenSearch with matching schema

#### Scenario: OpenSearch unavailable
- **WHEN** OpenSearch is unreachable
- **THEN** the handler fails silently and the application continues to function normally

### Requirement: Trace ID Propagation
Every HTTP request SHALL receive a trace_id (from `X-Trace-Id` header or auto-generated UUID v4) and a unique request_id. These IDs SHALL be included in all log entries and returned via response headers.

#### Scenario: Incoming request with trace ID
- **WHEN** an HTTP request includes `X-Trace-Id` header
- **THEN** the trace_id from the header is used for all logs in that request
- **AND** `X-Trace-Id` and `X-Request-Id` headers are included in the response

#### Scenario: Incoming request without trace ID
- **WHEN** an HTTP request does not include `X-Trace-Id` header
- **THEN** a new UUID v4 trace_id is generated
- **AND** the trace_id is propagated to downstream A2A calls

### Requirement: Admin Log Viewer
The platform SHALL provide a web-based log viewer at `/admin/logs` accessible to authenticated admin users. The viewer SHALL support: full-text search, level filtering, app filtering, date range filtering, and pagination (50 entries per page).

#### Scenario: View logs
- **WHEN** an admin navigates to `/admin/logs`
- **THEN** recent logs are displayed in a table with timestamp, level badge, app name, message, and trace link

#### Scenario: Filter by level
- **WHEN** an admin selects a log level filter (e.g., ERROR)
- **THEN** only logs of that level are displayed

#### Scenario: View trace
- **WHEN** an admin clicks a trace_id link
- **THEN** the admin is navigated to `/admin/logs/trace/{traceId}`
- **AND** all log entries for that trace are displayed in chronological order across all services

### Requirement: Log Index Management
The platform SHALL use daily OpenSearch indices (`platform_logs_YYYY_MM_DD`) with an index template for consistent mapping. The `logs:index:setup` command SHALL create the template and today's index.

#### Scenario: Setup indices
- **WHEN** an operator runs `logs:index:setup`
- **THEN** an index template is created with the defined mapping
- **AND** today's daily index is created if it does not exist

### Requirement: Log Cleanup
The platform SHALL provide a `logs:cleanup` command that removes old log indices. It SHALL support age-based retention (default 7 days) and size-based retention (default 2 GB). The command SHALL support `--dry-run` mode.

#### Scenario: Cleanup by age
- **WHEN** `logs:cleanup` runs with `--max-age=7`
- **THEN** indices older than 7 days are deleted

#### Scenario: Cleanup by size
- **WHEN** total index size exceeds `--max-size-gb=2` after age cleanup
- **THEN** oldest indices are deleted until total size is under the limit

#### Scenario: Dry run
- **WHEN** `logs:cleanup --dry-run` is executed
- **THEN** indices that would be deleted are listed but not actually deleted

### Requirement: Runtime Log Level Configuration
The admin settings page SHALL allow configuring the minimum log level written to OpenSearch (DEBUG, INFO, WARNING, ERROR). Changes SHALL take effect on the next request without application restart. Settings SHALL be stored in a JSON file (`var/log_settings.json`).

#### Scenario: Change log level
- **WHEN** an admin changes the log level to WARNING in settings
- **THEN** only WARNING and above logs are written to OpenSearch on subsequent requests

#### Scenario: Default settings
- **WHEN** the settings file does not exist
- **THEN** defaults are used: log_level=DEBUG, retention_days=7, max_size_gb=2

### Requirement: Application Log Instrumentation
Core app controllers and services SHALL emit structured log entries at critical integration points: incoming API requests, A2A calls, agent discovery sync, health polling results, and agent registry mutations.

#### Scenario: A2A invocation logged
- **WHEN** OpenClaw invokes a tool via `/api/v1/agents/invoke`
- **THEN** INFO-level logs are emitted with tool name, trace_id, agent name, duration, and status

#### Scenario: Health poll logged
- **WHEN** the health poller detects an agent threshold breach
- **THEN** a WARNING-level log is emitted with the agent name and failure count

### Requirement: OpenClaw Plugin Logging
The platform-tools OpenClaw plugin SHALL log discovery fetches, tool registrations, invocations (start + result), and errors using `api.log()`.

#### Scenario: Tool invocation logged
- **WHEN** an LLM invokes a platform tool through OpenClaw
- **THEN** INFO-level logs are emitted for invocation start and result (including duration and status)
- **AND** WARNING-level logs are emitted for failures with error details
