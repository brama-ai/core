## 1. Runtime Entrypoint
- [ ] 1.1 Create `agentic-development/foundry.sh` as the canonical Foundry entrypoint
- [ ] 1.2 Implement default interactive monitor mode when `foundry.sh` is run without arguments
- [ ] 1.3 Implement `foundry.sh headless` to start or resume background Foundry worker execution
- [ ] 1.4 Implement command dispatch so Foundry operations can run through `foundry.sh command <name>` and direct command args

## 2. Task Store Migration
- [ ] 2.1 Rename the Foundry lifecycle root from `agentic-development/tasks/` to `agentic-development/foundry-tasks/`
- [ ] 2.2 Update runtime scripts, summaries, reports, telemetry, and monitor views to read/write the new task root
- [ ] 2.3 Define migration/compatibility behavior for existing task files and historical artifacts

## 3. Monitor Consolidation
- [ ] 3.1 Move the current interactive monitor behavior under `foundry.sh`
- [ ] 3.2 Preserve worker tabs, task lifecycle controls, logs, and overview/status behaviors
- [ ] 3.3 Ensure interactive mode can inspect headless worker activity without requiring a separate launcher

## 4. Legacy Script Compatibility
- [ ] 4.1 Convert legacy `pipeline.sh`, `pipeline-batch.sh`, and monitor entrypoints into wrappers or delegated internal commands where needed
- [ ] 4.2 Surface deprecation guidance from legacy entrypoints to `foundry.sh`
- [ ] 4.3 Update any user-facing prompts or command docs that still instruct operators to invoke the legacy entrypoints directly

## 5. Validation
- [ ] 5.1 Add or update shell tests for the new Foundry entrypoint and task-root behavior
- [ ] 5.2 Verify the interactive monitor still supports the current critical task actions and worker visibility
- [ ] 5.3 Verify headless mode can be started first and later observed through interactive Foundry mode

## 6. Documentation
- [ ] 6.1 Update Foundry workflow docs under `docs/agent-development/`
- [ ] 6.2 Update any prompt-facing references in `.opencode/` that describe sequential runtime entrypoints or task paths
- [ ] 6.3 Update migration notes for operators who still use legacy `pipeline*` commands
