## ADDED Requirements

### Requirement: Foundry Monitor Continuity Across Modes
The pipeline monitor SHALL present the same Foundry task and worker state regardless of whether Foundry work was started interactively or through headless mode.

#### Scenario: Headless worker started before monitor opens
- **WHEN** an operator starts Foundry with `agentic-development/foundry.sh headless`
- **AND** later opens `agentic-development/foundry.sh` without arguments
- **THEN** the interactive monitor shows the running or completed headless worker activity
- **AND** the monitor exposes the current queue state, logs, and task lifecycle transitions for that run

#### Scenario: Monitor displays Foundry task root state
- **WHEN** the interactive monitor renders its task list and worker tabs
- **THEN** it reads Foundry lifecycle data from `agentic-development/foundry-tasks/`
- **AND** it does not require a separate legacy monitor-only task root
