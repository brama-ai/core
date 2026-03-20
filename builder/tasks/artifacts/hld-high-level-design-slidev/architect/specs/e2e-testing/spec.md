## MODIFIED Requirements

### Requirement: Playwright E2E Infrastructure
The project SHALL include a Playwright test suite under `tests/e2e/` for end-to-end verification
of HTTP endpoints against a fully isolated E2E Docker Compose stack.

#### Scenario: E2E suite executes via make target
- **WHEN** `make e2e` is executed with the Docker Compose stack running
- **THEN** Playwright discovers and executes all E2E tests and reports pass/fail results
- **AND** the suite targets the E2E runtime surfaces for all services (core-e2e, agent-e2e containers, openclaw-e2e)

#### Scenario: REST/console E2E suites target E2E runtime
- **WHEN** API-oriented Codecept scenarios are executed (for example `tests/openclaw/a2a_bridge_test.js`)
- **THEN** they use the E2E OpenClaw gateway and E2E Core base URL

#### Scenario: E2E tests are isolated from PHP test suites
- **WHEN** `codecept run` executes
- **THEN** Playwright tests are not included in the Codeception run

### Requirement: Isolated Core Database For E2E
The E2E workflow SHALL execute Core-facing tests against a dedicated Core database separate from the default local development database.

#### Scenario: E2E run uses dedicated Core DB
- **WHEN** E2E tests are started through `make e2e`
- **THEN** the Core E2E runtime is configured with `DATABASE_URL` bound to `ai_community_platform_test`
- **AND** the default Core runtime database (`ai_community_platform`) is not used for E2E mutations

### Requirement: Migration-First E2E Execution
The E2E workflow SHALL apply all service migrations to the dedicated E2E databases before executing tests.

#### Scenario: All migrations are applied before E2E test run
- **WHEN** `make e2e` (or `make e2e-prepare`) is executed
- **THEN** Core Doctrine migrations run against `ai_community_platform_test`
- **AND** Knowledge Agent Doctrine migrations run against `knowledge_agent_test`
- **AND** News-Maker Agent Alembic migrations run against `news_maker_agent_test`
- **AND** all migrations complete before Codecept/Playwright execution

#### Scenario: E2E stops on migration failure
- **WHEN** any E2E DB provisioning or migration fails
- **THEN** the E2E command exits non-zero
- **AND** no E2E test scenarios are started

## ADDED Requirements

### Requirement: Full-Stack E2E Isolation
The E2E workflow SHALL provide isolated duplicates of ALL application services (Core, agents, OpenClaw gateway) that connect to test data stores.

#### Scenario: Each agent has an E2E duplicate container
- **WHEN** `make e2e-prepare` is executed
- **THEN** E2E containers start for core-e2e, knowledge-agent-e2e, knowledge-worker-e2e, news-maker-agent-e2e, hello-agent-e2e, and openclaw-gateway-e2e
- **AND** each E2E container uses test databases, test Redis DB numbers, test OpenSearch indices, and test RabbitMQ vhost

#### Scenario: E2E agents are accessible on dedicated ports
- **WHEN** E2E containers are running
- **THEN** core-e2e is reachable at port 18080
- **AND** knowledge-agent-e2e is reachable at port 18083
- **AND** news-maker-agent-e2e is reachable at port 18084
- **AND** hello-agent-e2e is reachable at port 18085
- **AND** openclaw-gateway-e2e is reachable at port 28789

#### Scenario: A2A messages stay within E2E graph
- **WHEN** core-e2e sends an A2A message to an agent
- **THEN** the message is routed via openclaw-gateway-e2e to an agent-e2e container
- **AND** the agent-e2e container reads from and writes to test data stores

### Requirement: Resource Naming Convention
All infrastructure resources SHALL follow a predictable naming convention to distinguish production and test data.

#### Scenario: Postgres databases use _test suffix
- **WHEN** an agent declares a Postgres database named `{agent_name}`
- **THEN** the platform provisions both `{agent_name}` (prod) and `{agent_name}_test` (E2E)

#### Scenario: Redis uses even/odd DB numbering
- **WHEN** an agent is assigned Redis DB number N (even)
- **THEN** the E2E duplicate uses Redis DB number N+1 (odd)
- **AND** Redis DB 0 is reserved for Core prod, DB 1 for Core test

#### Scenario: OpenSearch indices use _test suffix
- **WHEN** an agent declares an OpenSearch index named `{index_name}`
- **THEN** the E2E duplicate uses index `{index_name}_test`

#### Scenario: RabbitMQ uses separate vhost
- **WHEN** prod services use the default RabbitMQ vhost `/`
- **THEN** E2E services use the `/test` vhost

### Requirement: Zero Agent-Side E2E Code
Agent developers SHALL NOT need to write any test-infrastructure code to support E2E testing. All isolation is handled by the platform via Docker Compose environment overrides.

#### Scenario: Agent reads standard environment variables
- **WHEN** an agent reads `DATABASE_URL`, `REDIS_URL`, `OPENSEARCH_INDEX`, or `RABBITMQ_URL`
- **THEN** the agent connects to whatever resource the environment specifies
- **AND** the agent has no awareness of whether it is running in prod or E2E mode

#### Scenario: Adding a new agent to E2E
- **WHEN** a new agent is added to the platform
- **THEN** the platform operator adds an E2E service definition with `profiles: [e2e]` to the agent's compose file with test resource environment overrides
- **AND** no changes are needed in the agent's source code

### Requirement: Unified Database Provisioning
Postgres init scripts SHALL provision all roles, databases (prod and test) for all services on first startup.

#### Scenario: Fresh postgres start provisions everything
- **WHEN** a developer starts the stack with a clean Postgres volume
- **THEN** all agent roles are created (knowledge_agent, news_maker_agent)
- **AND** all prod databases are created (ai_community_platform, knowledge_agent, news_maker_agent, litellm)
- **AND** all test databases are created (ai_community_platform_test, knowledge_agent_test, news_maker_agent_test)
