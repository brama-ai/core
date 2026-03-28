# Capability: E2E Testing

## MODIFIED Requirements

### Requirement: Full-Stack E2E Isolation
The E2E workflow SHALL provide isolated duplicates of ALL application services (Core, agents, OpenClaw gateway) that connect to test data stores.

#### Scenario: Each agent has an E2E duplicate container
- **WHEN** `make e2e-prepare` is executed
- **THEN** E2E containers start for core-e2e, knowledge-agent-e2e, knowledge-worker-e2e, news-maker-agent-e2e, hello-agent-e2e, dev-reporter-agent-e2e, and openclaw-gateway-e2e
- **AND** each E2E container uses test databases, test Redis DB numbers, test OpenSearch indices, and test RabbitMQ vhost

#### Scenario: E2E agents are accessible on dedicated ports
- **WHEN** E2E containers are running
- **THEN** core-e2e is reachable at port 18080
- **AND** knowledge-agent-e2e is reachable at port 18083
- **AND** news-maker-agent-e2e is reachable at port 18084
- **AND** hello-agent-e2e is reachable at port 18085
- **AND** dev-reporter-agent-e2e is reachable at port 18087
- **AND** openclaw-gateway-e2e is reachable at port 28789

#### Scenario: A2A messages stay within E2E graph
- **WHEN** core-e2e sends an A2A message to an agent
- **THEN** the message is routed via openclaw-gateway-e2e to an agent-e2e container
- **AND** the agent-e2e container reads from and writes to test data stores

## ADDED Requirements

### Requirement: Dev Reporter Agent E2E Coverage
The E2E suite SHALL include dedicated tests for the dev-reporter-agent covering health, manifest, and admin panel flows.

#### Scenario: Dev reporter health and manifest verified in E2E
- **WHEN** the E2E suite runs with the dev-reporter-agent-e2e container available
- **THEN** the health endpoint at `DEV_REPORTER_URL/health` returns HTTP 200 with `{"status": "ok"}`
- **AND** the manifest endpoint at `DEV_REPORTER_URL/api/v1/manifest` returns a valid Agent Card with 3 skills

#### Scenario: Dev reporter admin panel tested in E2E
- **WHEN** the E2E suite runs admin tests for dev-reporter
- **THEN** the reports list page loads with a pipeline runs table
- **AND** the status filter controls are functional
- **AND** the test file is `tests/e2e/tests/admin/dev_reporter_admin_test.js`
- **AND** the page object is `tests/e2e/support/pages/DevReporterPage.js`

#### Scenario: CUJ matrix includes dev-reporter entries
- **WHEN** the CUJ matrix is reviewed
- **THEN** it includes entries for dev-reporter admin reports list and health/manifest verification
