## ADDED Requirements

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
