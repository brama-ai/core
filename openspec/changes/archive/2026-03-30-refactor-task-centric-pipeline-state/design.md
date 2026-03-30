# Design: Task-Centric Pipeline Runtime State

## Context

Foundry and Ultraworks currently model runtime continuity in different and partly incompatible ways:

- Foundry uses queue/status folders plus summary/artifact subtrees
- Ultraworks relies on task worktrees but still reads global-looking pipeline continuity files inside each checkout
- monitors infer state from markdown, mtimes, and folder placement

This works for simple happy paths but becomes weak when:
- a task is interrupted mid-step
- reviewer/auditor sends work back for rework
- operators need to inspect one task holistically
- multiple workflows should expose a similar operator mental model

## Goals

- Make one task directory the primary operator-facing runtime unit
- Keep all task-local artifacts together in one place
- Make resume deterministic from machine-readable state
- Preserve a readable handoff journal for humans and agents
- Support rework loops without overwriting historical progress
- Let monitors render state without parsing ad hoc markdown conventions

## Non-Goals

- Replacing git worktree isolation for Ultraworks
- Removing workflow-specific behavior such as Foundry queue polling or Ultraworks parallel phases
- Designing a database-backed scheduler in this change

## Canonical Layout

Each task run lives under:

```text
tasks/<task-slug>--<workflow>/
```

Where `<workflow>` is one of:
- `foundry`
- `ultraworks`

Each task directory contains:

```text
task.md
handoff.md
state.json
events.jsonl
summary.md
meta.json
artifacts/
  telemetry/
  checkpoint.json
  u-<agent>/
    result.json
```

### File Roles

- `task.md`
  - original task prompt or normalized task description
  - **immutable after creation** — MUST NOT be deleted or modified by retry, cleanup, or archival
- `handoff.md`
  - readable inter-agent journal
  - may be used by explicitly allowed roles such as summarizer
  - not the primary resume source
  - **task-scoped** — agents read/write directly from `<task_dir>/handoff.md`
- `state.json`
  - canonical machine-readable state
  - source of truth for monitor, status, and resume
- `events.jsonl`
  - append-only event stream for history and diagnostics
- `summary.md`
  - final or best-effort summary artifact for that specific task
  - **archival gate** — task MUST NOT be archived if `summary.md` is empty or missing
- `meta.json`
  - immutable or slow-changing metadata such as workflow, created time, branch, worktree, run id, resumed-from lineage

## Decisions

### D1: Atomic Task Directory Init

**Decision:** Write all initial task files to a temp directory, then `mv` atomically to the final path. Add SIGINT/SIGTERM trap to `foundry-run.sh` to clean up incomplete directories.

**Rationale:** Currently `pipeline_task_dir_create()` uses `mkdir -p` followed by individual file writes. If the process is killed between `mkdir` and file creation, a ghost directory remains with missing files. The `flock`-based claiming in `foundry_claim_task()` only protects the claim transition, not the initial creation.

**Implementation approach:**
1. Create temp dir: `mktemp -d "${PIPELINE_TASKS_ROOT}/.tmp.XXXXXX"`
2. Write all files (`task.md`, `state.json`, `meta.json`, `handoff.md`, `events.jsonl`, `summary.md`) into temp dir
3. `mv` temp dir to final path atomically (same filesystem, so `rename(2)` is atomic)
4. Add `trap cleanup_ghost_dirs SIGINT SIGTERM EXIT` to `foundry-run.sh` that removes any `.tmp.*` dirs in `PIPELINE_TASKS_ROOT`

**Alternatives considered:**
- Sentinel file approach (write `.init-complete` as last step, check on startup) — rejected because it still leaves ghost dirs that need periodic cleanup
- Two-phase commit with lock file — over-engineered for filesystem operations

### D2: Protect `task.md` From Deletion on Retry

**Decision:** Add explicit guard in retry and cleanup paths that asserts `task.md` exists and is non-empty before proceeding.

**Rationale:** Investigation confirms `task.md` is already preserved on retry (never deleted). However, there is no formal assertion — if a future code change accidentally removes it, the failure would be silent. Adding an explicit guard makes the contract enforceable.

**Implementation approach:**
1. In `foundry-retry.sh` `retry_task()`: assert `[[ -s "$task_dir/task.md" ]]` before resetting status
2. In `foundry-run.sh` before agent execution: assert `task.md` exists
3. If assertion fails: log error, emit `task_md_missing` event, refuse to proceed

### D3: Scoped Handoff — Remove Global Symlink

**Decision:** Agents read/write `<task_dir>/handoff.md` directly. The global symlink at `.opencode/pipeline/handoff.md` is removed as the primary agent path.

**Rationale:** The current design creates a symlink `.opencode/pipeline/handoff.md` → `<task_dir>/handoff.md`. This is a race condition vector when multiple tasks run in the same workspace (e.g., `foundry-batch.sh` without worktree isolation). The symlink can only point to one task at a time.

**Implementation approach:**
1. Agent prompt templates in `foundry-run.sh` already know `$TASK_DIR` — change handoff references from `.opencode/pipeline/handoff.md` to `$TASK_DIR/handoff.md`
2. `init_handoff()` writes directly to `$TASK_DIR/handoff.md` without creating the symlink
3. Remove `HANDOFF_LINK` variable and symlink creation from `foundry-run.sh`
4. Monitor already reads from `<task_dir>/handoff.md` directly — no change needed
5. Keep `.opencode/pipeline/` directory for other pipeline state (e.g., `pipeline-plan.json`) but not for handoff

**Migration:** The symlink may still exist from previous runs. Cleanup code should remove stale symlinks but not fail if they're absent.

