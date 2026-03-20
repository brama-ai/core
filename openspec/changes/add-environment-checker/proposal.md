# Change: Add environment prerequisites checker to builder pipeline

## Why

Pipeline tasks currently fail silently or produce low-quality results when environment prerequisites are missing (PostgreSQL down, Redis unavailable, PHP extensions missing, etc.). The `add-deep-crawling` task demonstrated this problem: validator and tester agents were stuck as "pending (Docker not available)" because the pipeline had no pre-flight environment validation. Tasks should be cancelled immediately with a clear error message rather than wasting agent compute on a broken environment.

## What Changes

- **New script `builder/env-check.sh`**: A standalone bash environment checker that validates all prerequisites before any pipeline agent starts. Checks PostgreSQL, Redis, PHP (>= 8.5), Python (>= 3.12), Node.js (>= 20), Composer, npm, key PHP extensions, and per-app dependency status.
- **Pipeline integration**: `builder/pipeline.sh` gains a new `env_check()` step in the pre-flight phase (after existing `preflight()`, before branch setup). On fatal failure (exit 2), the task is cancelled and moved to `failed/` with a clear diagnostic.
- **Monitor integration**: `builder/monitor/pipeline-monitor.sh` displays environment status from the checker's JSON report in the Overview tab.
- **JSON report format**: The checker produces a machine-readable JSON report at `.opencode/pipeline/env-report.json` plus a human-readable summary on stdout.
- **Per-app requirement registry**: A declarative `builder/env-requirements.json` file maps app names to their specific prerequisites, enabling the checker to validate only what a task needs.
- **Handoff enrichment**: On success, the checker writes an `## Environment` section to `handoff.md` so downstream agents know what tools and versions are available.

## Impact

- Affected specs: `pipeline-monitor` (minor — display env status), new spec `pipeline-env-checker`
- Affected code:
  - `builder/env-check.sh` (new)
  - `builder/env-requirements.json` (new)
  - `builder/pipeline.sh` (modified — add env_check integration)
  - `builder/monitor/pipeline-monitor.sh` (modified — display env status)
  - `.opencode/pipeline/handoff-template.md` (modified — add Environment section placeholder)
