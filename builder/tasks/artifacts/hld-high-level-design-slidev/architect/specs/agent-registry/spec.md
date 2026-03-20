## MODIFIED Requirements

### Requirement: Agent Registration Source
The platform agent registry SHALL use pull-based discovery rather than push-based self-registration.
Core is the sole owner of agent lifecycle. Agents are not required to call any registration endpoint.

#### Scenario: New agent container starts
- **WHEN** a new service ending in `-agent` is added to `compose.yaml` and started
- **THEN** within 60 seconds, core's discovery cycle fetches its `/api/v1/manifest` and upserts it in the registry

#### Scenario: Agent not yet running on first discovery
- **WHEN** `AgentDiscoveryCommand` runs and a known agent service is not reachable
- **THEN** the agent's status is set to `unavailable` and the previous manifest data is preserved

#### Scenario: Manifest updated on agent restart
- **WHEN** an agent container restarts with a new version in its manifest
- **THEN** on the next discovery cycle, core updates the registry row with the new manifest data and version

---

### Requirement: Agent Status State Machine
The agent registry SHALL track each agent's operational status using a 4-state machine.

#### Scenario: Agent with valid manifest and passing health check
- **WHEN** discovery fetches a valid manifest AND the health endpoint returns 200
- **THEN** agent status is set to `healthy`

#### Scenario: Agent with manifest warnings
- **WHEN** discovery fetches a manifest that passes required-field validation but has convention warnings
- **THEN** agent status is set to `degraded` and violations are stored in the registry

#### Scenario: Agent container is down
- **WHEN** the health poller receives 3 consecutive non-200 responses from an agent
- **THEN** agent status is set to `unavailable`

#### Scenario: Agent manifest is unreadable
- **WHEN** discovery cannot parse the manifest response as valid JSON, or receives HTTP 5xx
- **THEN** agent status is set to `error`, the raw response is stored, and the admin panel shows the error detail

#### Scenario: Agent recovers from unavailable
- **WHEN** an agent with `unavailable` status responds successfully to the next discovery cycle
- **THEN** status transitions back to `healthy` or `degraded` based on manifest validity

---

## ADDED Requirements

### Requirement: Traefik-Based Service Discovery
Core SHALL discover agent services by querying the Traefik management API.

#### Scenario: Core discovers agent from Traefik
- **WHEN** `AgentDiscoveryCommand` runs
- **THEN** it queries `http://traefik:8080/api/http/services`, filters services matching `*-agent@docker`, and attempts manifest fetch for each

#### Scenario: Non-agent services are ignored
- **WHEN** Traefik API returns services that do not end with `-agent`
- **THEN** those services are not processed by the discovery loop

---

### Requirement: Convention Violation Reporting
The admin panel SHALL display convention violations for agents in degraded or error state.

#### Scenario: Admin views agent with violations
- **WHEN** an agent has status `degraded` or `error` and admin clicks its status badge
- **THEN** a modal displays the formatted list of convention violations (field name, violation type, recommendation)

#### Scenario: Agent clears all violations after fix
- **WHEN** an agent previously in `degraded` state updates its manifest to fix all violations
- **THEN** on the next discovery cycle, violations are cleared and status becomes `healthy`

---

### Requirement: Manual Discovery Trigger
The admin panel SHALL provide a "Run Discovery" action that immediately triggers a discovery cycle.

#### Scenario: Admin triggers manual discovery
- **WHEN** admin clicks "Run Discovery" on the agents page
- **THEN** `AgentDiscoveryCommand` runs synchronously, registry is updated, and admin sees a flash message with the result summary

---

### Requirement: Add-by-URL Stub
The admin panel SHALL show an "Add by URL" control that communicates upcoming functionality.

#### Scenario: Admin clicks "Add by URL"
- **WHEN** admin clicks the "Add by URL" button on the agents page
- **THEN** a modal appears with the message that URL-based provisioning is in development and instructions to add the agent to `compose.yaml` manually

## REMOVED Requirements

### Requirement: Agent Push Registration
**Reason**: Replaced by pull-based Traefik discovery. Agents no longer call `POST /api/v1/internal/agents/register` on startup.
**Migration**: Remove `KnowledgeRegisterCommand` and equivalent commands from all agents. Core discovers agents automatically.
