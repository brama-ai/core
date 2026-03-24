## 1. Runtime Contract
- [ ] 1.1 Define the default worktree and branch lifecycle for each Ultraworks task
- [ ] 1.2 Define success/failure cleanup behavior and operator-visible preservation rules
- [ ] 1.3 Define how task-scoped pipeline state is located from the active worktree

## 2. Ultraworks Launcher
- [ ] 2.1 Update `ultraworks-monitor.sh launch` to create and use a dedicated worktree by default for task runs
- [ ] 2.2 Update `ultraworks-monitor.sh headless` to create and use a dedicated worktree by default
- [ ] 2.3 Persist branch name and worktree path in runtime metadata/logs so monitoring and summaries can reference them
- [ ] 2.4 Preserve failed worktrees for debugging and provide cleanup guidance

## 3. Monitor And Safety
- [ ] 3.1 Ensure monitor/status commands can identify the task worktree and branch for a running Ultraworks session
- [ ] 3.2 Ensure concurrent Ultraworks runs do not share `handoff.md`, `plan.json`, reports, or logs
- [ ] 3.3 Add regression coverage for slug generation and worktree path handling where feasible

## 4. Documentation
- [ ] 4.1 Update Ultraworks operator docs to describe default isolated execution
- [ ] 4.2 Update pipeline docs to clarify the difference between Builder worker worktrees and Ultraworks task worktrees

## 5. Verification
- [ ] 5.1 Validate the OpenSpec change with `openspec validate add-ultraworks-worktree-isolation --strict`
- [ ] 5.2 Verify two parallel Ultraworks runs keep separate `handoff.md`, logs, and git changes
- [ ] 5.3 Verify a successful run leaves a reviewable branch and a failed run preserves its worktree path for inspection
