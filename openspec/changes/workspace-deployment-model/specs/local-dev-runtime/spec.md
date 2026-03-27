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
- **THEN** the required `compose*.yaml` files must live in the workspace repository under `docker/`
- **AND** those files must describe how projects are assembled together, not duplicate project-owned
  build definitions
- **AND** Compose files SHALL reference project Dockerfiles via `build.context` pointing to the
  project directory and `build.dockerfile` naming the project-owned Dockerfile

#### Scenario: Looking for a project image definition
- **WHEN** an operator or developer needs the `Dockerfile` used to build a deployable project image
- **THEN** that `Dockerfile` must live at the root of the owning project directory
- **AND** the workspace Compose layer SHALL reference it via `build.context` and `build.dockerfile`
- **AND** the workspace `docker/` directory SHALL NOT contain application Dockerfiles for
  deployable projects
- **AND** workspace-only tooling images (devcontainer, slides, templates) are exempt from this rule

#### Scenario: Looking for k3s deployment assets
- **WHEN** an operator needs k3s deployment manifests or charts
- **THEN** the required files must live under a dedicated deployment directory in the workspace repository

### Requirement: Dockerfile Ownership Rule
Each deployable project SHALL own its `Dockerfile` at the project repository root, while Docker
Compose files remain in the workspace as the assembly layer.

#### Scenario: Building a project image independently
- **WHEN** a CI/CD pipeline or developer builds a project image
- **THEN** the build context SHALL be the project directory (e.g., `brama-core/`, `brama-agents/hello-agent/`)
- **AND** the `Dockerfile` SHALL be found at the root of that project directory
- **AND** the build SHALL NOT require the full workspace tree

#### Scenario: Compose references project Dockerfiles correctly
- **WHEN** a Compose service defines a `build` section for a deployable project
- **THEN** `build.context` SHALL point to the project directory relative to the Compose file
- **AND** `build.dockerfile` SHALL be `Dockerfile` (the project-owned file)
- **AND** no Compose service SHALL use a `build.dockerfile` path that points into `docker/` for
  a deployable project image

#### Scenario: Core Dockerfile lives in brama-core
- **WHEN** the core platform image is built
- **THEN** the Dockerfile at `brama-core/Dockerfile` SHALL be used
- **AND** the Compose service `core` in `docker/compose.core.yaml` SHALL reference
  `context: ../brama-core` and `dockerfile: Dockerfile`
- **AND** no application Dockerfile for core SHALL exist under `docker/`

#### Scenario: No stale Dockerfile remnants in workspace docker directory
- **WHEN** the workspace `docker/` directory is inspected
- **THEN** there SHALL be no empty project subdirectories left from previous Dockerfile locations
- **AND** the only Dockerfiles under `docker/` SHALL be for workspace-level tooling (e.g., `docker/slides/Dockerfile`)

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
