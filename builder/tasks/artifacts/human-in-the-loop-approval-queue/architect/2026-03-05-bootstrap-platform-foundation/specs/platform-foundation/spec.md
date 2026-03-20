## ADDED Requirements

### Requirement: PHP 8.5 Runtime

The platform core MUST run on PHP 8.5. All Docker images and Composer version constraints
SHALL target PHP 8.5 exclusively.

#### Scenario: Docker image reports PHP 8.5

- **WHEN** the `core` container is built and `php -v` is executed inside it
- **THEN** the reported version is 8.5.x

#### Scenario: Composer enforces PHP 8.5

- **WHEN** `composer install` runs inside `apps/core/`
- **THEN** the `^8.5` PHP constraint is satisfied and installation succeeds without version errors

### Requirement: Symfony 7 Application

The platform core SHALL run a Symfony 7 application initialized via Composer Flex in `apps/core/`.

#### Scenario: Symfony kernel boots on HTTP request

- **WHEN** the `core` container receives any HTTP request
- **THEN** the Symfony kernel initializes without errors and the request is routed

#### Scenario: Symfony front controller replaces stub

- **WHEN** `apps/core/public/index.php` is read
- **THEN** it is the Symfony front controller (not the old plain-text stub)

### Requirement: PHPStan Static Analysis

The platform core MUST include PHPStan configured at level 8, enforced on `apps/core/src/`.

#### Scenario: Clean code produces no errors

- **WHEN** `phpstan analyse` runs on `apps/core/src/`
- **THEN** zero errors are reported at level 8

### Requirement: PHP CS Fixer Code Style

The platform core MUST include PHP CS Fixer with a project-defined ruleset applied to
`apps/core/src/`.

#### Scenario: Formatted code passes style check

- **WHEN** `php-cs-fixer check apps/core/src/` runs
- **THEN** no violations are reported

### Requirement: Codeception Test Suite

The platform core MUST include Codeception with unit and functional suites configured.

#### Scenario: Both suites are discovered and run

- **WHEN** `codecept run` executes from `apps/core/`
- **THEN** both unit and functional suites are discovered, executed, and report results without
  configuration errors

### Requirement: Postgres Connection via Doctrine DBAL

The platform core SHALL establish a Postgres connection using Doctrine DBAL on application boot.
No schema or migrations are required at this stage.

#### Scenario: DBAL initializes without errors

- **WHEN** the application boots with a valid `DATABASE_URL` pointing to the local Postgres service
- **THEN** Doctrine DBAL initializes the connection without throwing exceptions

### Requirement: LiteLLM Local Proxy

The local development platform stack SHALL include a `LiteLLM` proxy service for routing and
debugging LLM traffic from the platform and its agents.

#### Scenario: LiteLLM is reachable locally

- **WHEN** the local Docker Compose stack is running
- **THEN** the `LiteLLM` proxy is reachable on `http://localhost:4000/`

#### Scenario: LiteLLM is the standard development entrypoint for LLM calls

- **WHEN** an agent or orchestration component needs to call an LLM in local development
- **THEN** it uses the local `LiteLLM` proxy instead of a direct provider endpoint

### Requirement: LLM-Capable Agents Declare Proxy Usage

Agent-facing product requirements SHALL standardize how LLM-capable agents describe model access.

#### Scenario: Agent requirements declare the LLM gateway contract

- **WHEN** a new agent PRD includes LLM behavior
- **THEN** it declares that requests flow through the platform-owned `LiteLLM` proxy
- **AND** it specifies model aliases, prompt constraints, and safety boundaries
