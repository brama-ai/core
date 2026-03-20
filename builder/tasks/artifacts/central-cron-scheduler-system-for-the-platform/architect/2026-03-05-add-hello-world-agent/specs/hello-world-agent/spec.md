## ADDED Requirements

### Requirement: Hello-World Agent Webview
The hello-agent SHALL expose a public web page that displays a greeting message to the client.

The greeting text SHALL default to "Hello, World!" and MAY be overridden via the agent's `config.description` field set by an admin.

#### Scenario: Default greeting displayed
- **WHEN** a client opens the hello-agent webview at `/`
- **THEN** the page SHALL render with the text "Hello, World!"

#### Scenario: Custom greeting from admin config
- **WHEN** an admin sets `config.description` to "Welcome to our community!"
- **AND** a client opens the hello-agent webview at `/`
- **THEN** the page SHALL render with the text "Welcome to our community!"

### Requirement: Hello-World Agent Manifest
The hello-agent SHALL expose `GET /api/v1/manifest` returning a valid JSON manifest per platform agent conventions.

The manifest SHALL include:
- `name`: `"hello-agent"`
- `version`: strict semver (e.g. `"1.0.0"`)
- `description`: a short agent description
- `capabilities`: empty array `[]` (no A2A capabilities in MVP)
- `health_url`: `"http://hello-agent/health"`

#### Scenario: Valid manifest returned
- **WHEN** a GET request is sent to `/api/v1/manifest`
- **THEN** the response status SHALL be 200
- **AND** the response SHALL contain `name`, `version`, `capabilities` fields
- **AND** `name` SHALL be `"hello-agent"`
- **AND** `version` SHALL match semver pattern

### Requirement: Hello-World Agent Health Endpoint
The hello-agent SHALL expose `GET /health` returning `{"status": "ok"}` per platform agent conventions.

#### Scenario: Health check succeeds
- **WHEN** a GET request is sent to `/health`
- **THEN** the response status SHALL be 200
- **AND** the JSON body SHALL contain `"status": "ok"`

### Requirement: Hello-World Agent Docker Setup
The hello-agent SHALL be deployed as a Docker service with the label `ai.platform.agent=true`, routed through Traefik on entrypoint `:8085`.

#### Scenario: Agent discovered by platform
- **WHEN** the Docker stack is running
- **THEN** Traefik SHALL route port 8085 to the hello-agent service
- **AND** the core platform discovery SHALL detect `hello-agent` via Traefik API

### Requirement: Admin Config for Agent Description and System Prompt
The core admin panel SHALL allow editing `description` and `system_prompt` fields in the `config` JSONB column of `agent_registry` for any registered agent.

The edit form SHALL be accessible from the agents list page.

#### Scenario: Admin edits agent config
- **WHEN** an admin opens the agents list at `/admin/agents`
- **AND** clicks "Edit config" on a registered agent
- **THEN** a form SHALL be displayed with `description` and `system_prompt` text fields
- **AND** submitting the form SHALL update the agent's `config` via `PUT /api/v1/internal/agents/{name}/config`

#### Scenario: Config persists across agent restarts
- **WHEN** an admin saves `description` and `system_prompt` for an agent
- **AND** the agent service restarts and re-registers
- **THEN** the `config` values SHALL be preserved (not overwritten by discovery)

### Requirement: Hello-World Agent Automated Tests
The hello-agent SHALL have Codeception unit and functional test suites that verify the webview, manifest, and health endpoints.

Platform convention tests (`make conventions-test`) SHALL pass for the hello-agent.

#### Scenario: Unit and functional tests pass
- **WHEN** `make hello-test` is executed
- **THEN** all Codeception test suites SHALL pass with zero failures

#### Scenario: Convention tests pass
- **WHEN** `AGENT_URL=http://localhost:8085 make conventions-test` is executed
- **THEN** all TC-01 through TC-04 test cases SHALL pass
