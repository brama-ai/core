# Change: Archive Completed OpenSpec Changes and Synchronize ROADMAP

## Why

Eight OpenSpec changes have reached 100% task completion but remain in the active `changes/` directory, cluttering `openspec list` output and making it harder to distinguish active work from completed work. The ROADMAP.md is also out of sync: it lists stale task counts for several active changes and does not reflect the completion of these eight changes. Archiving completed changes and updating the ROADMAP restores the project's single-source-of-truth invariant for both OpenSpec state and roadmap progress.

## What Changes

### 1. Archive 8 Completed Changes

Move each completed change directory from `openspec/changes/<id>/` to `openspec/changes/archive/2026-03-30-<id>/`:

| Change ID | Tasks | Notes |
|-----------|-------|-------|
| `add-k3s-storage-architecture` | 28/28 | Storage architecture for k3s stateful services |
| `add-kubernetes-agent-discovery` | 52/52 | Kubernetes-native agent discovery provider |
| `complete-admin-agent-registry` | 17/17 | Final quality gates for admin agent registry |
| `fix-psr4-multitenant-e2e-boot` | 23/23 | PSR-4 naming, multi-tenant registration, E2E boot |
| `fix-remaining-e2e-failures` | 13/13 | Remaining E2E test failure fixes |
| `refactor-foundry-runtime-entrypoint` | 19/19 | Consolidated Foundry runtime entrypoint |
| `validate-k3s-local-runtime` | 18/18 | Local k3s runtime end-to-end validation |
| `refactor-task-centric-pipeline-state` | 45/46 | Task-centric pipeline state (STATUS: COMPLETED in tasks.md) |

### 2. Update ROADMAP.md

- Move archived items from "In Progress" to "Completed" section
- Update task counts for remaining active changes to match actual `tasks.md` progress
- Add newly visible changes that are missing from ROADMAP

### 3. Post-Archive Validation

- Run `openspec validate --strict` to confirm no broken references
- Verify `openspec list` shows only active (non-archived) changes

## Impact

- Affected specs: none (no spec content changes, only directory moves)
- Affected files:
  - `openspec/changes/` — 8 directories moved to `archive/`
  - `ROADMAP.md` — completed items moved, task counts updated
- No code changes
- No breaking changes
- No database migrations
