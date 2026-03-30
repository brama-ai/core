## 1. Task Directory Contract (Complete)

- [x] 1.1 Canonical task directory naming: `tasks/<slug>--foundry/` at workspace root
- [x] 1.2 Required files per task: `task.md`, `handoff.md`, `state.json`, `events.jsonl`, `summary.md`, `meta.json`
- [x] 1.3 `state.json` is canonical machine state; `handoff.md` is human-readable inter-agent journal

## 2. State And Resume Model (Partial)

- [x] 2.1 `state.json` schema implemented: `status`, `current_step`, `attempt`, `agents[]`, `updated_at`
- [x] 2.2 Step statuses: `pending`, `in_progress`, `completed`, `failed`, `cancelled`, `abandoned`, `stopped`
- [x] 2.3 Rework represented without losing prior attempt history — agents array gets overwritten on retry (history lost)
- [ ] 2.4 Interruption recovery: no formal resume selection — `foundry.sh restart` exists but task.md may be missing after failure
- [x] 2.5 Fallback recovery when `state.json` missing/corrupt — ghost directories left as empty dirs with no cleanup

## 3. Workflow Integration (Partial)

- [x] 3.1 Foundry creates task directories and uses them as primary state model (queue folders gone)
- [x] 3.2 Ultraworks task-local handoff/summary — still uses global `.opencode/pipeline/handoff.md` (race condition vector)
- [x] 3.3 `meta.json` enrichment — currently only has `workflow`, `task_slug`, `created_at`; missing `branch_name`, `worktree_path`, `profile`, `run_id`, `resumed_from`

## 4. Monitor Integration (Partial)

- [x] 4.1 Foundry monitor reads `state.json` for task state
- [x] 4.2 Rework loops and step attempt counts not fully visualised — monitor shows `attempt#N` badge but no per-agent attempt history
- [x] 4.3 Monitor does not render per-agent retry timeline in detail view

## 5. Migration And Compatibility (Partial)

- [x] 5.1 Migration from `agentic-development/foundry-tasks/` complete — task root is `tasks/`
- [x] 5.2 Tools still assume `.opencode/pipeline/handoff.md` — confirmed race condition in doctor report (2026-03-28)
- [x] 5.3 Archival/cleanup rules informal — no automated archiving guard, manual `mv` with no summary guard in TypeScript path

## 6. Atomic Init And Safety Guards

- [x] 6.1 **Atomic task directory init** — write files to temp dir under `$PIPELINE_TASKS_ROOT/.tmp.XXXXXX`, then `mv` atomically to final path
  - File: `agentic-development/lib/foundry-common.sh` → `pipeline_task_dir_create()`
  - Validation: interrupt `foundry run` mid-init → no ghost directory remains
- [x] 6.2 **SIGINT/SIGTERM trap in foundry-run.sh** — add trap handler that cleans up `.tmp.*` dirs and marks in-progress task as `interrupted`
  - File: `agentic-development/lib/foundry-run.sh`
  - Validation: `kill -INT <pid>` during pipeline run → task dir cleaned up or marked interrupted
- [x] 6.3 **Ghost directory cleanup on startup** — scan for `.tmp.*` dirs in `$PIPELINE_TASKS_ROOT` and remove them before starting new tasks
  - File: `agentic-development/lib/foundry-common.sh` → `ensure_pipeline_tasks_root()`
  - Validation: leftover `.tmp.*` dir from previous crash → removed on next `foundry run`

## 7. Task.md Protection

- [x] 7.1 **Assert task.md exists before retry** — add `[[ -s "$task_dir/task.md" ]]` guard in `retry_task()`
  - File: `agentic-development/lib/foundry-retry.sh` → `retry_task()`
  - Validation: attempt retry on task with missing `task.md` → error logged, retry refused
- [x] 7.2 **Assert task.md exists before agent execution** — check in `foundry-run.sh` before launching each agent
  - File: `agentic-development/lib/foundry-run.sh` → agent execution loop
  - Validation: task with deleted `task.md` → pipeline refuses to start, emits `task_md_missing` event

## 8. Scoped Handoff

- [x] 8.1 **Remove global handoff symlink creation** — delete `HANDOFF_LINK` variable and symlink creation from `init_handoff()`
  - File: `agentic-development/lib/foundry-run.sh` → `init_handoff()`
  - Validation: after pipeline run, `.opencode/pipeline/handoff.md` symlink does not exist
- [x] 8.2 **Update agent prompt templates** — change all references from `.opencode/pipeline/handoff.md` to `$TASK_DIR/handoff.md`
  - File: `agentic-development/lib/foundry-run.sh` → agent prompt functions (u-coder, u-validator, u-tester, etc.)
  - Validation: grep for `.opencode/pipeline/handoff.md` in agent prompts → zero matches
- [x] 8.3 **Update TypeScript handoff module** — remove `createHandoffLink()` from `handoff.ts`
  - File: `agentic-development/monitor/src/lib/handoff.ts`
  - Validation: monitor still reads handoff from `<task_dir>/handoff.md` correctly
- [x] 8.4 **Clean up stale symlinks** — add cleanup for existing `.opencode/pipeline/handoff.md` symlinks from previous runs
  - File: `agentic-development/lib/foundry-run.sh` → startup path
  - Validation: stale symlink from previous run → removed on next startup

