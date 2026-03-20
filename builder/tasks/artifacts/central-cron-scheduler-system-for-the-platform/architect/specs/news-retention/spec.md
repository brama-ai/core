## ADDED Requirements

### Requirement: Scheduled Raw-News Cleanup
The system SHALL remove or expire temporary raw-news items on a configurable cron-driven schedule.

#### Scenario: Expired raw item removed by cleanup job
- **WHEN** the cleanup job runs and a raw item has passed its `expires_at` timestamp
- **THEN** the system removes the item or marks it expired according to the configured retention policy

#### Scenario: Non-expired raw item preserved
- **WHEN** the cleanup job runs and a raw item has not yet reached `expires_at`
- **THEN** the system leaves the item untouched

### Requirement: Cleanup Scope Isolation
The system SHALL apply scheduled cleanup only to temporary raw-news storage and not to curated or published news records.

#### Scenario: Curated ready item preserved
- **WHEN** the cleanup job runs
- **THEN** curated items in `draft`, `ready`, or `published` states remain unaffected

#### Scenario: Published item preserved
- **WHEN** the cleanup job runs
- **THEN** published news remains available for the public UI and publication history

### Requirement: Cleanup Observability
The system SHALL record cleanup job outcomes for operator review.

#### Scenario: Successful cleanup run logged
- **WHEN** a cleanup job completes successfully
- **THEN** the system stores run metadata including time and affected item count

#### Scenario: Cleanup failure logged
- **WHEN** a cleanup job fails
- **THEN** the system stores the failure status and error details for admin visibility
