## ADDED Requirements

### Requirement: E2E Agent Health Status Resolution
The E2E workflow SHALL ensure that all registered agents have a resolved `health_status` (not `unknown`) before E2E test execution begins. This requires that E2E agent registration payloads include `health_url` pointing to the agent's health endpoint using Docker DNS names, and that a health poll cycle runs after registration.

#### Scenario: E2E agents register with health_url
- **WHEN** `e2e-register-agents` registers agents in core-e2e
- **THEN** each registration payload includes a `health_url` field with the agent's Docker-internal health endpoint (e.g., `http://hello-agent-e2e/health`)
- **AND** the `health_url` uses Docker DNS names reachable from the core-e2e container

#### Scenario: Health status resolved before test execution
- **WHEN** `make e2e-prepare` completes
- **THEN** all registered agents have `health_status` set to `healthy` or `degraded` (not `unknown`)
- **AND** the admin agents page renders `badge-healthy` or `badge-degraded` for each registered agent

#### Scenario: In-test agent re-registration preserves health resolution
- **WHEN** an E2E test Before hook re-registers an agent via `/api/v1/internal/agents/register` with `health_url`
- **THEN** the registration response includes the resolved `health_status`
- **AND** the agent's health badge is immediately visible on the admin agents page without waiting for a poller cycle