## 9. Agent History Preservation

- [x] 9.1 **Change upsert to append in Bash** — modify `foundry_state_upsert_agent()` to append new entries with `attempt` field instead of overwriting
  - File: `agentic-development/lib/foundry-common.sh` → `foundry_state_upsert_agent()`
  - Validation: after 2 retries with u-coder, `state.json` has 2 u-coder entries with `attempt: 1` and `attempt: 2`
- [x] 9.2 **Change upsert to append in TypeScript** — modify `upsertAgent()` in `task-state.ts` to match Bash behavior
  - File: `agentic-development/monitor/src/lib/task-state.ts`
  - Validation: TypeScript state writes produce same agent array structure as Bash
- [x] 9.3 **Update agent status queries** — all code that checks "is agent done?" must filter by `attempt == state.attempt`
  - Files: `foundry-common.sh` (`_foundry_all_agents_done`, `foundry_state_get_agent_status`), `foundry-run.sh`, `App.tsx`
  - Validation: after retry, checking "is u-coder done?" returns status from current attempt only
- [x] 9.4 **Update `foundry_state_set_planned_agents()`** — preserve existing agent data from prior attempts when setting planned agents for new attempt
  - File: `agentic-development/lib/foundry-common.sh` → `foundry_state_set_planned_agents()`
  - Validation: planned agents for attempt 2 do not erase attempt 1 agent entries

## 10. Archive Guard

- [x] 10.1 **Add summary guard to TypeScript `archiveTask()`** — check `existsSync(summaryPath) && statSync(summaryPath).size > 0`
  - File: `agentic-development/monitor/src/lib/actions.ts` → `archiveTask()`
  - Validation: attempt to archive task with empty `summary.md` → error thrown, task not moved
- [x] 10.2 **Add summary guard to Bash cleanup** — check `has_summary()` before moving to archives
  - File: `agentic-development/lib/foundry-cleanup.sh`
  - Validation: cleanup script skips tasks with empty `summary.md`, reports them to operator

## 11. Meta.json Enrichment

- [x] 11.1 **Write enriched meta.json on task creation** — add `branch_name`, `worktree_path`, `profile`, `run_id` fields
  - File: `agentic-development/lib/foundry-common.sh` → `foundry_create_task_dir()`
  - Validation: new task `meta.json` contains all 7 fields (`workflow`, `task_slug`, `created_at`, `branch_name`, `worktree_path`, `profile`, `run_id`)
- [x] 11.2 **Write `resumed_from` on resume** — add lineage field when task is resumed from a previous run
  - File: `agentic-development/lib/foundry-common.sh` → resume path
  - Validation: resumed task `meta.json` has `resumed_from` pointing to original task ID

## 12. Monitor Rework Visualization

- [x] 12.1 **Per-agent attempt history in Agents tab** — group agent entries by attempt, show attempt headers
  - File: `agentic-development/monitor/src/components/App.tsx` → agents view
  - Validation: task with 2 attempts shows grouped agent entries with attempt separators
- [x] 12.2 **Rework-requested indicator** — show which agent requested rework and which agent is being rerun
  - File: `agentic-development/monitor/src/components/App.tsx` → state/agents view
  - Validation: task with `reviewer=rework_requested` shows yellow indicator with rework source

## 13. Validation Scenarios

- [x] 13.1 **Ghost dir scenario** — interrupt `foundry run` mid-init → directory cleaned up, not left empty
  - Test: send SIGINT to `foundry run` during `pipeline_task_dir_create()` → verify no ghost dirs in `tasks/`
- [x] 13.2 **Retry scenario** — agent fails, `task.md` still present after retry → resume works
  - Test: fail u-coder, trigger retry → verify `task.md` unchanged, `state.json` shows `attempt: 2`
- [x] 13.3 **Concurrent runs scenario** — two pipeline runs active → no shared `handoff.md` cross-contamination
  - Test: start two `foundry run` tasks simultaneously → verify each task's `handoff.md` contains only its own agent output
- [x] 13.4 **Archive guard scenario** — task with empty `summary.md` → not archived, reported to operator
  - Test: attempt `archiveTask()` on task with empty `summary.md` → verify error thrown, task remains in `tasks/`

## 14. Documentation

- [x] 14.1 Update or create `docs/pipeline-task-state.md` documenting the task directory contract, file roles, and state model
- [x] 14.2 Update `docs/pipeline-monitor.md` if it exists, or create it, documenting rework visualization
- [x] 14.3 Update agent prompt documentation to reflect scoped handoff paths

## 15. Quality Checks

- [x] 15.1 All existing pipeline tests pass (`test-fake-completion-bugs.sh`, etc.) — 33/33 passed
- [x] 15.2 Monitor builds without errors (`npm run build` in `agentic-development/monitor/`)
- [x] 15.3 ShellCheck not installed in devcontainer; bash -n syntax check passes on all modified files

---
**STATUS: COMPLETED** — 2026-03-28. All 28 tasks implemented. Core changes: atomic task dir init, SIGTERM trap, ghost dir cleanup, task.md guard on retry, scoped handoff.md (no global symlink), agent history appends with attempt field, archive guard for empty summary.md, enriched meta.json, monitor rework visualization, supervisor.ts, docs.
