# Capability: Dev Reporter

## ADDED Requirements

### Requirement: E2E Test Coverage

The dev-reporter-agent MUST have dedicated E2E tests verifying its health endpoint, manifest endpoint, and admin panel functionality against the running E2E stack.

#### Scenario: Health endpoint E2E verification via Traefik

- **WHEN** the E2E test sends a GET request to `DEV_REPORTER_URL/health` with edge auth cookie
- **THEN** the response status is 200
- **THEN** the response body contains `{"status": "ok"}`

#### Scenario: Manifest endpoint E2E verification via Traefik

- **WHEN** the E2E test sends a GET request to `DEV_REPORTER_URL/api/v1/manifest` with edge auth cookie
- **THEN** the response status is 200
- **THEN** the response body contains agent name `dev-reporter-agent`
- **THEN** the skills array includes `devreporter.ingest`, `devreporter.status`, and `devreporter.notify`

#### Scenario: Admin reports list page loads

- **WHEN** an authenticated admin navigates to the dev-reporter admin page
- **THEN** the page displays a table of pipeline runs
- **THEN** the table includes columns for date, task, branch, status, and duration

#### Scenario: Admin reports list supports status filter

- **WHEN** an authenticated admin uses the status filter on the dev-reporter admin page
- **THEN** the page updates to show only runs matching the selected status

### Requirement: Quality Gate Compliance

The dev-reporter-agent MUST pass all platform quality gates before being considered production-ready.

#### Scenario: PHPStan analysis passes

- **WHEN** `make dev-reporter-analyse` is executed
- **THEN** PHPStan reports zero errors at level 8

#### Scenario: Code style check passes

- **WHEN** `make dev-reporter-cs-check` is executed
- **THEN** PHP CS Fixer reports zero violations

#### Scenario: All unit and functional tests pass

- **WHEN** `make dev-reporter-test` is executed
- **THEN** all Codeception test suites pass with zero failures

#### Scenario: Agent convention compliance passes

- **WHEN** `make conventions-test` is executed
- **THEN** the dev-reporter-agent passes all convention verification checks
