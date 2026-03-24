## 1. Task Directory Contract

- [ ] 1.1 Define the canonical task directory naming convention under `tasks/`
- [ ] 1.2 Define required files for each task directory: `task.md`, `handoff.md`, `state.json`, `events.jsonl`, `summary.md`, `meta.json`
- [ ] 1.3 Define which file is canonical for machine state vs human-readable continuity

## 2. State And Resume Model

- [ ] 2.1 Define the `state.json` schema for workflow, current step, overall status, attempts, and timestamps
- [ ] 2.2 Define the allowed per-step statuses and transitions
- [ ] 2.3 Define how rework is represented without losing prior attempt history
- [ ] 2.4 Define how interruption recovery and resume selection work
- [ ] 2.5 Define fallback recovery behavior when `state.json` is missing or corrupted

## 3. Workflow Integration

- [ ] 3.1 Define how Foundry creates and processes task directories instead of queue/status folders as the primary state model
- [ ] 3.2 Define how Ultraworks writes task-local handoff, summary, and machine state inside the task directory
- [ ] 3.3 Define how worktree/session metadata is persisted in `meta.json`

## 4. Monitor Integration

- [ ] 4.1 Define how Foundry and Ultraworks monitors read task state from `state.json`
- [ ] 4.2 Define how monitors render rework loops and step attempt counts
- [ ] 4.3 Define how monitors link to or open `handoff.md` and `summary.md` for the active task

## 5. Migration And Compatibility

- [ ] 5.1 Define migration from `agentic-development/foundry-tasks/` and global summary scanning to task-centric directories
- [ ] 5.2 Define compatibility behavior for tools that still assume `.opencode/pipeline/handoff.md`
- [ ] 5.3 Define archival/cleanup rules for completed task directories

## 6. Validation

- [ ] 6.1 Add or update specs for pipeline task state, agents, and monitor behavior
- [ ] 6.2 Update Foundry and Ultraworks documentation to describe the new layout and resume contract
- [ ] 6.3 Verify interrupted runs, rework loops, and completed summaries can all be recovered from task-local state
