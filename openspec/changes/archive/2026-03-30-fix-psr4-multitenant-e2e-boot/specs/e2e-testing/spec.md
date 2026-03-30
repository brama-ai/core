## MODIFIED Requirements
### Requirement: Playwright E2E Infrastructure
The project SHALL include a Playwright test suite under `tests/e2e/` for end-to-end verification
of HTTP endpoints against a fully isolated E2E Docker Compose stack.

#### Scenario: E2E suite executes via make target
- **WHEN** `make e2e` is executed with the Docker Compose stack running
- **THEN** Playwright discovers and executes all E2E tests and reports pass/fail results
- **AND** the suite targets the E2E runtime surfaces for all services (core-e2e, agent-e2e containers, openclaw-e2e)

#### Scenario: REST/console E2E suites target E2E runtime
- **WHEN** API-oriented Codecept scenarios are executed (for example `tests/openclaw/a2a_bridge_test.js`)
- **THEN** they use the E2E OpenClaw gateway and E2E Core base URL

#### Scenario: E2E tests are isolated from PHP test suites
- **WHEN** `codecept run` executes
- **THEN** Playwright tests are not included in the Codeception run

#### Scenario: E2E agent URLs are configurable via environment variables
- **WHEN** E2E tests reference agent endpoints
- **THEN** they use `HELLO_URL`, `KNOWLEDGE_URL`, `NEWS_URL` environment variables with localhost defaults
- **AND** they do not use Docker-internal hostnames that are unreachable from the host runner

#### Scenario: Smoke suite includes only services available in e2e-prepare
- **WHEN** `make e2e-smoke` is executed (grep `@smoke`)
- **THEN** only tests tagged `@smoke` are included
- **AND** tests requiring services not started by `e2e-prepare` (full Traefik agent routing) are tagged with non-smoke tags (`@traefik`)

## ADDED Requirements
### Requirement: Tenant-Aware E2E Agent Registration

The E2E preparation pipeline SHALL register and enable agents within the default tenant context.

#### Scenario: E2E agents are registered with tenant_id

- **WHEN** `make e2e-register-agents` executes
- **THEN** each agent registration API call resolves the default tenant automatically
- **AND** subsequent SQL statements for enabling agents and creating scheduled jobs include the correct `tenant_id`
- **AND** `ON CONFLICT` clauses match the composite unique constraints that include `tenant_id`
