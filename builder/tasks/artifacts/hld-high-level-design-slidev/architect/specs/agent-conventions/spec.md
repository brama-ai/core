## ADDED Requirements

### Requirement: Agent Manifest Endpoint
Every agent service in the platform SHALL expose `GET /api/v1/manifest` returning a JSON document
that describes the agent's identity, version, and capabilities.

#### Scenario: Manifest returns required fields
- **WHEN** core calls `GET /api/v1/manifest` on any registered agent
- **THEN** the response is HTTP 200, Content-Type `application/json`, and contains at minimum `name` (non-empty string) and `version` (semver `X.Y.Z`)

#### Scenario: Manifest with capabilities declares A2A endpoint
- **WHEN** the manifest `capabilities` array is non-empty
- **THEN** `a2a_endpoint` MUST be present and be a valid URL

#### Scenario: Manifest missing required field
- **WHEN** a manifest response omits `name` or `version`
- **THEN** core sets agent status to `error` and stores a violation: "Required field missing: {field}"

#### Scenario: Manifest endpoint requires no authentication
- **WHEN** core fetches `/api/v1/manifest` without an `Authorization` header
- **THEN** the agent returns a valid response (no 401/403)

---

### Requirement: Agent Health Endpoint
Every agent service SHALL expose `GET /health` returning `{"status": "ok"}` with HTTP 200.

#### Scenario: Health check passes
- **WHEN** core health poller calls `GET /health` on a running agent
- **THEN** the response is HTTP 200 with JSON body containing `"status": "ok"`

#### Scenario: Health endpoint requires no authentication
- **WHEN** core calls `GET /health` without any authorization header
- **THEN** the agent returns 200 (no auth required)

---

### Requirement: Agent A2A Endpoint
Every agent that declares non-empty `capabilities` SHALL expose `POST /api/v1/a2a` implementing
the standard request/response envelope.

#### Scenario: Valid A2A request handled
- **WHEN** core posts `{"tool": "<known-capability>", "input": {...}, "trace_id": "...", "request_id": "..."}` to `/api/v1/a2a`
- **THEN** agent returns HTTP 200 with `{"status": "completed|failed|needs_clarification", "output": {...}}`

#### Scenario: Unknown tool returns structured error
- **WHEN** core posts a request with an unknown `tool` value
- **THEN** agent returns HTTP 200 with `{"status": "failed", "error": "<descriptive message>", "output": null}`

#### Scenario: Malformed envelope returns 4xx
- **WHEN** a request body is missing the `tool` field entirely
- **THEN** agent returns HTTP 400 or 422

#### Scenario: Same request_id is idempotent
- **WHEN** the same `request_id` is submitted twice
- **THEN** the response `status` is consistent between both calls

---

### Requirement: Docker Compose Convention
Every agent service added to the platform `compose.yaml` SHALL follow the naming and label convention
to be automatically discoverable by core.

#### Scenario: Agent service is auto-discovered
- **WHEN** a service named `{name}-agent` with label `ai.platform.agent=true` is added to `compose.yaml` and started
- **THEN** core's next discovery cycle includes it without any manual registry configuration

#### Scenario: Service without agent naming convention is not discovered
- **WHEN** a service is added to `compose.yaml` without the `-agent` suffix and without the `ai.platform.agent=true` label
- **THEN** core does not attempt to fetch its manifest

---

### Requirement: Convention Compliance Test Suite
The platform SHALL provide a Codecept.js + Playwright test suite that verifies every registered
agent implements all required conventions, runnable via `make conventions-test`.

#### Scenario: All conventions pass for a compliant agent
- **WHEN** `make conventions-test` is executed against a running stack
- **THEN** all TC-01, TC-02, and TC-03 test cases pass for each discovered agent

#### Scenario: Non-compliant agent fails test suite
- **WHEN** an agent does not implement `GET /health`
- **THEN** `make conventions-test` exits with a non-zero code and a clear failure message identifying the agent and failing test case
