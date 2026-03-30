# Tasks: sync-roadmap-with-openspec-state

## 1. Fix Incorrect Task Counts

- [x] 1.1 Update `close-a2a-trace-quality-gates` from `2/11 tasks` to `0/11 tasks` in ROADMAP.md line 39
- [x] 1.2 Update `archive-completed-openspec-changes` from `in progress` (no count) to `0/26 tasks` in ROADMAP.md line 48

## 2. Update Date (if needed)

- [x] 2.1 Verify "Last updated" date is `March 30, 2026`; update if stale

## 3. Verification

- [x] 3.1 Run `openspec list` and cross-reference every active change against ROADMAP.md — confirm all are present
- [x] 3.2 For each "In Progress" and "Planned" item with an openspec change ID, verify the task count matches the actual `tasks.md` checkbox count
- [x] 3.3 Confirm no roadmap items reference change IDs that do not exist in `openspec/changes/` or `openspec/changes/archive/`
