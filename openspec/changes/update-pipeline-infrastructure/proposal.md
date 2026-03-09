# Proposal: Update Pipeline Infrastructure

## Why

The pipeline batch runner has critical reliability issues:

1. **Branch pollution**: Sequential mode (1 worker) runs `git checkout` directly in the main repo. If the pipeline crashes, the repo stays on a task branch instead of `main`, corrupting the working state for subsequent runs and manual work.
2. **No crash recovery**: After a crash, stale worktrees and orphan branches are left behind. The next run doesn't detect or clean up these artifacts.
3. **No TUI monitor**: The bash-based monitor (`pipeline-monitor.sh`) flickers due to `clear`+redraw and has broken escape sequence handling. There's no modern, stable monitoring tool.
4. **Zombie process blocking**: The monitor detects batch PIDs via `pgrep` but doesn't verify if the process is actually alive (zombie/orphan processes block new starts).

## What Changes

### Modified Capability: `pipeline-batch`

- **Worktree isolation for all modes**: Sequential mode (1 worker) now creates a single git worktree, identical to parallel mode. The main repo's branch is never switched during pipeline execution.
- **Crash recovery at startup**: On start, detect if repo is on a non-main branch (crashed previous run), auto-stash + checkout main, prune stale worktrees, and clean up `.pipeline-worktrees/`.
- **Cleanup traps**: Both sequential and parallel modes register `trap EXIT` handlers that restore the original branch and remove worktrees, even on signals/crashes.
- **Git lock contention retry**: Branch creation retries up to 5 times with backoff for parallel worker lock contention.

### New Capability: `pipeline-monitor` (Ink TUI)

- React/Ink-based terminal UI (`scripts/monitor/`) replacing the bash monitor.
- Features: task list with priority sorting, progress bar, elapsed time, batch start/kill/retry actions, detail view, keyboard navigation.
- Zombie PID detection (checks process stat, filters Z/T states).
- Priority change with `+`/`-` keys, cursor follows task after reorder.

### Modified Capability: `pipeline` (single task runner)

- Git branch creation with retry loop (5 attempts, exponential backoff) for lock contention in parallel worktree mode.

## Impact

- **Modified**: `scripts/pipeline-batch.sh` — worktree isolation, crash recovery, cleanup
- **Modified**: `scripts/pipeline.sh` — git retry logic
- **Modified**: `scripts/pipeline-monitor.sh` — buffer rendering fixes
- **New**: `scripts/monitor/` — Ink TUI monitor (index.js, package.json)
- **New**: `scripts/pipeline-monitor-ink.sh` — wrapper script
- **No breaking changes** to pipeline task format or API
