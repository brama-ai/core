## 1. Runtime Entrypoint
- [x] 1.1 Create `agentic-development/foundry.sh` as the canonical Foundry entrypoint
- [x] 1.2 Implement default interactive monitor mode when `foundry.sh` is run without arguments
- [x] 1.3 Implement `foundry.sh headless` to start or resume background Foundry worker execution
- [x] 1.4 Implement command dispatch so Foundry operations can run through `foundry.sh command <name>` and direct command args

## 2. Task Store Migration
- [x] 2.1 Task root migrated to `tasks/` at workspace root (not `agentic-development/foundry-tasks/`)
- [x] 2.2 Runtime scripts, monitor, and telemetry read/write `tasks/` via `PIPELINE_TASKS_ROOT`
- [x] 2.3 Migration complete — `agentic-development/tasks/` removed, archives under `tasks/archives/`

## 3. Monitor Consolidation
- [x] 3.1 Interactive monitor accessible via `foundry.sh` (no-args → TUI)
- [x] 3.2 Worker tabs, task lifecycle controls, logs, and status views preserved
- [x] 3.3 Interactive mode inspects headless worker activity without separate launcher

## 4. Legacy Script Compatibility
- [x] 4.1 Legacy `pipeline.sh`, `pipeline-batch.sh` fully removed — `foundry.sh` is the only entrypoint
- [x] 4.2 No legacy entrypoints remain
- [x] 4.3 All user-facing prompts and `.opencode/` commands reference `foundry.sh`

## 5. Validation
- [x] 5.1 Tests exist in `agentic-development/tests/`
- [x] 5.2 Interactive monitor supports task actions and worker visibility
- [x] 5.3 Headless mode can be started and observed through interactive mode

## 6. Documentation
- [x] 6.1 Foundry workflow docs updated under `docs/agent-development/`
- [x] 6.2 `.opencode/` prompt-facing references updated
- [x] 6.3 Migration notes in `agentic-development/MIGRATION.md`

---

> **Status: COMPLETED** — 2026-03-28. All goals achieved.
> `foundry.sh` is the single entrypoint. Task store is `tasks/`. All legacy pipeline scripts removed.
> Remaining gaps (ghost dir on interrupted init, shared handoff.md race) tracked in `refactor-task-centric-pipeline-state`.
