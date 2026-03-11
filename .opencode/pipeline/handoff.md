# Pipeline Handoff

- **Task**: implement change add-marketplace-stale-agent-cleanup
- **Started**: 2026-03-11 10:43:29
- **Branch**: pipeline/implement-change-add-marketplace-stale-agent-clean
- **Pipeline ID**: 20260311_104327

---

## Architect

- **Status**: pending
- **Change ID**: —
- **Apps affected**: —
- **DB changes**: —
- **API changes**: —

## Coder

- **Status**: done
- **Files modified**:
  - `apps/core/src/AgentRegistry/AgentRegistryInterface.php` — added `deleteStaleMarketplaceAgents(int $failureThreshold): int` method signature
  - `apps/core/src/AgentRegistry/AgentRegistryRepository.php` — implemented `deleteStaleMarketplaceAgents()`: fetches stale agents, deletes each, inserts `stale_deleted` audit entries, invalidates cache
  - `apps/core/src/Command/AgentHealthPollerCommand.php` — added `STALE_THRESHOLD = 5` constant; calls `deleteStaleMarketplaceAgents()` after poll loop
  - `apps/core/tests/Unit/AgentRegistry/AgentRegistryRepositoryTest.php` — added 3 unit tests (deletes eligible, skips installed, preserves below threshold)
  - `apps/core/tests/Functional/Command/AgentHealthPollerCommandCest.php` — new functional test: inserts stale agent, runs command, asserts deletion and audit entry
  - `docs/agent-requirements/agent-state-model.md` — added section 4 documenting stale marketplace agent cleanup behavior
  - `openspec/changes/add-marketplace-stale-agent-cleanup/tasks.md` — all tasks marked `[x]`
- **Migrations created**: none (no schema changes required)
- **Deviations**:
  - Audit logging is done directly via `Connection::executeStatement` inside the repository (not via `AgentRegistryAuditLogger`) to avoid adding a new constructor dependency to the repository. The SQL is identical to what `AgentRegistryAuditLogger::log()` executes.
  - The implementation uses a SELECT-then-DELETE loop (per agent) rather than a single bulk DELETE, to enable per-agent audit logging as required by the spec.

## Validator

- **Status**: done
- **PHPStan**:
  - `apps/core/`: pass
- **CS-check**:
  - `apps/core/`: pass
- **Files fixed**: none

## Tester

- **Status**: pending
- **Test results**: —
- **New tests written**: —

## Documenter

- **Status**: pending
- **Docs created/updated**: —

---

- **Commit (coder)**: b3fe118
