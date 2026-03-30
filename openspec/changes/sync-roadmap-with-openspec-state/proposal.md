# Change: Sync ROADMAP.md with actual OpenSpec state

## Why

ROADMAP.md has drifted from the actual state of OpenSpec changes. Task counts for some items are incorrect, and one item is missing its task count entirely. This creates confusion about project progress and violates the ROADMAP Synchronization requirement established in `improve-development-workflow`.

## What Changes

- Fix `close-a2a-trace-quality-gates` task count from `2/11` to `0/11` (the "2 checked" were false positives — `[x]` appeared in task description text, not as actual checkboxes)
- Add task count `0/26` to `archive-completed-openspec-changes` (currently shows "in progress" with no count)
- Update "Last updated" date to `2026-03-30` (if not already current)

## What Was Verified and Found Correct

A full audit of all 24 active OpenSpec changes was performed:

- **No items reference non-existent changes** — all change IDs in the roadmap correspond to directories in `openspec/changes/`
- **No active changes are missing from the roadmap** — all 24 active changes appear in the appropriate roadmap section
- **22 of 24 task counts are already correct** — only 2 items need fixing
- **Date** — already shows `March 30, 2026`

## Impact

- Affected files: `brama-core/ROADMAP.md`
- Affected specs: `platform` (ROADMAP Synchronization requirement)
- Risk: None — documentation-only change
