## ADDED Requirements

### Requirement: Compose-Based External Agent Onboarding

The platform SHALL define a documented operator workflow for onboarding an external agent into the
Docker-based runtime without editing the platform base compose files for each installation.

#### Scenario: Operator enables an external agent runtime

- **WHEN** an operator clones an agent repository into `projects/<agent-name>/`
- **THEN** the operator can attach the agent to the platform runtime through a documented compose
  fragment or compose template
- **AND** the agent service joins the same Docker network and discovery flow as bundled services

#### Scenario: Operator removes an external agent runtime

- **WHEN** an operator wants to detach an external agent from the local or self-hosted Docker stack
- **THEN** the workflow documents how to stop the service, remove its compose fragment, and retain
  or delete its persistent data intentionally

### Requirement: External Agent Upgrade Playbook

The platform SHALL provide an operator playbook for upgrading an external agent checkout while
preserving platform compatibility.

#### Scenario: Operator upgrades a checked-out agent repository

- **WHEN** an operator pulls a new tag or commit for an external agent repository
- **THEN** the documentation describes how to rebuild or pull the service image, run any migration
  step, and verify manifest/health compatibility before enabling traffic

#### Scenario: Compatibility check fails after upgrade

- **WHEN** an upgraded external agent no longer satisfies platform conventions
- **THEN** the platform surfaces the failure through the existing discovery or convention
  verification flow
- **AND** the operator documentation includes rollback guidance
