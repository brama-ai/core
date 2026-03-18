## MODIFIED Requirements

### Requirement: Manual Digest Trigger
The system SHALL provide a button in the admin UI to trigger digest generation immediately, and this action SHALL initiate background digest processing without blocking the admin request.

#### Scenario: Admin triggers digest
- **WHEN** an admin clicks the digest trigger button
- **THEN** the digest service run is started in the background immediately
- **AND** the admin request returns without waiting for full digest generation

## ADDED Requirements

### Requirement: Manual Digest Trigger Outcome Visibility
The system SHALL record whether a manual digest trigger was accepted, skipped due to an active run, or completed with channel delivery warning.

#### Scenario: Trigger skipped because run is already active
- **WHEN** an admin triggers digest while another digest run is active
- **THEN** the system records a skipped outcome for the second trigger attempt

#### Scenario: Delivery warning after successful generation
- **WHEN** digest generation succeeds but channel delivery via Core/OpenClaw fails
- **THEN** the system records a completion outcome with delivery warning details
