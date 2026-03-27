# pipeline-env-checker Specification

## Purpose
TBD - created by archiving change add-environment-checker. Update Purpose after archive.
## Requirements
### Requirement: Environment Prerequisites Validation

The builder pipeline SHALL validate environment prerequisites before any pipeline agent starts execution. The validation MUST cover global infrastructure services, language runtimes, package managers, and per-app dependencies.

#### Scenario: All prerequisites pass in healthy devcontainer

- **GIVEN** a devcontainer with PostgreSQL 16, Redis 7, PHP 8.5, Python 3.12, Node 22, Composer, and npm installed and running
- **WHEN** the environment checker runs with no `--app` flags
- **THEN** all global checks pass
- **AND** the exit code is 0
- **AND** a JSON report is written to `.opencode/pipeline/env-report.json` with all checks having status "pass"

#### Scenario: PostgreSQL is unavailable

- **GIVEN** PostgreSQL is not running or not accepting connections
- **WHEN** the environment checker runs
- **THEN** the PostgreSQL check has status "fail"
- **AND** the exit code is 2 (fatal)
- **AND** the detail message includes actionable guidance (e.g., "run 'make up' or 'pg_isready' to diagnose")

#### Scenario: Redis is unavailable

- **GIVEN** Redis is not running or not responding to PING
- **WHEN** the environment checker runs
- **THEN** the Redis check has status "fail"
- **AND** the exit code is 2 (fatal)

#### Scenario: PHP version below minimum

- **GIVEN** PHP is installed but version is below 8.5
- **WHEN** the environment checker runs for an app requiring PHP
- **THEN** the PHP version check has status "fail"
- **AND** the exit code is 2 (fatal)
- **AND** the detail message includes the detected version and the required minimum

#### Scenario: Missing PHP extension

- **GIVEN** PHP is installed but the `pdo_pgsql` extension is not loaded
- **WHEN** the environment checker runs with `--app core`
- **THEN** the PHP extension check for `pdo_pgsql` has status "fail"
- **AND** the exit code is 2 (fatal)

### Requirement: Per-App Dependency Scoping

The environment checker SHALL support per-app requirement scoping via `--app <name>` flags. When app flags are provided, only the global checks plus the specified apps' requirements MUST be validated.

#### Scenario: Check only PHP apps

- **GIVEN** the checker is invoked with `--app core --app knowledge-agent`
- **WHEN** the checks execute
- **THEN** global checks (PostgreSQL, Redis, git, jq) are validated
- **AND** PHP runtime, Composer, and PHP extensions for core and knowledge-agent are validated
- **AND** Python and Node.js checks are NOT executed

#### Scenario: Check Python app

- **GIVEN** the checker is invoked with `--app news-maker-agent`
- **WHEN** the checks execute
- **THEN** global checks are validated
- **AND** Python runtime (>= 3.12) and pip are validated
- **AND** PHP and Node.js checks are NOT executed

#### Scenario: No app flags runs global checks only

- **GIVEN** the checker is invoked without any `--app` flags
- **WHEN** the checks execute
- **THEN** only global checks (services and common tools) are validated
- **AND** no app-specific runtime or dependency checks are executed

### Requirement: Machine-Readable Report Output

The environment checker SHALL produce a JSON report containing the timestamp, exit code, summary, duration, per-check results, and detected environment versions.

#### Scenario: JSON report written to default path

- **GIVEN** the checker completes all checks
- **WHEN** the `--report-file` flag is not specified
- **THEN** the JSON report is written to `.opencode/pipeline/env-report.json`
- **AND** the report is valid JSON parseable by `jq`

#### Scenario: JSON report written to custom path

- **GIVEN** the checker is invoked with `--report-file /tmp/env.json`
- **WHEN** the checks complete
- **THEN** the JSON report is written to `/tmp/env.json`

#### Scenario: JSON output to stdout

- **GIVEN** the checker is invoked with `--json` flag
- **WHEN** the checks complete
- **THEN** the JSON report is printed to stdout instead of the human-readable summary
- **AND** the report file is still written to the report-file path

### Requirement: Three-Tier Exit Code Contract

The environment checker SHALL use exit codes to communicate overall status: 0 for all checks passing, 1 for non-fatal warnings, and 2 for fatal failures that MUST block pipeline execution.

#### Scenario: Exit code 0 on full success

- **GIVEN** all checks pass
- **WHEN** the checker exits
- **THEN** the exit code is 0

