## ADDED Requirements

### Requirement: Inline Health Probe on Registration
The platform SHALL perform an immediate health probe when an agent registers via `/api/v1/internal/agents/register` and the manifest includes a `health_url` field. The probe result SHALL update the agent's `health_status` in the database before the registration response is returned.

If the health probe succeeds (HTTP 2xx from `health_url`), `health_status` SHALL be set to `healthy`. If the probe fails or times out, `health_status` SHALL remain `unknown` and the periodic health poller will retry on its next cycle.

The inline probe SHALL use a short timeout (2 seconds) to avoid blocking the registration API.

#### Scenario: Registration with health_url triggers immediate probe
- **WHEN** an agent registers with a manifest containing `health_url`
- **AND** the health endpoint responds with HTTP 200
- **THEN** the agent's `health_status` is set to `healthy` in the database
- **AND** the registration response includes `"health_status": "healthy"`

#### Scenario: Registration with unreachable health_url
- **WHEN** an agent registers with a manifest containing `health_url`
- **AND** the health endpoint is unreachable or returns a non-2xx status
- **THEN** the agent's `health_status` remains `unknown`
- **AND** the registration response includes `"health_status": "unknown"`
- **AND** the periodic health poller will attempt to resolve the status on its next cycle

#### Scenario: Registration without health_url skips probe
- **WHEN** an agent registers with a manifest that does not contain `health_url`
- **THEN** the agent's `health_status` defaults to `unknown` (existing behavior)
- **AND** no inline health probe is performed
