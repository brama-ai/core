# grouped-log-view Specification

## Purpose
Allow operators to view logs grouped by `trace_id` or `request_id` on the admin
logs page, with collapsible groups that expand to show all related log entries
sorted chronologically.

## ADDED Requirements

### Requirement: Grouping Mode Selector on Logs Page
The admin logs page SHALL provide a grouping selector in the filter form with
options: none (flat list), group by `trace_id`, group by `request_id`.

#### Scenario: Operator selects grouping by trace_id
- **GIVEN** the admin logs page is open
- **WHEN** the operator selects "За trace_id" from the Grouping dropdown
- **AND** clicks Search
- **THEN** the URL SHALL include `group_by=trace_id` query parameter
- **AND** the results SHALL be rendered as grouped collapsible cards instead of
  a flat table

#### Scenario: Default view has no grouping
- **GIVEN** the admin logs page is opened without a `group_by` parameter
- **THEN** logs SHALL be displayed as the existing flat table (no grouping)

### Requirement: Grouped Results Rendered as Collapsible Cards
When grouping is active, each group SHALL be rendered as a collapsed
`<details>` element that expands to show the group's log entries.

#### Scenario: Group card displays summary information
- **GIVEN** logs are grouped by `trace_id`
- **WHEN** the results render
- **THEN** each group card summary SHALL show:
  - The group key value (first 8 characters with full value in title attribute)
  - Total number of logs in the group
  - List of distinct `app_name` values as badges
  - Time range (earliest to latest `@timestamp`)

#### Scenario: Expanding a group shows sorted log entries
- **GIVEN** a collapsed group card is displayed
- **WHEN** the operator clicks on the group summary
- **THEN** the card SHALL expand to show individual log entries
- **AND** entries SHALL be sorted by `@timestamp` ascending (oldest first)
- **AND** each entry SHALL display: timestamp, level badge, app name, message,
  and optional exception details (same format as the flat log view)

### Requirement: Grouped Query Uses Server-Side Aggregation
The controller SHALL use OpenSearch composite aggregation to group results on the
server side, returning at most 20 groups per page with up to 50 logs each.

#### Scenario: Aggregation query returns grouped results
- **GIVEN** a search with `group_by=trace_id` and optional filters (level, app, date)
- **WHEN** the controller queries OpenSearch
- **THEN** it SHALL use a `composite` aggregation on the `group_by` field
- **AND** each bucket SHALL include sub-aggregations for: earliest timestamp,
  latest timestamp, distinct app names, and top 50 hits sorted by time ascending

#### Scenario: Pagination across grouped results
- **GIVEN** more than 20 groups match the query
- **WHEN** the first page of groups is displayed
- **THEN** a "Далі" (Next) button SHALL be shown
- **AND** clicking it SHALL load the next 20 groups using the composite
  aggregation `after_key`

### Requirement: Group Card Links to Full Trace View
Each group card for `trace_id` grouping SHALL include a link to the existing
trace detail page (`/admin/logs/trace/{traceId}`).

#### Scenario: Operator navigates from group to trace page
- **GIVEN** logs are grouped by `trace_id`
- **WHEN** the operator clicks the trace link on a group card
- **THEN** the browser SHALL navigate to `/admin/logs/trace/{traceId}`
- **AND** the full waterfall + sequence diagram SHALL be displayed
