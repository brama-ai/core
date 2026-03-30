# Change: Fix PSR-4 naming, multi-tenant registration, and E2E boot pipeline

## Why
After the A2A terminology refactoring (`refactor-a2a-terminology`) and multi-tenant migration (`add-tenant-management`), the core application fails to boot due to four PSR-4 class/file mismatches in `src/A2AGateway/`. Additionally, the internal agent registration API and E2E pipeline are broken because they do not account for multi-tenant context, and PostgreSQL lacks the `pgvector` extension required by `news-maker-agent` migrations.

## What Changes
- **PSR-4 fix**: Rename 4 source files and 2 test files so class names match file names (PSR-4 autoloading requirement)
- **pgvector**: Switch PostgreSQL image from `postgres:16-alpine` to `pgvector/pgvector:pg16`; add init script for `vector` extension
- **Multi-tenant registration**: `AgentRegistrationController` falls back to default tenant when no tenant context is set (internal API path)
- **E2E Makefile**: Update SQL statements to include `tenant_id` for `agent_registry` and `scheduled_jobs`
- **E2E test fixes**: Fix `Scenario.skip()` syntax error, use env vars for agent URLs, re-tag non-smoke tests

## Impact
- Affected specs: `local-dev-runtime`, `agent-registry`, `e2e-testing`
- Affected code:
  - `apps/brama-core/src/A2AGateway/` (4 file renames)
  - `apps/brama-core/tests/Unit/A2AGateway/` (2 file renames)
  - `apps/brama-core/src/Controller/Api/Internal/AgentRegistrationController.php`
  - `apps/brama-core/config/packages/security.yaml` (no change — reverted)
  - `compose.yaml` (postgres image)
  - `docker/postgres/init/04_create_extensions.sql` (new)
  - `Makefile` (tenant-aware SQL)
  - `tests/e2e/tests/` (4 test files)
