## ADDED Requirements

### Requirement: Agent Discovery Endpoint
The platform SHALL expose `GET /api/v1/agents/discovery` returning a tool catalog of all enabled agents as OpenClaw-compatible tool definitions, one entry per agent capability.

#### Scenario: Discovery returns enabled agents as tools
- **WHEN** an authenticated caller sends GET `/api/v1/agents/discovery`
- **THEN** the response includes one tool entry per capability of each enabled agent, with `name`, `agent`, `description`, and `input_schema`

#### Scenario: Disabled agent not included in discovery
- **WHEN** an agent is disabled in the registry
- **THEN** none of its capabilities appear in the discovery response

#### Scenario: Discovery endpoint requires authentication
- **WHEN** an unauthenticated request is made to `/api/v1/agents/discovery`
- **THEN** the platform returns `401 Unauthorized`

#### Scenario: Discovery reflects immediate registry change
- **WHEN** an admin enables or disables an agent
- **THEN** the next call to `/api/v1/agents/discovery` (after cache TTL or immediate push) reflects the updated state

---

### Requirement: Capability-to-Tool Schema Translation
The platform SHALL derive an OpenClaw tool `input_schema` for each agent capability from the agent's manifest or from a capability-specific schema provided by the agent.

#### Scenario: Capability with explicit schema
- **WHEN** an agent registers and provides a `capability_schemas` map in its manifest
- **THEN** the corresponding tool entry in the discovery response uses that schema as `input_schema`

#### Scenario: Capability without explicit schema
- **WHEN** a capability is declared in `capabilities` but no schema is provided
- **THEN** the tool entry uses an open schema `{ "type": "object" }` with a note in the description

---

### Requirement: A2A Invoke Bridge
The platform SHALL expose `POST /api/v1/agents/invoke` as the sole path for OpenClaw to call any agent, routing the call through the platform A2A contract.

#### Scenario: Invoke succeeds for enabled agent
- **WHEN** OpenClaw sends a valid invoke request with `tool` and `input` for an enabled agent
- **THEN** the platform resolves the agent, sends the A2A request, and returns the A2A response with `status: completed`

#### Scenario: Invoke rejected for disabled agent
- **WHEN** OpenClaw sends an invoke request for a tool belonging to a disabled agent
- **THEN** the platform returns `{ "status": "failed", "reason": "agent_disabled" }` without calling the agent

#### Scenario: Unknown tool rejected
- **WHEN** OpenClaw sends an invoke request with a `tool` name not found in the registry
- **THEN** the platform returns `{ "status": "failed", "reason": "unknown_tool" }`

#### Scenario: Invoke call audited
- **WHEN** any invoke request is processed
- **THEN** the platform writes an audit log entry with: `tool`, `agent`, `trace_id`, `request_id`, `duration_ms`, `status`, `actor=openclaw`

---

### Requirement: Discovery Authentication via Gateway Token
Both the discovery endpoint and the invoke bridge SHALL authenticate callers using the platform gateway token shared with OpenClaw.

#### Scenario: Valid token accepted
- **WHEN** OpenClaw sends `Authorization: Bearer <OPENCLAW_GATEWAY_TOKEN>` with a discovery or invoke request
- **THEN** the request is authenticated and processed

#### Scenario: Invalid or missing token rejected
- **WHEN** a request arrives with no token or an incorrect token
- **THEN** the platform returns `401 Unauthorized` with no agent data exposed
