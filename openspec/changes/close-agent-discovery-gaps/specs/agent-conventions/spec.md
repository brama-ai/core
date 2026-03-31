## ADDED Requirements

### Requirement: Agent Manifest JSON Schema
The platform SHALL maintain a formal JSON Schema file at `config/agent-manifest-schema.json`
that defines the structure and validation rules for agent manifest responses. This schema
serves as the canonical contract for the `GET /api/v1/manifest` endpoint.

The schema SHALL require `name` (non-empty kebab-case string) and `version` (semver `X.Y.Z`)
as mandatory fields. Optional fields SHALL include `url`, `a2a_endpoint` (deprecated),
`skills`, `description`, `permissions`, `commands`, `events`, `capabilities`, `storage`,
`health_url`, `admin_url`, `config_schema`, and `scheduled_jobs`.

A mirrored copy SHALL exist at `tests/agent-conventions/support/manifest-schema.json` for
test-side validation without cross-referencing core config paths.

#### Scenario: Schema defines required fields
- **WHEN** a developer inspects `config/agent-manifest-schema.json`
- **THEN** the schema requires `name` (non-empty string, kebab-case pattern) and `version` (semver pattern `X.Y.Z`) as mandatory fields

#### Scenario: Schema defines optional fields with formats
- **WHEN** a manifest includes optional fields like `url`, `a2a_endpoint`, `skills`, `description`, `permissions`, `commands`, or `events`
- **THEN** the schema validates `url` and `a2a_endpoint` as URI format and `skills` as an array of strings or structured AgentSkill objects

#### Scenario: Schema validates against existing agent manifests
- **WHEN** the schema is applied to manifests from knowledge-agent, hello-agent, or news-maker-agent
- **THEN** all existing agent manifests pass validation without errors

#### Scenario: Test-side schema mirrors core schema
- **WHEN** the convention test suite validates a manifest
- **THEN** it uses `tests/agent-conventions/support/manifest-schema.json` which mirrors the core schema

### Requirement: Agent Convention Verification Model
The `AgentConventionVerifier` service SHALL validate agent manifests against platform
conventions and return a `ConventionResult` with one of three statuses: `healthy`, `degraded`,
or `error`. The status SHALL be determined by the severity of detected violations.

The verifier SHALL enforce the following rules:
- Missing or empty `name` field: `error` status
- Missing or empty `version` field: `error` status
- Null or unparseable manifest input: `error` status
- Non-semver `version` string (not matching `X.Y.Z`): `degraded` status with warning
- Non-empty `skills` array without `url` or `a2a_endpoint`: `degraded` status with warning
- Valid manifest with all required fields and no violations: `healthy` status with empty violations list

#### Scenario: Missing name returns error
- **WHEN** the verifier receives a manifest without a `name` field
- **THEN** the result status is `error`
- **AND** the violations list contains a message about the missing `name` field

#### Scenario: Missing version returns error
- **WHEN** the verifier receives a manifest without a `version` field
- **THEN** the result status is `error`
- **AND** the violations list contains a message about the missing `version` field

#### Scenario: Null input returns error
- **WHEN** the verifier receives `null` as the manifest input
- **THEN** the result status is `error`
- **AND** the violations list contains a message about unparseable input

#### Scenario: Valid manifest returns healthy
- **WHEN** the verifier receives a complete manifest with valid `name`, `version`, `skills`, and `capabilities`
- **THEN** the result status is `healthy`
- **AND** the violations list is empty

#### Scenario: Non-semver version returns degraded
- **WHEN** the verifier receives a manifest with `version` set to a non-semver string (e.g. `latest` or `1.0`)
- **THEN** the result status is `degraded`
- **AND** the violations list contains a warning about the version format

#### Scenario: Skills without endpoint returns degraded
- **WHEN** the verifier receives a manifest with a non-empty `skills` array but no `url` or `a2a_endpoint` field
- **THEN** the result status is `degraded`
- **AND** the violations list contains a warning about the missing endpoint

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
functionality for URL-based agent provisioning. The button SHALL open a Bootstrap modal
with a bilingual development notice and instructions for the current manual workflow.

#### Scenario: Admin sees Add by URL button
- **WHEN** an admin visits the agents management page at `/admin/agents`
- **THEN** an "Add by URL" button is visible alongside the "Run Discovery" button

#### Scenario: Add by URL shows development notice
- **WHEN** an admin clicks the "Add by URL" button
- **THEN** a modal appears with a message explaining the feature is in development
- **AND** the modal provides instructions to add agents via `compose.yaml` with the `ai.platform.agent=true` Docker label
- **AND** the modal does not trigger any backend action
