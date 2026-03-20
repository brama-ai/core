## ADDED Requirements

### Requirement: Knowledge Entry Deduplication
The system SHALL detect near-duplicate knowledge entries before indexing and merge them instead of creating redundant records.

#### Scenario: Duplicate detected and merged
- **WHEN** the `ExtractKnowledge` workflow node produces an entry whose embedding similarity to an existing entry exceeds the configured threshold (default: 0.92 cosine)
- **THEN** the system merges the new body content into the existing entry and updates its `updated_at` timestamp, rather than creating a second document

#### Scenario: Below threshold entry indexed normally
- **WHEN** the similarity of a new entry to all existing entries is below the deduplication threshold
- **THEN** the entry is indexed as a new document

---

### Requirement: Knowledge Confidence Score
Each extracted knowledge entry SHALL carry a confidence score (0.0–1.0) reflecting the LLM's certainty about the extraction quality.

#### Scenario: Confidence score assigned during extraction
- **WHEN** the `ExtractKnowledge` node completes
- **THEN** the entry includes a `confidence` field between 0.0 and 1.0 based on LLM self-assessment

#### Scenario: Low-confidence entry flagged for review
- **WHEN** an extracted entry has `confidence < 0.6`
- **THEN** the entry is saved with `status = pending_review` and appears in the admin review queue

---

### Requirement: Real-Time Message Ingestion
The system SHALL subscribe to the `message.created` platform event and trigger extraction for new messages without requiring manual batch upload.

#### Scenario: New message triggers extraction
- **WHEN** `message.created` event is emitted by the platform
- **THEN** the knowledge agent evaluates the message as a single-item chunk and enqueues it for extraction if it passes the `AnalyzeMessages` filter

#### Scenario: High-volume chat throttled
- **WHEN** more than 30 messages per minute arrive via `message.created`
- **THEN** the agent buffers messages and processes them in time-window chunks rather than individually

---

### Requirement: Search Usage Feedback Loop
The system SHALL track which knowledge entries are accessed via search or direct navigation and use this signal to adjust search ranking.

#### Scenario: Access event recorded
- **WHEN** a user opens a knowledge entry from search results or tree navigation
- **THEN** the system records an access event with `entry_id`, `user_id` (if authenticated), and `timestamp`

#### Scenario: Frequently accessed entries boosted in search
- **WHEN** hybrid search is executed
- **THEN** entries with higher access counts in the last 30 days receive a configurable score boost (default: 1.2x multiplier)

---

### Requirement: Knowledge Export
The admin panel SHALL provide an export function allowing the full knowledge base to be downloaded in multiple formats.

#### Scenario: Admin exports as JSON
- **WHEN** admin clicks "Експортувати → JSON" in the admin knowledge page
- **THEN** the system streams a JSON array of all entries (no embeddings) as a downloadable file

#### Scenario: Admin exports as Markdown
- **WHEN** admin clicks "Експортувати → Markdown"
- **THEN** the system generates a ZIP archive containing one `.md` file per knowledge entry, organized by `tree_path`

---

### Requirement: Moderator Emoji Trigger
The system SHALL automatically trigger knowledge extraction for a message thread when a moderator reacts with the designated extraction emoji.

#### Scenario: Emoji reaction triggers extraction
- **WHEN** a moderator with `moderator` role reacts to a Telegram message with the configured extraction emoji (default: 📌)
- **THEN** the knowledge agent enqueues the message and its last 10 thread replies for extraction

#### Scenario: Non-moderator emoji ignored
- **WHEN** a regular member reacts with the extraction emoji
- **THEN** no extraction is triggered; no feedback is sent to the user
