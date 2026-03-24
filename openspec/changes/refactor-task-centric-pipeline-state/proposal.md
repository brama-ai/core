# Change: Unify Pipeline Runtime State Around Task Directories

## Why

The current pipeline runtime scatters task state across multiple unrelated locations:
- Foundry queue folders such as `agentic-development/foundry-tasks/todo/`, `done/`, `failed/`, and `summary/`
- global `.opencode/pipeline/handoff.md`
- latest-summary lookups instead of task-scoped summary artifacts
- task worktree metadata tracked separately from human-readable run state

This makes resume behavior fragile, monitor views harder to reason about, and Ultraworks continuity especially confusing when a task is interrupted or sent back for rework.

The team wants one task = one directory, where operators can always find the task description, handoff, state, event history, and final summary together.

## What Changes

- Introduce a canonical task-centric runtime layout under `tasks/`
- Store each task run in its own directory named `<task-slug>--<workflow>/`
- Move task-scoped runtime artifacts into that directory:
  - `task.md`
  - `handoff.md`
  - `state.json`
  - `events.jsonl`
  - `summary.md`
  - `meta.json`
- Make `state.json` the canonical source of truth for monitor and resume decisions
- Keep `handoff.md` as the human-readable inter-agent journal, not the primary machine state
- Define explicit step status and attempt tracking so rework loops can be visualized without losing history
- Define interruption and resume rules for both Foundry and Ultraworks using task-local state
- Update monitor/runtime docs to describe the new task-directory contract

## Impact

- Affected specs:
  - `pipeline-agents`
  - `pipeline-monitor`
  - `pipeline-task-state` (new)
- Affected code:
  - `agentic-development/foundry.sh`
  - `agentic-development/pipeline.sh`
  - `agentic-development/pipeline-batch.sh`
  - `agentic-development/monitor/pipeline-monitor.sh`
  - `agentic-development/monitor/ultraworks-monitor.sh`
  - `agentic-development/postmortem-summary.sh`
  - `agentic-development/normalize-summary.py`
  - prompt/docs that still assume global `handoff.md` or queue/status folders
- Architectural impact: yes, this changes how both workflows persist runtime state and how resume/monitoring work
