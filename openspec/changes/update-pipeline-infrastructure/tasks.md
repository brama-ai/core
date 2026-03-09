# Tasks: Update Pipeline Infrastructure

## Group 1: Worktree Isolation

- [x] 1.1 Rewrite `run_sequential()` to create a single worktree instead of direct checkout
- [x] 1.2 Add `trap EXIT` cleanup handler for sequential mode
- [x] 1.3 Add `rm -rf` fallback to parallel mode worktree cleanup
- [x] 1.4 Add `git checkout "$ORIGINAL_BRANCH"` to parallel cleanup handler

## Group 2: Crash Recovery

- [x] 2.1 Detect non-main branch at startup and auto-recover (stash + checkout)
- [x] 2.2 Prune stale worktree refs at startup (`git worktree prune`)
- [x] 2.3 Remove leftover `.pipeline-worktrees/` directory at startup

## Group 3: Git Lock Contention

- [x] 3.1 Add retry loop (5 attempts, backoff) for `git checkout -b` in `pipeline.sh`
- [x] 3.2 Handle both existing and new branch cases in retry logic

## Group 4: Ink TUI Monitor

- [x] 4.1 Create `scripts/monitor/` with `package.json` (ink 5, react 18)
- [x] 4.2 Implement data helpers: `buildTaskList()`, `getPriority()`, `setPriority()`
- [x] 4.3 Implement process helpers: `getBatchPid()`, `getBatchElapsed()`, `detectWorkers()`
- [x] 4.4 Implement actions: `actionStart()`, `actionKill()`, `actionRetryFailed()`
- [x] 4.5 Implement UI components: App, ProgressBar, StatusCards, TaskLine, TabBar, BottomMenu
- [x] 4.6 Implement keyboard navigation (arrows, tabs, detail view)
- [x] 4.7 Fix zombie PID detection (check process stat for Z/T)
- [x] 4.8 Fix elapsed time display (parse `ps -o etime=` instead of `lstart`)
- [x] 4.9 Change priority keys from p/d to +/- with cursor following task
- [x] 4.10 Create wrapper script `scripts/pipeline-monitor-ink.sh`

## Group 5: Auto-Fix Retry

- [x] 5.1 Add `--auto-fix` flag and `MAX_AUTO_FIX_RETRIES` to batch script
- [x] 5.2 Implement `auto_fix_and_retry()` function with AI analysis
- [x] 5.3 Integrate auto-fix into `move_to_failed()` flow
