## ADDED Requirements
### Requirement: Failed E2E Runs Produce Investigation Handoffs

The E2E workflow SHALL produce a structured investigation handoff whenever an executed E2E scenario fails.

The handoff SHALL include:
- failing scenario or test identifier
- failure summary
- reproduction command
- artifact paths for logs or reports
- branch and commit metadata
- preliminary classification hint

#### Scenario: Assertion failure produces investigation handoff
- **WHEN** `make e2e` executes
- **AND** one or more scenarios run and fail
- **THEN** the workflow emits a structured investigation handoff payload
- **AND** the payload is sufficient for a follow-up investigator to reproduce and analyze the bug without re-reading the entire parent task

#### Scenario: E2E bootstrap failure is tagged for infra investigation
- **WHEN** the E2E workflow fails before scenario execution because stack preparation, migrations, or test infrastructure bootstrap fails
- **THEN** the workflow still emits a structured investigation handoff
- **AND** the handoff classification is `infra`
- **AND** it points to the failing bootstrap command and logs
