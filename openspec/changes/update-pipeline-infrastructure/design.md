# Design: Update Pipeline Infrastructure

## Worktree Isolation (All Modes)

### Problem

Sequential mode ran `git -C "$REPO_ROOT" checkout -b "$branch"` directly in the main repo. On crash, the repo stayed on the task branch.

### Solution

Both sequential (1 worker) and parallel (N workers) modes now create worktrees under `.pipeline-worktrees/`. The `pipeline.sh` script runs inside the worktree, so `$REPO_ROOT` resolves to the worktree path, and `git checkout` only affects the worktree.

```
Main repo (always on main)
├── .pipeline-worktrees/
│   ├── worker-1/   ← worktree, detached HEAD → task branch
│   └── worker-2/   ← worktree, detached HEAD → task branch (parallel only)
```

### Crash Recovery

At startup, `pipeline-batch.sh`:
1. Checks if `ORIGINAL_BRANCH != main` → stash + checkout main
2. Runs `git worktree prune` to clean stale refs
3. Removes leftover `.pipeline-worktrees/` directory
4. Registers `trap EXIT` for cleanup on any termination

## Ink TUI Monitor

### Tech Stack

- **Ink 5** (React for CLI) — same stack as Claude Code
- **React 18** — component model, hooks for state
- Node.js ESM module

### Architecture

```
App (main component)
├── State: tab, selectedIdx, tick, detailFile, actionMsg
├── Data: buildTaskList(), detectWorkers(), getBatchPid()
├── useInput() — keyboard handler
├── useEffect() — 3s auto-refresh
│
├── Overview tab
│   ├── ProgressBar
│   ├── StatusCards (todo/in-progress/done/failed counts)
│   ├── TaskLine[] (selectable, color-coded by state)
│   └── BottomMenu (context-sensitive keys)
│
├── Worker tabs (when worktrees exist)
│   └── WorkerTab (log tail from worker directory)
│
└── Detail view (on Enter)
    └── TaskDetail (full file content, log tail)
```

### Key Bindings

| Key | Context | Action |
|-----|---------|--------|
| ↑/↓ | Overview | Navigate task list |
| ←/→ | Any | Switch tabs |
| Enter | Overview | Open task detail |
| Esc | Detail | Close detail |
| +/- | Todo task | Change priority (cursor follows) |
| s | Any | Start batch |
| k | Any | Kill batch |
| f | Any | Retry failed tasks |
| x | In-progress | Stop task |
| q | Any | Quit |

## Git Lock Contention

Parallel workers share the same `.git` directory. Branch operations can fail with `index.lock` errors. Solution: retry loop with exponential backoff (1s, 2s, 3s, 4s, 5s).
