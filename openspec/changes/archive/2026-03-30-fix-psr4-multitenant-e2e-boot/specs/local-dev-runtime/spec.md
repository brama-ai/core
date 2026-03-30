## MODIFIED Requirements
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

#### Scenario: PostgreSQL includes pgvector extension

- **WHEN** a developer starts the local stack
- **THEN** the PostgreSQL instance uses the `pgvector/pgvector:pg16` image
- **AND** the `vector` extension is pre-created in agent databases that require it (`news_maker_agent`, `news_maker_agent_test`)
