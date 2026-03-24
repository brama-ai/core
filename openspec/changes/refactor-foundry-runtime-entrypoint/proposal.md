# Change: Consolidate Foundry Runtime Into a Single Entrypoint

## Why
The current sequential pipeline runtime is split across multiple scripts and folders: `pipeline.sh`, `pipeline-batch.sh`, `monitor/pipeline-monitor.sh`, and `tasks/`. That makes the user-facing workflow harder to learn, harder to monitor, and inconsistent with the new `Foundry` naming.

## What Changes
- Introduce `agentic-development/foundry.sh` as the canonical entrypoint for the sequential Foundry runtime
- Rename the Foundry task lifecycle root from `agentic-development/tasks/` to `agentic-development/foundry-tasks/`
- Define `foundry.sh` operating modes:
  - no args: open interactive monitor UI
  - `headless`: start or continue background Foundry worker execution
  - `command <name>` or direct command args: run built-in runtime commands without separate wrapper scripts
- Fold current monitor and task-runner entrypoints under `foundry.sh` while preserving compatibility shims for legacy scripts during migration
- Update documentation and prompt-facing workflow references from legacy pipeline naming to Foundry naming

## Impact
- Affected specs:
  - `pipeline-monitor`
  - `pipeline-agents`
  - `foundry-runtime` (new)
- Affected code:
  - `agentic-development/foundry.sh` (new)
  - `agentic-development/pipeline.sh`
  - `agentic-development/pipeline-batch.sh`
  - `agentic-development/monitor/pipeline-monitor.sh`
  - `agentic-development/tasks/` and related tooling references
  - workflow docs and `.opencode` command prompts
