# Change: Isolate Ultraworks Runs With Task Worktrees

## Why
`Ultraworks` currently launches in the shared repository checkout by default. Parallel sessions therefore contend for the same working tree, `.opencode/pipeline/handoff.md`, plan files, logs, and reports. This makes concurrent runs unsafe and causes state corruption between unrelated tasks.

The team needs `Ultraworks` to behave like an isolated worker runtime: each task should run on its own branch, inside its own git worktree, with task-scoped pipeline state. Parallel execution should be safe by default rather than a manual workaround.

## What Changes
- Make `Ultraworks` launch and headless modes create a dedicated git worktree per task by default.
- Require a dedicated branch per task, using the `pipeline/<task-slug>` naming pattern unless overridden.
- Ensure `.opencode/pipeline/*` state is isolated per task through the worktree rather than shared in the repository root.
- Define cleanup and preservation rules: successful runs may remove the worktree automatically, failed runs should preserve it for debugging unless configured otherwise.
- Update the monitor/runtime contract so operators can locate the active worktree path and branch for each run.
- Update `Ultraworks` documentation to remove the current “shared working directory” default assumption.

## Impact
- Affected specs: `pipeline-monitor`, `pipeline-agents`
- Affected code: `builder/monitor/ultraworks-monitor.sh`, any Ultraworks launcher wrappers, monitor/runtime metadata, docs under `docs/agent-development/` and `brama-core/docs/features/`
- Architectural impact: yes, this changes the default execution isolation model of the Ultraworks runtime
