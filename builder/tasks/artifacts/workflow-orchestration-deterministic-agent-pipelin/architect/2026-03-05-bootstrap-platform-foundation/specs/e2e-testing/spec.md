## ADDED Requirements

### Requirement: Playwright E2E Infrastructure

The project SHALL include a Playwright test suite under `tests/e2e/` for end-to-end verification
of HTTP endpoints against the running Docker Compose stack.

#### Scenario: E2E suite executes via make target

- **WHEN** `make e2e` is executed with the Docker Compose stack running
- **THEN** Playwright discovers and executes all E2E tests and reports pass/fail results

#### Scenario: E2E tests are isolated from PHP test suites

- **WHEN** `codecept run` executes
- **THEN** Playwright tests are not included in the Codeception run

### Requirement: Health Endpoint E2E Test

The Playwright suite SHALL include an E2E test verifying `GET /health` end-to-end against the
running `core` container.

#### Scenario: Health endpoint passes E2E verification

- **WHEN** the Playwright E2E test for `/health` runs against `http://localhost/health`
- **THEN** the HTTP response status is `200` and the response body contains `"status":"ok"`

### Requirement: Traefik Liveness Verification

The Playwright suite SHALL include an E2E test verifying that Traefik is running and its
REST API is reachable on the local development port.

#### Scenario: Traefik API responds

- **WHEN** `GET http://localhost:8080/api/http/services` is called
- **THEN** the HTTP response status is `200` and the body is valid JSON

### Requirement: Core Service Registration in Traefik

The Playwright suite SHALL include an E2E test verifying that the `core` container is
registered as an active service in Traefik.

#### Scenario: core@docker is present in Traefik services

- **WHEN** `GET http://localhost:8080/api/http/services` is called
- **THEN** the response body contains a service entry named `core@docker`

#### Scenario: core router is active

- **WHEN** `GET http://localhost:8080/api/http/routers` is called
- **THEN** the response body contains a router entry for `core` with status `enabled`
