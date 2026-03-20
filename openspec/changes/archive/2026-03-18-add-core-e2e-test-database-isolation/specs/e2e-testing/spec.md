## ADDED Requirements

### Requirement: Isolated Core Database For E2E
The E2E workflow SHALL execute Core-facing tests against a dedicated Core database separate from the default local development database.

#### Scenario: E2E run uses dedicated Core DB
- **WHEN** E2E tests are started through the supported E2E entry command
- **THEN** the Core runtime targeted by E2E is configured with `DATABASE_URL` bound to `ai_community_platform_e2e`
- **AND** the default Core runtime database (`ai_community_platform`) is not used for E2E mutations

### Requirement: Migration-First E2E Execution
The E2E workflow SHALL apply Core Doctrine migrations to the dedicated E2E Core database before executing tests.

#### Scenario: Migrations are applied before E2E test run
- **WHEN** `make e2e` (or equivalent E2E wrapper) is executed
- **THEN** E2E DB provisioning and `doctrine:migrations:migrate --no-interaction` run before Codecept/Playwright execution

#### Scenario: E2E stops on migration failure
- **WHEN** E2E DB provisioning or migration fails
- **THEN** the E2E command exits non-zero
- **AND** no E2E test scenarios are started

## MODIFIED Requirements

### Requirement: Playwright E2E Infrastructure
The project SHALL include a Playwright test suite under `tests/e2e/` for end-to-end verification
of HTTP endpoints against the running Docker Compose stack.

#### Scenario: E2E suite executes via make target
- **WHEN** `make e2e` is executed with the Docker Compose stack running
- **THEN** Playwright discovers and executes all E2E tests and reports pass/fail results
- **AND** the suite targets the dedicated Core E2E runtime surface

#### Scenario: REST/console E2E suites target Core E2E runtime
- **WHEN** API-oriented Codecept scenarios are executed (for example `tests/openclaw/a2a_bridge_test.js`)
- **THEN** they use the same dedicated Core E2E base URL as browser E2E flows

#### Scenario: E2E tests are isolated from PHP test suites
- **WHEN** `codecept run` executes
- **THEN** Playwright tests are not included in the Codeception run
