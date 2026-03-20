## ADDED Requirements

### Requirement: A2A Server Admin Section
The admin panel SHALL display an "A2A Server" information section for each registered agent that has an A2A endpoint configured.

#### Scenario: Agent with A2A endpoint shows A2A Server section
- **WHEN** an agent has `a2a_endpoint` defined in its Agent Card
- **THEN** the admin agents page displays an "A2A Server" collapsible section for that agent
- **AND** the section shows the A2A endpoint URL, list of skills, and skill schemas (if any)

#### Scenario: Agent without A2A endpoint hides A2A Server section
- **WHEN** an agent does not have `a2a_endpoint` in its Agent Card
- **THEN** no "A2A Server" section is displayed for that agent

### Requirement: A2A Gateway Architecture
The platform core SHALL act as an A2A Gateway — accepting requests as an A2A Server (from OpenClaw) and forwarding them as an A2A Client (to remote agents). All gateway services SHALL reside in the `App\A2AGateway` namespace.

#### Scenario: Core forwards message through gateway
- **WHEN** OpenClaw sends a skill invocation via `POST /api/v1/a2a/send-message`
- **THEN** the `A2AClient` service (in `App\A2AGateway`) resolves the skill to an enabled agent, sends the request to the agent's A2A endpoint, and returns the response

### Requirement: A2A Gateway API Routes
The platform SHALL expose A2A Gateway endpoints under the `/api/v1/a2a/` path prefix with route names prefixed by `api_a2a_`.

#### Scenario: SendMessage endpoint
- **WHEN** an A2A Client sends `POST /api/v1/a2a/send-message` with valid credentials and a skill name
- **THEN** the platform routes the request to the matching agent and returns the response

#### Scenario: Discovery endpoint
- **WHEN** an A2A Client sends `GET /api/v1/a2a/discovery` with valid credentials
- **THEN** the platform returns a catalog of available skills from all enabled agents

### Requirement: A2A Terminology Alignment
All code namespaces, class names, and configuration keys SHALL use official A2A protocol terminology.

#### Scenario: Gateway namespace
- **WHEN** a developer looks at the service classes for A2A gateway functionality
- **THEN** the namespace is `App\A2AGateway` (not `App\AgentDiscovery`)

#### Scenario: Controller namespace
- **WHEN** a developer looks at the controller namespace for A2A API endpoints
- **THEN** the namespace is `Controller\Api\A2AGateway` (not `Controller\Api\OpenClaw`)
