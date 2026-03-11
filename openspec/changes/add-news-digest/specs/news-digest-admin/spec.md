## ADDED Requirements

### Requirement: Digest System Prompt Configuration
The system SHALL provide admin controls for the digest generator's system prompt and guardrail.

#### Scenario: Admin updates digest prompt
- **WHEN** an admin saves a new system prompt for the digest generator
- **THEN** subsequent digest generation runs use the updated prompt

#### Scenario: Digest guardrail is always applied
- **WHEN** a digest generation run executes
- **THEN** the configured guardrail is appended to the system prompt

### Requirement: Digest Model Configuration
The system SHALL allow admins to select the LLM model used for digest generation.

#### Scenario: Admin changes digest model
- **WHEN** an admin updates the digest model setting
- **THEN** subsequent digest runs use the new model

### Requirement: Digest Source Status Configuration
The system SHALL allow admins to configure which curated item statuses are eligible for digest inclusion.

#### Scenario: Default source statuses
- **WHEN** agent settings are initialized for the first time
- **THEN** the default digest source statuses are `ready` and `moderated`

#### Scenario: Admin restricts to moderated only
- **WHEN** an admin configures digest source statuses to only `moderated`
- **THEN** only items that have been manually moderated are included in digest generation

### Requirement: Digest Schedule Configuration
The system SHALL allow admins to configure the cron schedule for automatic digest generation.

#### Scenario: Digest cron updated
- **WHEN** an admin changes the digest cron schedule
- **THEN** future automatic digest runs follow the new schedule

### Requirement: Manual Digest Trigger
The system SHALL provide a button in the admin UI to trigger digest generation immediately.

#### Scenario: Admin triggers digest
- **WHEN** an admin clicks the digest trigger button
- **THEN** the digest service runs immediately in the background

### Requirement: Embedding Model Configuration
The system SHALL allow admins to configure the embedding model used for deduplication.

#### Scenario: Admin changes embedding model
- **WHEN** an admin updates the embedding model setting
- **THEN** subsequent embedding computations use the new model
