## ADDED Requirements

### Requirement: Digest Generation from Eligible Items
The system SHALL generate a compiled news digest from curated items matching the configured source statuses.

#### Scenario: Digest generated from multiple items
- **WHEN** the digest service runs and finds curated items matching configured statuses
- **THEN** the system sends all eligible items to the LLM and produces a single digest document

#### Scenario: No eligible items available
- **WHEN** the digest service runs and no curated items match the configured statuses
- **THEN** no digest is created and the run is logged as empty

### Requirement: Adaptive Digest Length
The system SHALL instruct the LLM to adjust per-item detail inversely proportional to the total number of items in the digest.

#### Scenario: Single item gets full coverage
- **WHEN** the digest includes exactly 1 item
- **THEN** the LLM is prompted to provide detailed coverage (~500 words)

#### Scenario: Multiple items get proportional coverage
- **WHEN** the digest includes 4–7 items
- **THEN** the LLM is prompted for concise summaries (~100 words each)

#### Scenario: Many items get brief coverage
- **WHEN** the digest includes 8 or more items
- **THEN** the LLM is prompted for brief bullet-style entries (~50 words each)

### Requirement: Digest Persisted as Record
The system SHALL persist each generated digest with metadata about included items.

#### Scenario: Digest record created
- **WHEN** the LLM returns a valid digest
- **THEN** a `Digest` record is created with title, body, language, item count, and creation timestamp

#### Scenario: Digest links to source items
- **WHEN** a digest is created
- **THEN** a many-to-many relationship records which curated items were included in the digest

### Requirement: Published Status on Digest Generation
The system SHALL mark all curated items included in a digest as `published` after successful digest creation.

#### Scenario: Items transition to published
- **WHEN** a digest is successfully generated and saved
- **THEN** all included curated items have `status = published` and `published_at` set to current timestamp

#### Scenario: Failed digest does not change item statuses
- **WHEN** digest generation fails (LLM error, validation failure)
- **THEN** no curated item statuses are changed

### Requirement: Digest Trigger
The system SHALL support both scheduled (cron) and manual digest generation.

#### Scenario: Scheduled digest runs on cron
- **WHEN** the configured digest cron schedule fires
- **THEN** the digest service collects eligible items and generates a digest

#### Scenario: Admin triggers digest manually
- **WHEN** an admin clicks the manual digest trigger in the admin UI
- **THEN** the digest service runs immediately
