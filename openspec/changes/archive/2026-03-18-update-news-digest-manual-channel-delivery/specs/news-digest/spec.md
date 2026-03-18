## MODIFIED Requirements

### Requirement: Digest Trigger
The system SHALL support both scheduled (cron) and manual digest generation, and manual runs SHALL execute asynchronously with single-flight protection to avoid duplicate concurrent runs.

#### Scenario: Scheduled digest runs on cron
- **WHEN** the configured digest cron schedule fires
- **THEN** the digest service collects eligible items and generates a digest

#### Scenario: Admin triggers digest manually
- **WHEN** an admin clicks the manual digest trigger in the admin UI
- **THEN** the digest service run is accepted immediately in the background

#### Scenario: Duplicate manual trigger while running
- **WHEN** a manual digest run is already in progress and an admin clicks trigger again
- **THEN** the second trigger is skipped and no second run starts

## ADDED Requirements

### Requirement: Manual Digest Channel Delivery
The system SHALL publish a successfully generated manual digest to the community channel via the platform routing path (`Core A2A` invoke endpoint with `openclaw.send_message`).

#### Scenario: Manual digest generated and delivered
- **WHEN** a manual digest run creates a digest successfully
- **THEN** the system sends exactly one outbound publish request through `POST /api/v1/a2a/send-message`
- **AND** the request targets `tool = openclaw.send_message`

#### Scenario: No eligible items on manual run
- **WHEN** a manual digest run finds no eligible curated items
- **THEN** no digest is created
- **AND** no outbound channel publish request is sent

#### Scenario: Channel delivery fails after digest creation
- **WHEN** digest persistence succeeds but outbound publish returns transport/business failure
- **THEN** the digest record and item status transitions remain committed
- **AND** the failure is logged with trace/request context for operator troubleshooting
