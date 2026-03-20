## ADDED Requirements

### Requirement: Approval Queue Database Table
The system SHALL maintain an `approval_queue` table in the core Postgres database for storing items submitted by agents for human review. The table MUST use UUID primary keys, JSONB for payload and callback_payload, and support statuses: `pending`, `approved`, `rejected`, `expired`.

#### Scenario: Migration creates table with all required columns
- **WHEN** `doctrine:migrations:migrate` is run against the core database
- **THEN** the `approval_queue` table exists with columns: `id` (UUID PK), `agent_name` (VARCHAR 64), `action_type` (VARCHAR 64), `title` (VARCHAR 256), `payload` (JSONB), `preview_html` (TEXT nullable), `status` (VARCHAR 32 default 'pending'), `priority` (INTEGER default 5), `reviewer` (VARCHAR 128 nullable), `reviewer_comment` (TEXT nullable), `reviewed_at` (TIMESTAMPTZ nullable), `expires_at` (TIMESTAMPTZ nullable), `callback_skill` (VARCHAR 128 nullable), `callback_payload` (JSONB default '{}'), `created_at` (TIMESTAMPTZ default now())

#### Scenario: Indexes support primary query patterns
- **WHEN** the migration completes
- **THEN** composite indexes exist on `(status, priority DESC, created_at)` and `(agent_name, status)`

---

### Requirement: Submit for Approval A2A Skill
The system SHALL expose a `core.submit_for_approval` A2A skill that allows any agent to submit content for human review. The skill MUST accept `action_type`, `title`, and `payload` as required fields, and return a queue ID immediately without blocking the caller.

#### Scenario: Successful submission with required fields
- **WHEN** an authenticated A2A request is sent with `tool: "core.submit_for_approval"` and input containing `action_type: "publish_digest"`, `title: "News digest #42"`, and `payload: { "body": "content" }`
- **THEN** the system inserts a row into `approval_queue` with `status = 'pending'` and returns `{ queue_id: "<uuid>", status: "queued" }` with HTTP 200

#### Scenario: Submission with optional fields
- **WHEN** an A2A request includes optional fields `preview_html`, `priority: 8`, `expires_in_hours: 24`, `callback_skill: "news_maker.publish"`, and `callback_payload: { "item_id": "abc" }`
- **THEN** the system stores all fields, computes `expires_at` as `now() + 24 hours`, and returns `{ queue_id, status: "queued" }`

#### Scenario: Submission with missing required fields
- **WHEN** an A2A request is sent with `tool: "core.submit_for_approval"` but `title` is missing
- **THEN** the system returns HTTP 400 with an error describing the missing field

#### Scenario: Submission with invalid priority
- **WHEN** an A2A request includes `priority: 15` (outside 1-10 range)
- **THEN** the system returns HTTP 400 with a validation error

#### Scenario: Skill is listed in core agent card
- **WHEN** a client fetches `GET /.well-known/agent-card.json`
- **THEN** the response includes a skill with `id: "core.submit_for_approval"` in the skills array

---

### Requirement: Approval Decision with Callback
The system SHALL allow an admin to approve or reject a pending approval item, and if a `callback_skill` is configured, the system MUST invoke the callback skill via A2A with the decision details.

#### Scenario: Admin approves item without callback
- **WHEN** an admin submits a decision of `approved` for a pending approval item that has no `callback_skill`
- **THEN** the item's status is updated to `approved`, `reviewer` is set to the admin's username, `reviewed_at` is set to the current time, and no A2A callback is made

#### Scenario: Admin rejects item with comment
- **WHEN** an admin submits a decision of `rejected` with `comment: "Content quality too low"` for a pending item
- **THEN** the item's status is updated to `rejected`, `reviewer_comment` is stored, and `reviewed_at` is set

#### Scenario: Admin approves item with callback skill
- **WHEN** an admin approves an item that has `callback_skill: "news_maker.publish"` and `callback_payload: { "item_id": "abc" }`
- **THEN** the system updates the item status to `approved` and invokes `A2AClient.invoke("news_maker.publish", { decision: "approved", reviewer_comment: "...", original_payload: {...}, callback_payload: { "item_id": "abc" } })`

