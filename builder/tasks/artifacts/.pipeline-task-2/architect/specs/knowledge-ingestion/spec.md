## ADDED Requirements

### Requirement: A2A Message Metadata Store Skill
The system SHALL provide an A2A skill `knowledge.store_message` that persists a source message with rich metadata in the knowledge agent database.

#### Scenario: Store message with full metadata
- **WHEN** core invokes `knowledge.store_message` with message fields including author, channel, message id, and timestamp
- **THEN** the knowledge agent stores a row in `knowledge_source_messages`
- **AND** returns `status: completed` with the stored record id

#### Scenario: Duplicate delivery is idempotent
- **WHEN** the same source message (`source_platform`, `chat_id`, `message_id`) is delivered again
- **THEN** the knowledge agent updates the existing row instead of inserting a duplicate
- **AND** returns the existing row id

#### Scenario: Raw payload is preserved
- **WHEN** `knowledge.store_message` receives a message payload with nested metadata
- **THEN** the full original payload is stored in `raw_payload` JSONB
- **AND** normalized metadata is stored in dedicated columns for filtering and analytics
