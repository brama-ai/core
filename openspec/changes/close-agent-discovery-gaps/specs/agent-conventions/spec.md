## ADDED Requirements

### Requirement: Agent Manifest JSON Schema
The platform SHALL maintain a formal JSON Schema file at `config/agent-manifest-schema.json`
that defines the structure and validation rules for agent manifest responses. This schema
serves as the canonical contract for the `GET /api/v1/manifest` endpoint.

#### Scenario: Schema defines required fields
- **WHEN** a developer inspects `config/agent-manifest-schema.json`
- **THEN** the schema requires `name` (non-empty string) and `version` (semver pattern `X.Y.Z`) as mandatory fields

#### Scenario: Schema defines optional fields with formats
- **WHEN** a manifest includes optional fields like `a2a_endpoint`, `skills`, `description`, `permissions`, `commands`, or `events`
- **THEN** the schema validates `a2a_endpoint` as a valid URL format and `skills` as an array

#### Scenario: Schema conditional requirement for a2a_endpoint
- **WHEN** a manifest declares a non-empty `skills` array
- **THEN** the schema requires `a2a_endpoint` to be present and non-empty

#### Scenario: Test-side schema mirrors core schema
- **WHEN** the convention test suite validates a manifest
- **THEN** it uses `tests/agent-conventions/support/manifest-schema.json` which mirrors the core schema

### Requirement: Scheduled Agent Discovery
The platform SHALL automatically run agent discovery at regular intervals without manual
intervention. The `agent:discovery` command SHALL be registered as a scheduled task with
a 60-second polling interval using the platform's existing scheduler infrastructure.

#### Scenario: Discovery runs automatically every 60 seconds
- **WHEN** the platform scheduler is active
- **THEN** `agent:discovery` executes every 60 seconds, discovering new agents and updating existing agent status

#### Scenario: Manual discovery remains available
- **WHEN** an admin clicks "Run Discovery" in the admin panel
- **THEN** discovery runs immediately regardless of the scheduled interval

### Requirement: Add-by-URL Admin Placeholder
The admin agents page SHALL display an "Add by URL" button that communicates upcoming
functionality for URL-based agent provisioning.

#### Scenario: Admin sees Add by URL button
- **WHEN** an admin visits the agents management page
- **THEN** an "Додати за URL" / "Add by URL" button is visible alongside the "Run Discovery" button

#### Scenario: Add by URL shows development notice
- **WHEN** an admin clicks the "Add by URL" button
- **THEN** a modal appears explaining the feature is in development and providing instructions to add agents via `compose.yaml` with the `ai.platform.agent=true` label
