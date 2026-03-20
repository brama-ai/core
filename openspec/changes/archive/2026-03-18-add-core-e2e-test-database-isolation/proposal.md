# Change: Add Core E2E Test Database Isolation

> **SUPERSEDED** by [`refactor-e2e-test-isolation`](../refactor-e2e-test-isolation/proposal.md).
> This proposal covered Core-only isolation. The replacement provides full-stack isolation
> for all services (Core, agents, OpenClaw) using Docker Compose `profiles: [e2e]`.

## Why
Current E2E tests run against the same Core database used for day-to-day local development. Admin E2E scenarios mutate settings and can overwrite operator configuration.

We need a repeatable test path where Core uses a dedicated E2E database and migrations are always applied before E2E starts.

## What Changes
- Add a dedicated Core E2E database (`ai_community_platform_e2e`) alongside the default development database.
- Add a dedicated Core E2E runtime surface (separate service/runtime config) bound to the E2E database.
- Update E2E run flow so migrations are executed against the E2E database before test execution.
- Ensure both browser and REST/console Codecept suites (e.g. `a2a_bridge_test`) target the Core E2E runtime.
- Fail fast when E2E database provisioning/migrations fail.
- Document operator workflow for isolated E2E execution.

## Impact
- Affected specs: `e2e-testing`, `local-dev-runtime`
- Affected code:
  - Compose topology and environment wiring for Core E2E runtime
  - Make targets for E2E DB prepare + migration-before-test
  - E2E run scripts/docs
- Non-goal in this change: isolation of agent-owned databases (`knowledge-agent`, `news-maker-agent`) and their admin-side mutable state.