**Alternatives considered:**
- Per-task symlink directory (`.opencode/pipeline/tasks/<slug>/handoff.md`) — adds indirection without benefit since agents already have `$TASK_DIR`
- Keep symlink as convenience alias — rejected because it's the root cause of the race condition

### D4: Preserve Agent History on Retry

**Decision:** Change `foundry_state_upsert_agent()` from upsert (overwrite) to append with `attempt` field per agent entry.

**Rationale:** Currently, when a task retries, the `agents[]` array in `state.json` is upserted — each agent name maps to exactly one entry, and the previous run's telemetry is overwritten. This means operators cannot diagnose what happened in earlier attempts.

**Implementation approach:**
1. Each agent entry in `agents[]` gets an `attempt` field matching the task-level attempt counter at the time of execution
2. On retry, new agent entries are **appended** with the new attempt number instead of overwriting existing entries
3. Queries for "current agent status" filter by `attempt == state.attempt`
4. Monitor and runner logic that checks "is agent done?" filters by current attempt
5. `events.jsonl` already preserves full history — `state.json` now mirrors this for structured queries

**Schema change for `agents[]` entries:**
```json
{
  "agent": "u-coder",
  "attempt": 2,
  "status": "done",
  "model": "anthropic/claude-sonnet-4-6",
  "duration_seconds": 45,
  "started_at": "2026-03-28T10:00:00Z",
  "completed_at": "2026-03-28T10:00:45Z"
}
```

**Alternatives considered:**
- Separate `history[]` array alongside `agents[]` — adds complexity; single array with attempt filter is simpler
- Only rely on `events.jsonl` for history — not structured enough for monitor rendering

### D5: Archive Guard — Non-Empty Summary Required

**Decision:** Both Bash and TypeScript archival paths MUST check `summary.md` is non-empty before archiving.

**Rationale:** `foundry-runner.sh` already uses `[[ -s "$1/summary.md" ]]` via `has_summary()`, but the TypeScript `archiveTask()` in the monitor only checks `summarizer.status === "done"` without verifying the file content. A task with a `done` summarizer but empty `summary.md` (e.g., summarizer crashed after status update but before writing) can be incorrectly archived.

**Implementation approach:**
1. `archiveTask()` in `actions.ts`: add `existsSync(summaryPath) && statSync(summaryPath).size > 0` check
2. `foundry-cleanup.sh`: add `has_summary()` check before moving to archives
3. If guard fails: throw error with message "Cannot archive task: summary.md is empty or missing"
4. Emit `archive_blocked` event to `events.jsonl`

### D6: `meta.json` Enrichment

**Decision:** Expand `meta.json` to include `branch_name`, `worktree_path`, `profile`, `run_id`, and `resumed_from` fields.

**Rationale:** Currently `meta.json` contains only `workflow`, `task_slug`, and `created_at`. The monitor already reads `branch_name` and `worktree_path` from `meta.json` for Ultraworks tasks, but Foundry tasks store branch in `state.json`. Unifying metadata in `meta.json` simplifies the monitor and enables lineage tracking for resumed tasks.

**Implementation approach:**
1. `foundry_create_task_dir()`: write enriched `meta.json` with all fields
2. On resume: add `resumed_from` field pointing to the original task ID
3. Monitor reads metadata from `meta.json` uniformly for both workflows
4. `state.json` retains `branch_name` for backward compatibility but `meta.json` is authoritative

**Schema:**
```json
{
  "workflow": "foundry",
  "task_slug": "add-streaming-support",
  "created_at": "2026-03-28T10:00:00Z",
  "branch_name": "foundry/add-streaming-support",
  "worktree_path": null,
  "profile": "full",
  "run_id": "f7a3b2c1",
  "resumed_from": null
}
```

### D7: Monitor Rework Visualization

**Decision:** Enhance the monitor TUI detail view to show per-agent attempt history, retry timeline, and rework-requested indicators.

**Rationale:** The monitor already shows `attempt#N` badge and `↻N` loop counter. With D4 (agent history preservation), the monitor can now render a timeline showing each agent's execution across attempts, making rework patterns visible.

**Implementation approach:**
1. Agents tab: group agent entries by attempt, show attempt headers
2. State tab: show per-agent attempt count alongside status
3. Timeline view: render attempt boundaries with visual separators
4. Color coding: `rework_requested` in yellow, failed attempts in red, current attempt in green

## Risks / Trade-offs

- **`state.json` growth** — appending agent history instead of upserting means `state.json` grows with each retry. Mitigated by: typical tasks have 1-3 retries with 5-8 agents each, so growth is bounded (~50 entries max).
- **Backward compatibility** — removing the global handoff symlink breaks any external tool that reads `.opencode/pipeline/handoff.md`. Mitigated by: no known external consumers; internal agent prompts are updated in the same change.
- **Atomic `mv` assumption** — `mv` is atomic only on the same filesystem. Mitigated by: temp dir is created in the same `PIPELINE_TASKS_ROOT` directory, guaranteeing same filesystem.
- **Monitor query complexity** — filtering `agents[]` by attempt adds complexity to monitor queries. Mitigated by: simple `jq` filter `select(.attempt == .state.attempt)`.

## Migration Plan

1. **Phase 1 (this change):** Implement all 20 pending tasks in the existing codebase
2. **Phase 2 (cleanup):** Remove stale symlinks and legacy references in a follow-up PR
3. **No data migration needed:** Existing task directories are forward-compatible; new fields in `meta.json` and `state.json` are additive

## Open Questions

None — all design decisions are resolved based on investigation of current implementation state.
