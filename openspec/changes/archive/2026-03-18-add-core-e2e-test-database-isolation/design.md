## Context
The current E2E entry point (`make e2e`) executes tests against `BASE_URL=http://localhost` and the running default Core runtime. The Core runtime uses `ai_community_platform` by default, so E2E mutations in admin scenarios affect local operator state.

## Goals / Non-Goals
- Goals:
  - Isolate Core E2E writes from the default local development database.
  - Guarantee migration parity by applying Core migrations to E2E DB before every E2E run.
  - Keep developer flow simple (`make e2e` remains a single entry command).
- Non-Goals:
  - Full isolation of non-Core agent databases.
  - Rewriting E2E suite semantics or replacing Codecept/Playwright stack.

## Decisions

### Decision 1: Two Core databases in local Postgres
Use:
- `ai_community_platform` for default local runtime.
- `ai_community_platform_e2e` for E2E runtime.

Rationale: strong data isolation with minimal framework changes, while keeping the same migration set and schema contract.

### Decision 2: Dedicated Core E2E runtime surface
Introduce a dedicated Core runtime variant (`core-e2e`) that is configured with E2E DB URL.

Rationale: avoids hot-swapping `DATABASE_URL` on the shared Core service and prevents race conditions during local development.

### Decision 3: Migration-before-test is mandatory
`make e2e` (or a wrapper it calls) must:
1. Ensure E2E DB exists.
2. Run `doctrine:migrations:migrate --no-interaction` against E2E DB.
3. Run E2E tests only if steps 1-2 succeed.

Rationale: deterministic schema state and lower flake rate.

### Decision 4: Explicit E2E base URL
E2E runner should target the dedicated Core E2E surface via explicit `BASE_URL` in the Make/script flow for both browser and REST/console suites.

Rationale: makes target runtime unambiguous and auditable.

## Risks / Trade-offs
- Additional compose/runtime complexity (extra Core service).
  - Mitigation: keep E2E service in dedicated compose overlay and start only for E2E flow.
- Some E2E scenarios may still mutate non-Core systems.
  - Mitigation: mark this as follow-up; this change isolates Core state first.

## Migration Plan
1. Add E2E DB creation/provisioning command (idempotent).
2. Add Core E2E runtime wiring (compose overlay + env).
3. Add E2E migration prepare target and call it from `make e2e`.
4. Update E2E docs with new execution path and troubleshooting.

## Rollback Plan
- Revert make target changes and compose overlay.
- Stop/remove Core E2E runtime.
- Keep default Core runtime on `ai_community_platform` unchanged.

## Open Questions
- Should `make e2e-smoke` also enforce migration-before-test on E2E DB, or stay read-only against default runtime?
- Should CI always run full E2E against isolated Core E2E runtime by default?