#### Scenario: Admin edits payload before approving
- **WHEN** an admin modifies the payload JSON and then approves the item
- **THEN** the edited payload is stored in the `payload` column and the edited version is sent as `original_payload` in the callback

#### Scenario: Decision on non-pending item is rejected
- **WHEN** an admin attempts to approve an item with `status = 'approved'` or `status = 'expired'`
- **THEN** the system returns an error indicating the item is not in a reviewable state

---

### Requirement: Approval Queue Admin UI
The system SHALL provide an admin page at `/admin/approvals` for reviewing and managing approval queue items, accessible only to authenticated admin users.

#### Scenario: Admin views pending approvals
- **WHEN** an authenticated admin visits `GET /admin/approvals`
- **THEN** the page displays a paginated list of approval items with default filter `status = pending`, sorted by `priority DESC, created_at ASC`

#### Scenario: Admin filters by agent and action type
- **WHEN** an admin applies filters `agent=news-maker-agent` and `action_type=publish_digest`
- **THEN** only matching items are displayed

#### Scenario: Admin views item detail with HTML preview
- **WHEN** an approval item has a non-null `preview_html` field
- **THEN** the detail view renders the HTML preview in a sandboxed container

#### Scenario: Admin views item detail with JSON payload
- **WHEN** an approval item has no `preview_html`
- **THEN** the detail view displays the `payload` as formatted JSON

#### Scenario: Auto-refresh updates pending list
- **WHEN** the admin is viewing the approvals page with `status = pending` filter
- **THEN** the page auto-refreshes every 30 seconds to show new items

#### Scenario: Unauthenticated access redirects to login
- **WHEN** an unauthenticated user visits `GET /admin/approvals`
- **THEN** the response redirects to `GET /admin/login`

---

### Requirement: Approval Queue Navigation Badge
The system SHALL display a badge with the count of pending approval items next to the "Approval Queue" navigation item in the admin sidebar.

#### Scenario: Badge shows pending count
- **WHEN** there are 5 items with `status = 'pending'` in the approval queue
- **THEN** the admin sidebar shows a badge with "5" next to the approval queue nav item

#### Scenario: Badge is hidden when no pending items
- **WHEN** there are 0 pending items in the approval queue
- **THEN** no badge is displayed next to the approval queue nav item

---

### Requirement: Approval Item Expiration
The system SHALL automatically expire approval items whose `expires_at` timestamp has passed, changing their status from `pending` to `expired`.

#### Scenario: Expired items are marked via console command
- **WHEN** the `app:expire-approvals` console command runs
- **THEN** all items with `status = 'pending'` and `expires_at < now()` are updated to `status = 'expired'` and the command outputs the count of expired items

#### Scenario: Items without expires_at never auto-expire
- **WHEN** an approval item has `expires_at = NULL`
- **THEN** the item remains in `pending` status indefinitely until manually reviewed

#### Scenario: Already reviewed items are not affected by expiration
- **WHEN** the expiration command runs and an item has `status = 'approved'` with a past `expires_at`
- **THEN** the item's status is not changed

---

### Requirement: Pending Items Soft Limit
The system SHALL log a warning when the number of pending approval items exceeds 1000.

#### Scenario: Warning logged on insert above threshold
- **WHEN** a new approval item is inserted and the total pending count exceeds 1000
- **THEN** a warning-level log message is emitted indicating the pending queue has exceeded the soft limit

---

### Requirement: News-Maker Approval Integration
The news-maker agent SHALL support an optional `require_approval` setting that, when enabled, submits curated news items to the core approval queue instead of making them directly available for publication.

#### Scenario: Rewriter submits for approval when enabled
- **WHEN** `AgentSettings.require_approval` is `True` and the rewriter produces a `CuratedNewsItem` with `status = ready`
- **THEN** the agent calls `core.submit_for_approval` with `action_type: "publish_digest"`, the item's title/summary/body/source_url as payload, and a rendered preview_html

#### Scenario: Rewriter skips approval when disabled
- **WHEN** `AgentSettings.require_approval` is `False` and the rewriter produces a `CuratedNewsItem` with `status = ready`
- **THEN** no approval submission is made and the item remains with `status = ready` for direct publication

#### Scenario: Approval setting defaults to disabled
- **WHEN** a fresh news-maker agent starts with default settings
- **THEN** `AgentSettings.require_approval` is `False`
