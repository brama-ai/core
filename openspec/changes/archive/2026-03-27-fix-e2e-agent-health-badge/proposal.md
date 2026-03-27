# Change: Fix E2E Agent Health Badge Not Showing After Discovery

## Why
E2E tests `hello_agent_test.js` and `news_maker_admin_test.js` fail because agents registered via the internal API (`/api/v1/internal/agents/register`) have `health_status = 'unknown'` in the database. The admin template only renders `badge-healthy` or `badge-degraded` when `health_status` matches those exact values. The health poller (`app:agent-health-poll`) skips agents whose manifest lacks a `health_url` field, and the E2E registration payloads do not include `health_url`. This creates a permanent `badge-unknown` state that E2E tests cannot assert against.

## What Changes
- **E2E registration payloads** (Makefile `e2e-register-agents` and in-test `Before` hooks) SHALL include `health_url` pointing to the E2E agent container's health endpoint (Docker DNS name, not localhost)
- **E2E preparation** SHALL run a single health poll cycle after agent registration so that `health_status` is set to `healthy` or `degraded` before tests execute
- **`register()` method** in `AgentRegistryRepository` SHALL perform an inline health probe when the manifest contains `health_url`, setting `health_status` immediately instead of leaving it as `unknown`
- **Spec delta** for `agent-registry`: document that registration with `health_url` triggers an immediate health probe
- **Spec delta** for `e2e-testing`: document that E2E agent registration must include `health_url` and that health status must be resolved before test execution

## Impact
- Affected specs: `e2e-testing`, `agent-registry`
- Affected code:
  - `Makefile` — `e2e-register-agents` target (add `health_url` to payloads, add health poll step)
  - `brama-core/src/src/AgentRegistry/AgentRegistryRepository.php` — `register()` method (inline health probe)
  - `brama-core/src/src/Controller/Api/Internal/AgentRegistrationController.php` — trigger health probe after registration
  - `brama-core/tests/e2e/tests/admin/news_maker_admin_test.js` — add `health_url` to Before hook registration
  - `brama-core/tests/e2e/tests/admin/knowledge_admin_test.js` — add `health_url` to Before hook registration
- No database migrations needed
- No API surface changes (existing endpoints, new optional field in registration payload)
