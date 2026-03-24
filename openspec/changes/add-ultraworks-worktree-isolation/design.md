# Design: Ultraworks Task Isolation

## Context
`Ultraworks` is currently launched from `builder/monitor/ultraworks-monitor.sh`, which starts `opencode run --command auto ...` in the repository root. This is safe for one session, but not for concurrent sessions because all pipeline continuity artifacts live under `.opencode/pipeline/` in the same checkout.

Builder batch workers already demonstrate the needed pattern: isolated worktrees avoid filesystem conflicts while keeping shared git object storage.

## Goals
- Make isolated task execution the default for `Ultraworks`.
- Keep branch naming predictable and compatible with existing `pipeline/<slug>` conventions.
- Preserve enough metadata for monitor, summaries, and PR creation.
- Keep failure investigation practical by preserving failed worktrees.

## Non-Goals
- A full scheduler or queue for Ultraworks
- Automatic merge of parallel branches
- Replacing OpenCode orchestration logic

## Proposed Flow
1. User starts `Ultraworks` with a task.
2. Launcher derives a task slug.
3. Launcher creates a new branch and git worktree for that slug.
4. Launcher runs `opencode run --command auto ...` inside that worktree.
5. The run writes to that worktree's `.opencode/pipeline/` directory.
6. The monitor stores and shows branch/worktree metadata.
7. On success, the branch remains available for review and the worktree may be cleaned automatically.
8. On failure, the worktree is preserved by default for inspection.

## Worktree Shape
- Root directory can remain under `.pipeline-worktrees/` to stay aligned with existing conventions.
- Each run should have a unique path, even when slugs collide.
- Branch name should default to `pipeline/<slug>` with a collision-safe suffix when needed.

## Monitor Shape
- The monitor should not assume one global active `.opencode/pipeline/handoff.md` for all sessions.
- For a task run started by the launcher, the monitor should be able to discover and present:
  - worktree path
  - branch name
  - latest task-scoped handoff path
  - latest task-scoped log path

## Cleanup Rules
- Success: worktree may be removed automatically after summaries/metadata are persisted, but branch must remain.
- Failure: worktree should be preserved unless the operator explicitly requests cleanup.
- Stale worktrees should be discoverable for manual cleanup.
