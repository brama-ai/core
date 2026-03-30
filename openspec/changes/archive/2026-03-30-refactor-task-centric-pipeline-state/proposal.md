# Change: Unify Pipeline Runtime State Around Task Directories

## Why

The current pipeline runtime scatters task state across multiple unrelated locations:
- Foundry queue folders such as `agentic-development/foundry-tasks/todo/`, `done/`, `failed/`, and `summary/`
- global `.opencode/pipeline/handoff.md` symlink that can only point to one task at a time
- latest-summary lookups instead of task-scoped summary artifacts
- task worktree metadata tracked separately from human-readable run state

This makes resume behavior fragile, monitor views harder to reason about, and Ultraworks continuity especially confusing when a task is interrupted or sent back for rework.

The team wants one task = one directory, where operators can always find the task description, handoff, state, event history, and final summary together.

### Current State (as of 2026-03-28)

The initial task-centric layout is **partially implemented** (8 of 28 tasks complete):

| Area | Status | Notes |
|------|--------|-------|
| Task directory naming | Done | `tasks/<slug>--foundry/` at workspace root |
| Required files per task | Done | `task.md`, `handoff.md`, `state.json`, `events.jsonl`, `summary.md`, `meta.json` |
| `state.json` as canonical state | Done | Machine-readable status, current step, agents array |
| Foundry task directory creation | Done | `foundry_create_task_dir()` with collision avoidance |
| Atomic task claiming | Done | `flock`-based claiming in `foundry_claim_task()` |
| `meta.json` creation | Done | Workflow, task_slug, created_at |
| Monitor reads `state.json` | Done | TypeScript TUI reads task-local state |
| Migration from queue folders | Done | `tasks/` is the primary root |

**20 tasks remain**, covering:
- Atomic directory init with SIGINT/SIGTERM cleanup (ghost dir prevention)
- `task.md` protection on retry (already preserved, needs formal guard)
- Scoped `handoff.md` — remove singular global symlink race condition
- Agent history preservation on retry (currently overwritten by upsert)
- Archive guard requiring non-empty `summary.md`
- `meta.json` enrichment (branch, worktree, profile, run ID)
- Monitor rework visualization improvements
- 4 validation scenarios

## What Changes

- **Atomic task directory init** — write files to temp dir, `mv` atomically; add SIGINT/SIGTERM trap to `foundry-run.sh` to clean up incomplete dirs on interrupt
- **Protect `task.md` from deletion on retry** — add explicit guard in foundry cleanup so `task.md` survives failed runs; add assertion in retry path
- **Scoped `handoff.md`** — agents read/write `<task_dir>/handoff.md` directly instead of through a singular global symlink; remove `.opencode/pipeline/handoff.md` symlink as the primary agent path
- **Preserve agent history on retry** — append to `agents[]` with `attempt` field per agent entry instead of upserting (overwriting) previous run data
- **Archive guard** — only archive when `summary.md` exists and is non-empty (`test -s`); report incomplete tasks instead of silently archiving; enforce in both Bash (`foundry-runner.sh`) and TypeScript (`archiveTask()`)
- **`meta.json` enrichment** — add `branch_name`, `worktree_path`, `profile`, `run_id`, `resumed_from` lineage fields
- **Monitor: rework visualisation** — show per-agent attempt history, retry timeline, and rework-requested indicators in TUI detail view
- **4 validation scenarios** — ghost dir cleanup, task.md survival, concurrent handoff isolation, archive guard for empty summary

## Impact

- Affected specs:
  - `pipeline-agents` (MODIFIED: scoped handoff, agent history)
  - `pipeline-monitor` (ADDED: rework visualization detail)
  - `pipeline-task-state` (ADDED: atomic init, archive guard, meta.json enrichment, agent history model)
- Affected code:
  - `agentic-development/lib/foundry-common.sh` — `pipeline_task_dir_create()`, `foundry_state_upsert_agent()`, `foundry_create_task_dir()`
  - `agentic-development/lib/foundry-run.sh` — `init_handoff()`, agent prompt templates, SIGINT trap
  - `agentic-development/lib/foundry-retry.sh` — retry path guards
  - `agentic-development/lib/foundry-runner.sh` — autonomous runner retry logic
  - `agentic-development/lib/foundry-cleanup.sh` — archival guard
  - `agentic-development/monitor/src/lib/actions.ts` — `archiveTask()` summary guard
  - `agentic-development/monitor/src/lib/task-state.ts` — agent upsert → append
  - `agentic-development/monitor/src/components/App.tsx` — rework visualization
  - `agentic-development/monitor/src/lib/handoff.ts` — symlink removal
  - Agent prompt templates that reference `.opencode/pipeline/handoff.md`
- Architectural impact: yes — changes how both workflows persist runtime state, how agents discover handoff context, and how resume/monitoring work
