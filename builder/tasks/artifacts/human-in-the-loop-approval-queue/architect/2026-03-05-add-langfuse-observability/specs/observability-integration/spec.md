## ADDED Requirements

### Requirement: Local Langfuse Stack for Observability
The platform SHALL provide a local self-hosted Langfuse stack in root Docker Compose for operator trace inspection.

The stack SHALL include web, worker, and required data dependencies and SHALL be reachable through Traefik.

#### Scenario: Langfuse stack is available locally
- **WHEN** the platform compose stack is started
- **THEN** Langfuse web UI SHALL be reachable at `http://localhost:8086/`
- **AND** Langfuse worker and storage dependencies SHALL be healthy in Docker

### Requirement: Traefik Edge Auth for Tool EntryPoints
The platform SHALL protect tool entrypoints with Traefik forward-auth and JWT cookie validation.

Protected entrypoints SHALL include `openclaw`, `langfuse`, and all agent entrypoints.

#### Scenario: Unauthorized user opens protected tool URL
- **WHEN** a user opens a protected URL (for example `http://localhost:8086/` or `http://localhost:8085/`) without a valid JWT cookie
- **THEN** Traefik SHALL redirect the user to `/edge/auth/login`
- **AND** the redirect SHALL preserve original target URL in `rd` query parameter

#### Scenario: User logs in on edge login page
- **WHEN** a user submits valid admin credentials on `/edge/auth/login`
- **THEN** core SHALL issue JWT cookie for edge access
- **AND** the browser SHALL be redirected to the original `rd` target URL

### Requirement: OpenClaw Invocation Tracing in Core
The core OpenClaw invoke API SHALL emit Langfuse trace data for OpenClaw-originated tool invocations.

#### Scenario: Tool invocation arrives from OpenClaw
- **WHEN** `POST /api/v1/agents/invoke` is called with a valid tool payload
- **THEN** core SHALL emit a Langfuse trace for the invocation
- **AND** core SHALL emit a span describing outbound A2A execution status and duration

### Requirement: A2A Trace Context Propagation
The core A2A bridge SHALL propagate trace and correlation headers to downstream agents.

#### Scenario: Core calls downstream agent A2A endpoint
- **WHEN** core sends an outbound A2A HTTP request
- **THEN** it SHALL include `traceparent`, `x-request-id`, `x-agent-run-id`, and `x-a2a-hop` headers

### Requirement: Hello-Agent A2A Tracing
The hello-agent SHALL emit Langfuse span data for incoming A2A requests.

#### Scenario: Hello-agent handles a2a request
- **WHEN** hello-agent receives `POST /api/v1/a2a`
- **THEN** it SHALL emit a Langfuse span correlated by `trace_id` and `request_id`
- **AND** the span SHALL include intent, status, and duration metadata
