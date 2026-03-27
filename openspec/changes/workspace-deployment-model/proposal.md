# Change: Establish Workspace Deployment Model for Compose, Devcontainer, and k3s

## Why

The platform currently mixes application concerns, local runtime concerns, and future cluster
deployment concerns across multiple places. This makes it unclear:

- which files define the fastest single-node deployment path
- how devcontainer relates to Docker Compose
- where k3s deployment assets and instructions should live
- how operators and developers are expected to validate each deployment mode

Without a documented deployment model, every new environment change risks adding another partial
workflow or another undocumented assumption.

## What Changes

- **ADDED**: A canonical deployment model with three explicit runtime modes:
  - Docker Compose as the baseline single-machine deployment path
  - Devcontainer as a developer overlay on top of Docker Compose
  - k3s as the cluster-oriented deployment path
- **ADDED**: Documentation requirements for where deployment instructions and assets must live
- **ADDED**: A packaging ownership rule that keeps `compose*.yaml` in the workspace while moving
  `Dockerfile` definitions next to the code they build
- **ADDED**: Verification-oriented acceptance criteria for each mode
- **MODIFIED**: Local development runtime documentation to distinguish baseline runtime from overlays

## Impact

- Affected specs:
  - local-dev-runtime
  - k3s-deployment
- Affected docs:
  - workspace README files
  - deployment guides for Compose, devcontainer, and k3s
- Affected structure:
  - workspace root for Compose and devcontainer assets
  - project repositories for Docker image build definitions
  - deployment directory for k3s assets
