## Context
Foundry is currently a brand-layer rename over a runtime still organized around legacy `pipeline*` scripts and `tasks/` directories. The user wants the runtime model itself simplified so operators interact with one executable, one task root, and one monitoring surface.

This is a cross-cutting workspace-tooling change that affects task lifecycle storage, monitor behavior, command dispatch, and backward compatibility for existing docs and automation.

## Goals
- Make `agentic-development/foundry.sh` the single human-facing entrypoint for sequential Foundry work
- Keep interactive monitoring always available through the same executable
- Allow background/headless execution without losing visibility in the interactive monitor
- Remove the need to remember multiple standalone helper scripts for common Foundry operations
- Migrate visible naming from generic `pipeline`/`tasks` to `Foundry`/`foundry-tasks`

## Non-Goals
- Do not rename the Ultraworks runtime or its launcher model in this change
- Do not require immediate removal of all legacy scripts if compatibility wrappers are needed
- Do not redesign OpenCode's built-in `build` agent semantics

## Proposed Runtime Model

### 1. Single Foundry CLI
`agentic-development/foundry.sh` becomes the top-level command with these behaviors:

- `foundry.sh`
  - Opens the interactive monitor/status UI
- `foundry.sh headless`
  - Starts or resumes background Foundry processing without attaching the interactive UI
- `foundry.sh command <name> [args...]`
  - Runs one of the built-in Foundry commands
- `foundry.sh <command> [args...]`
  - Shorthand for direct command invocation in headless/non-interactive usage where unambiguous

### 2. Unified Task Root
Foundry state moves from:

- `agentic-development/tasks/`

to:

- `agentic-development/foundry-tasks/`

The internal lifecycle directories remain conceptually the same:

- `todo/`
- `in-progress/`
- `done/`
- `failed/`
- `suspended/`
- `summary/`
- `artifacts/`
- `archive/`

Compatibility wrappers or migrations should preserve discoverability of existing task files during rollout.

### 3. Embedded Monitor
The current `monitor/pipeline-monitor.sh` behavior is absorbed into `foundry.sh` default mode.

The monitor remains responsible for:
- overview/status display
- worker tabs
- task lifecycle actions
- log views
- manual start/retry/archive controls

### 4. Headless Worker Mode
`foundry.sh headless` runs the worker logic in background mode and updates the same shared Foundry state that the interactive monitor reads.

The operator can later run plain `foundry.sh` and inspect:
- current queue state
- active workers
- logs
- task details
- retry/stop controls

### 5. Built-in Commands
Current standalone operational entrypoints should become internal Foundry commands where practical, for example:
- monitoring/status actions
- batch/task processing actions
- retry helpers
- stats/report helpers

The exact command menu can be finalized during implementation, but the spec should require:
- a discoverable command list in the UI
- a CLI form for running the same commands without navigating the TUI first

## Backward Compatibility
- Legacy scripts may remain as thin wrappers during migration
- Wrappers should print a deprecation hint pointing to `foundry.sh`
- Existing automation must continue to work for one migration window unless explicitly broken by an approved follow-up change

## Risks
- Many scripts and prompt files currently hardcode `tasks/` paths
- Monitor behavior and worker behavior are currently split; naive merging may regress auto-start, lock handling, or task lifecycle moves
- Existing summaries and artifacts use historical `b-` naming; path migration must avoid orphaning prior runs

## Open Questions / Assumptions
- Assumption: `foundry-tasks/` renames only the Foundry sequential task store, not Ultraworks-specific task queues
- Assumption: `headless` should be idempotent and safe to run while the interactive monitor is not attached
- Assumption: direct CLI commands can coexist with the TUI without creating a second incompatible command surface
