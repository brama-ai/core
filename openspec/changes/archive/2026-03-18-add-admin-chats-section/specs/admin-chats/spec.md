## ADDED Requirements

### Requirement: Admin Chat List
The admin panel SHALL provide a paginated list of A2A conversations at `/admin/chats`.

Each row SHALL display: trace ID, agent name, skill name, status, duration, timestamp, and message count.

The list SHALL be sorted by `created_at DESC` (most recent first).

#### Scenario: Admin opens chats page
- **WHEN** an authenticated admin navigates to `/admin/chats`
- **THEN** the page SHALL display a table of conversations from `a2a_message_audit` grouped by `trace_id`
- **AND** each row SHALL show trace_id, agent, skill, status badge, duration_ms, created_at, and message count

#### Scenario: Empty state
- **WHEN** no audit records exist
- **THEN** the page SHALL display an empty state message

### Requirement: Chat List Filtering
The chat list SHALL support filtering by agent name, skill name, status, and date range.

#### Scenario: Filter by agent
- **WHEN** admin selects an agent from the filter dropdown
- **THEN** the list SHALL show only conversations involving that agent

#### Scenario: Filter by status
- **WHEN** admin selects a status (completed / failed)
- **THEN** the list SHALL show only conversations with that status

#### Scenario: Filter by date range
- **WHEN** admin sets a date range
- **THEN** the list SHALL show only conversations within that range

### Requirement: Chat List Navigation to Trace View
Each trace ID in the chat list SHALL be a clickable link to the existing trace visualization page.

#### Scenario: Admin clicks trace ID
- **WHEN** admin clicks the trace ID link in a chat row
- **THEN** the browser SHALL navigate to `/admin/logs/trace/{traceId}`

### Requirement: Chat Detail View
The admin panel SHALL provide a conversation detail view at `/admin/chats/{traceId}`.

The detail view SHALL display the message exchange: input payload, agent response, participating agents, status, and timing.

#### Scenario: Admin opens chat detail
- **WHEN** admin clicks a chat row or navigates to `/admin/chats/{traceId}`
- **THEN** the page SHALL display the conversation flow with input and output payloads
- **AND** SHALL show agent name, skill, status, duration, and timestamps

#### Scenario: Multiple invocations in one trace
- **WHEN** a trace contains multiple A2A invocations
- **THEN** the detail view SHALL display all invocations in chronological order

#### Scenario: OpenSearch unavailable
- **WHEN** OpenSearch is unreachable or returns no data for the trace
- **THEN** the detail view SHALL display audit-level data (agent, skill, status, duration) without payloads
- **AND** SHALL show a notice that payload details are temporarily unavailable

### Requirement: Chat Detail Trace Link
The chat detail page SHALL provide a link to the full trace visualization.

#### Scenario: Admin navigates to trace from detail
- **WHEN** admin clicks the "View Trace" button on the chat detail page
- **THEN** the browser SHALL navigate to `/admin/logs/trace/{traceId}`

### Requirement: Chat List Pagination
The chat list SHALL support cursor-based pagination.

#### Scenario: More conversations than page size
- **WHEN** there are more conversations than the page size
- **THEN** the page SHALL display pagination controls (next/previous)
- **AND** pagination SHALL use cursor-based navigation on `created_at`
