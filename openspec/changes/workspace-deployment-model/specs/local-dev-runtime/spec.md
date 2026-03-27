# Local Development Runtime Specification

## MODIFIED Requirements

### Requirement: Local Runtime Modes Must Be Explicit
The platform SHALL define a clear relationship between Docker Compose, devcontainer, and k3s.

#### Scenario: Reading workspace deployment documentation
- **WHEN** an operator or developer reads the workspace documentation
- **THEN** Docker Compose must be presented as the baseline single-machine runtime
- **AND** devcontainer must be presented as a developer overlay on top of Docker Compose
- **AND** k3s must be presented as a separate cluster-oriented deployment mode

### Requirement: Runtime Assets Must Have Stable Ownership
Workspace runtime files SHALL live in predictable locations based on deployment purpose.

#### Scenario: Looking for Compose runtime files
- **WHEN** an operator needs Docker Compose runtime assets
- **THEN** the required files must live in the workspace repository root or its workspace-level support directories

#### Scenario: Looking for devcontainer assets
- **WHEN** a developer needs devcontainer definitions
- **THEN** the required files must live under `.devcontainer/` in the workspace repository

#### Scenario: Looking for Compose assembly files
- **WHEN** an operator or developer needs Docker Compose topology files
- **THEN** the required `compose*.yaml` files must live in the workspace repository
- **AND** those files must describe how projects are assembled together, not duplicate project-owned
  build definitions

#### Scenario: Looking for a project image definition
- **WHEN** an operator or developer needs the `Dockerfile` used to build a deployable project image
- **THEN** that `Dockerfile` must live next to the owning project codebase
- **AND** the workspace Compose layer may reference it via `build.context` and `dockerfile`
- **AND** the workspace repository must not be the long-term owner of per-project application
  Dockerfiles except for workspace-only tooling images

#### Scenario: Looking for k3s deployment assets
- **WHEN** an operator needs k3s deployment manifests or charts
- **THEN** the required files must live under a dedicated deployment directory in the workspace repository

### Requirement: Each Runtime Mode Must Be Verifiable
Each runtime mode SHALL have documented verification steps that can be executed immediately after setup.

#### Scenario: Verifying Docker Compose setup
- **WHEN** Docker Compose setup completes
- **THEN** the documentation must provide commands to confirm the stack is running
- **AND** the documentation must state expected success signals such as healthy services or reachable endpoints

#### Scenario: Verifying devcontainer setup
- **WHEN** a devcontainer is created or rebuilt
- **THEN** the documentation must provide commands to confirm that the container has access to the Compose-backed runtime
- **AND** the documentation must state expected success signals such as tool availability or reachable local services
