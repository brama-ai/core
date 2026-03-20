## ADDED Requirements

### Requirement: Health Check Endpoint

The platform core SHALL expose `GET /health` returning a JSON status response. The endpoint
MUST be publicly accessible with no authentication required and MUST be reachable through
the Traefik routing layer at `http://localhost/health`.

#### Scenario: Returns 200 with JSON status body

- **WHEN** `GET /health` is called
- **THEN** the response status is `200 OK`, `Content-Type` is `application/json`, and the body
  contains `{"status":"ok","service":"core-platform"}`

#### Scenario: No credentials required

- **WHEN** `GET /health` is called without any authorization header or token
- **THEN** the response is `200 OK` (health endpoint is unauthenticated)

#### Scenario: Endpoint is reachable through Traefik

- **WHEN** `GET http://localhost/health` is called against the running Docker Compose stack
- **THEN** Traefik forwards the request to the `core` container and the response is `200 OK`
- **AND** no direct container port bypass is required

#### Scenario: Endpoint is covered by functional tests

- **WHEN** the Codeception functional suite runs
- **THEN** at least one test verifies `GET /health` returns `200 OK` with the expected JSON body
