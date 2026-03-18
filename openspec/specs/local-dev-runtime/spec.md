# local-dev-runtime Specification

## Purpose
TBD - created by archiving change add-local-dev-compose-topology. Update Purpose after archive.
## Requirements
### Requirement: Local Development Topology

The system SHALL provide a local Docker Compose topology for bootstrapping the platform runtime with `Traefik` as the public routing layer.

#### Scenario: Core surface available on default HTTP port

- **WHEN** a developer starts the local stack
- **THEN** the core platform surface is reachable at `http://localhost/`
- **AND** the request is routed through `Traefik`

#### Scenario: Auxiliary surfaces are isolated by entrypoint

- **WHEN** a developer starts the local stack
- **THEN** auxiliary surfaces for admin bootstrap and OpenClaw bootstrap are reachable on dedicated local ports
- **AND** those requests are routed through `Traefik` entrypoints rather than direct container port publishing

### Requirement: Local Infrastructure Dependencies

The local development topology SHALL include the baseline infrastructure services required by the platform runtime.

#### Scenario: Stateful services are available for development

- **WHEN** a developer starts the local stack
- **THEN** local instances of `Postgres`, `Redis`, `OpenSearch`, and `RabbitMQ` are started
- **AND** each service is reachable on a conventional local development port

#### Scenario: Infrastructure services are not treated as Traefik-routed app surfaces

- **WHEN** infrastructure services are exposed locally
- **THEN** they use direct local ports for their native protocols or management endpoints
- **AND** only application HTTP surfaces remain behind `Traefik`

### Requirement: MVP Boundary Preservation

The local development topology SHALL preserve the repository's documented MVP architecture boundaries.

#### Scenario: OpenClaw remains replaceable

- **WHEN** an OpenClaw runtime is included in the local stack
- **THEN** it is modeled as a separate service behind a bounded interface
- **AND** it does not become the owner of platform gateway, data, or permissions

#### Scenario: Admin bootstrap does not redefine MVP scope

- **WHEN** an admin-facing stub exists in the local stack
- **THEN** it is documented as a technical placeholder for routing or hardening checks
- **AND** it is not treated as an approved MVP web admin panel

### Requirement: Stub-First Bootstrap

The first local runtime implementation SHALL start with hello world stubs before full framework integration.

#### Scenario: Core service reserves the future framework path

- **WHEN** the first local stack is implemented
- **THEN** the core service may respond with a minimal hello world response
- **AND** its container layout remains compatible with a future `PHP + Symfony 7 + Composer + Neuron AI` application

### Requirement: Dedicated Core E2E Runtime Surface
The local development topology SHALL provide a dedicated E2E runtime surface for ALL platform services, configured independently from the default runtime.

#### Scenario: E2E runtime uses isolated database configuration
- **WHEN** a developer starts the E2E runtime topology via `make e2e-prepare`
- **THEN** all E2E service instances use `DATABASE_URL` targeting `_test` databases
- **AND** all E2E service instances use isolated Redis DB numbers, OpenSearch indices, and RabbitMQ vhost
- **AND** the default runtime services remain configured for production resources

#### Scenario: E2E runtime is optional for normal development
- **WHEN** a developer starts the default local runtime via `make up` (without `--profile e2e`)
- **THEN** only the default runtime services are started
- **AND** E2E-specific containers (gated by `profiles: [e2e]`) are not started

#### Scenario: E2E and dev runtimes coexist
- **WHEN** both dev and E2E containers are running simultaneously
- **THEN** they share the same infrastructure containers (Postgres, Redis, OpenSearch, RabbitMQ)
- **AND** data isolation is maintained through namespace separation (separate DBs, indices, vhosts, Redis DB numbers)
- **AND** there is no cross-contamination between dev and E2E data

