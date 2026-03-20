## MODIFIED Requirements

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
