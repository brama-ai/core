## ADDED Requirements

### Requirement: Batch Archive of Completed Changes

The project SHALL support batch archiving of completed OpenSpec changes. When multiple changes have reached 100% task completion, they SHALL be moved from `openspec/changes/<id>/` to `openspec/changes/archive/YYYY-MM-DD-<id>/` in a single coordinated operation. The ROADMAP.md MUST be updated in the same operation to reflect the archived items as completed and to correct task counts for remaining active changes.

#### Scenario: Batch archive moves completed changes to archive directory
- **WHEN** one or more changes have all tasks marked `[x]` or are marked `STATUS: COMPLETED` in tasks.md
- **THEN** each change directory is moved to `openspec/changes/archive/YYYY-MM-DD-<id>/`
- **AND** `openspec list` no longer shows the archived changes as active

#### Scenario: ROADMAP synchronization after batch archive
- **WHEN** changes are archived
- **THEN** ROADMAP.md moves archived items from "In Progress" to "Completed"
- **AND** task counts for remaining active changes are updated to match actual `tasks.md` progress
- **AND** the "Last updated" date in ROADMAP.md is set to the archive date
