## ADDED Requirements

### Requirement: Foundry Single Entrypoint
The sequential Foundry runtime SHALL expose `agentic-development/foundry.sh` as its canonical operator entrypoint.

#### Scenario: Operator launches Foundry without arguments
- **WHEN** an operator runs `agentic-development/foundry.sh`
- **THEN** the Foundry interactive monitor UI opens
- **AND** the UI shows current task lifecycle state and worker activity

#### Scenario: Operator launches Foundry in headless mode
- **WHEN** an operator runs `agentic-development/foundry.sh headless`
- **THEN** Foundry starts or resumes background worker processing without opening the interactive UI
- **AND** the runtime writes status, logs, and task lifecycle updates to the same Foundry state store used by the monitor

### Requirement: Foundry Unified Command Surface
The Foundry runtime SHALL provide built-in operational commands through `agentic-development/foundry.sh` instead of requiring operators to learn separate top-level wrapper scripts for common actions.

#### Scenario: Operator runs a named command
- **WHEN** an operator runs `agentic-development/foundry.sh command <name> [args...]`
- **THEN** Foundry executes the requested operational command
- **AND** the command uses the same runtime state and task root as interactive mode

#### Scenario: Operator selects a command from the UI
- **WHEN** an operator opens the Foundry interactive UI
- **THEN** they can access a command list or command tab
- **AND** they can launch supported Foundry operations from within the UI

### Requirement: Foundry Task Root
The sequential Foundry runtime SHALL use `agentic-development/foundry-tasks/` as its canonical task lifecycle root.

#### Scenario: Runtime initializes task directories
- **WHEN** Foundry starts and the task root does not exist
- **THEN** it creates the lifecycle directories under `agentic-development/foundry-tasks/`
- **AND** those directories include `todo`, `in-progress`, `done`, `failed`, `suspended`, `summary`, `artifacts`, and `archive`

#### Scenario: Operator creates or processes a Foundry task
- **WHEN** Foundry queues, starts, completes, fails, suspends, or archives a task
- **THEN** the lifecycle transition is recorded under `agentic-development/foundry-tasks/`
- **AND** the operator-facing monitor reads from the same task root

### Requirement: Legacy Entry Compatibility
The Foundry migration SHALL preserve a compatibility path for legacy sequential pipeline entrypoints during the rollout window.

#### Scenario: Operator invokes a legacy script
- **WHEN** an operator runs a legacy sequential runtime script such as `pipeline.sh`, `pipeline-batch.sh`, or the old monitor launcher
- **THEN** the invocation is forwarded to the Foundry runtime or an equivalent compatibility wrapper
- **AND** the operator sees a deprecation hint pointing to `agentic-development/foundry.sh`
