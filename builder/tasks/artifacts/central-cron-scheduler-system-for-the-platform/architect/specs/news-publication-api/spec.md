## ADDED Requirements

### Requirement: Ready News Feed API
The system SHALL expose an internal API endpoint that returns curated news items that are ready for publication.

#### Scenario: Ready items returned
- **WHEN** a client requests the ready-news endpoint
- **THEN** the response includes only curated items where `status = ready`

#### Scenario: Published items excluded from ready feed
- **WHEN** a curated item has already been marked `published`
- **THEN** that item is not returned by the ready-news endpoint

### Requirement: Publication Status Update API
The system SHALL expose an API method to mark a curated news item as published.

#### Scenario: Ready item marked published
- **WHEN** a client submits a publish request for a `ready` curated item
- **THEN** the system updates the item status to `published` and records `published_at`

#### Scenario: Already published item is handled idempotently
- **WHEN** a client submits a publish request for an already published item
- **THEN** the system returns a successful idempotent response without creating a duplicate state change

### Requirement: Invalid Publication Transition Protection
The system SHALL reject invalid attempts to publish items that are not in a publication-ready state.

#### Scenario: Draft item cannot be published
- **WHEN** a client attempts to publish an item with `status = draft`
- **THEN** the system rejects the request with a validation error

#### Scenario: Rejected item cannot be published
- **WHEN** a client attempts to publish an item with `status = rejected`
- **THEN** the system rejects the request with a validation error
