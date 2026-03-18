## ADDED Requirements

### Requirement: Agent Card Schema
The platform SHALL validate agent metadata against the Agent Card JSON Schema (`config/agent-card.schema.json`). The schema uses official A2A terminology: `skills` (was `capabilities`), `skill_schemas` (was `capability_schemas`).

#### Scenario: Valid Agent Card with skills
- **WHEN** an agent provides an Agent Card with `name`, `version`, `description`, `permissions`, `commands`, `events`, `a2a_endpoint`, and `skills` fields
- **THEN** the Agent Card passes validation

#### Scenario: Legacy capabilities field rejected
- **WHEN** an agent provides an Agent Card with `capabilities` instead of `skills`
- **THEN** the schema validation rejects the field as unknown (additionalProperties: false)

### Requirement: Agent Card Fetcher
The platform SHALL fetch Agent Cards from registered agents using the `AgentCardFetcher` service (was `AgentManifestFetcher`).

#### Scenario: Fetch Agent Card from agent
- **WHEN** the platform discovers a new agent via Traefik
- **THEN** the `AgentCardFetcher` retrieves the Agent Card from `http://{hostname}/api/v1/manifest`

### Requirement: A2A Message Audit
The platform SHALL record all A2A message invocations in the `a2a_message_audit` table (was `agent_invocation_audit`) with a `skill` column (was `tool`).

#### Scenario: Audit records use skill terminology
- **WHEN** the platform invokes a skill on a remote agent
- **THEN** the audit record stores the skill name in the `skill` column of `a2a_message_audit`