#### Scenario: Exit code 1 on non-fatal warnings

- **GIVEN** all required checks pass but Docker daemon is not running
- **WHEN** the checker exits
- **THEN** the exit code is 1
- **AND** the summary indicates warnings are present

#### Scenario: Exit code 2 on fatal failure

- **GIVEN** PostgreSQL is not available and the task requires database access
- **WHEN** the checker exits
- **THEN** the exit code is 2
- **AND** the pipeline MUST NOT proceed to agent execution

### Requirement: Pipeline Pre-Flight Integration

The builder pipeline (`builder/pipeline.sh`) SHALL invoke the environment checker as a pre-flight gate after the existing `preflight()` function and before branch setup. Fatal failures MUST cancel the task immediately.

#### Scenario: Pipeline cancels on fatal env failure

- **GIVEN** the environment checker exits with code 2
- **WHEN** the pipeline processes the exit code
- **THEN** the pipeline emits an `ENV_FATAL` event
- **AND** the task file is moved to `failed/` with env failure metadata
- **AND** the pipeline exits with code 3
- **AND** no pipeline agents are started

#### Scenario: Pipeline continues with warnings

- **GIVEN** the environment checker exits with code 1
- **WHEN** the pipeline processes the exit code
- **THEN** the pipeline emits an `ENV_WARN` event
- **AND** warnings are written to the handoff.md Environment section
- **AND** the pipeline continues to branch setup and agent execution

#### Scenario: Pipeline writes environment info to handoff on success

- **GIVEN** the environment checker exits with code 0
- **WHEN** the pipeline processes the exit code
- **THEN** detected runtime versions (PHP, Python, Node, etc.) are written to the handoff.md `## Environment` section
- **AND** downstream agents can read this section to know available tools

#### Scenario: Skip env check with flag

- **GIVEN** the pipeline is invoked with `--skip-env-check`
- **WHEN** the pipeline starts
- **THEN** the environment checker is not executed
- **AND** the pipeline proceeds directly to branch setup

### Requirement: Standalone Execution

The environment checker MUST be independently runnable outside the pipeline context for developer self-service diagnostics.

#### Scenario: Developer runs checker manually

- **GIVEN** a developer is in the repository root
- **WHEN** they run `./builder/env-check.sh`
- **THEN** global environment checks execute and results are printed to stdout
- **AND** the script does not depend on pipeline state or handoff files

#### Scenario: Developer checks specific app

- **GIVEN** a developer wants to verify the core app environment
- **WHEN** they run `./builder/env-check.sh --app core`
- **THEN** global checks plus core-specific checks (PHP, Composer, extensions) execute
- **AND** results are printed with pass/warn/fail indicators

#### Scenario: Help flag shows usage

- **GIVEN** a developer runs `./builder/env-check.sh --help`
- **THEN** usage information is printed including all available flags and exit code meanings

### Requirement: Per-App Requirement Registry

The system SHALL maintain a declarative JSON registry (`builder/env-requirements.json`) that maps app names to their environment prerequisites including runtime, minimum version, required tools, extensions, and dependency check commands.

#### Scenario: Registry contains all platform apps

- **GIVEN** the registry file exists
- **THEN** it contains entries for core, knowledge-agent, dev-reporter-agent, news-maker-agent, and wiki-agent
- **AND** each entry specifies runtime, min_version, and tools

#### Scenario: Adding a new app to the registry

- **GIVEN** a new agent app is added to the platform
- **WHEN** a developer adds an entry to `builder/env-requirements.json`
- **THEN** the environment checker automatically validates that app's prerequisites when `--app <new-app>` is passed
- **AND** no changes to `env-check.sh` are required

### Requirement: Monitor Environment Status Display

The pipeline monitor SHALL display a compact environment status summary in the Overview tab, sourced from the environment checker's JSON report.

#### Scenario: Healthy environment display

- **GIVEN** the env report shows all checks passing
- **WHEN** the monitor renders the Overview tab
- **THEN** a status line shows detected versions and total check count (e.g., "Env: PHP 8.5 | Python 3.12 | Node 22 | PG | Redis | 12/12 checks")

#### Scenario: Failed environment display

- **GIVEN** the env report shows fatal failures
- **WHEN** the monitor renders the Overview tab
- **THEN** a status line shows failure summary with failed check names (e.g., "Env: FAILED - PostgreSQL not available (1 fatal)")

