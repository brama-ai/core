## ADDED Requirements

### Requirement: External Agent Workspace Convention

The platform SHALL define a standard workspace convention for agent repositories that are maintained
outside the core platform repository.

#### Scenario: Operator checks out an external agent repository

- **WHEN** an operator wants to add an externally maintained agent to the platform workspace
- **THEN** the documentation instructs the operator to clone the repository into
  `projects/<agent-name>/`
- **AND** the platform tooling treats that checkout as a supported source location

#### Scenario: Platform core repository remains independent

- **WHEN** an agent is maintained in an external repository
- **THEN** the platform core repository does not require the agent source to be copied into `apps/`
- **AND** the shared infrastructure and operator tooling remain owned by the platform repository

### Requirement: Source-Origin Agnostic Runtime Contract

The platform SHALL apply the same runtime contract to in-repository agents and externally checked
out agents.

#### Scenario: External agent joins discovery flow

- **WHEN** an external agent service is started in the platform network
- **THEN** core discovers it using the same manifest and health endpoint contract as an in-repo
  agent
- **AND** the agent can enter the same registry and lifecycle flows

#### Scenario: External agent uses platform conventions

- **WHEN** an external agent is reviewed for compatibility
- **THEN** it is validated against the same conventions for service labels, manifest fields,
  endpoint paths, and health checks as any bundled agent
