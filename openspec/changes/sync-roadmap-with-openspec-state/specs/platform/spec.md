## MODIFIED Requirements

### Requirement: ROADMAP Synchronization
Every feature development SHALL maintain synchronization between ROADMAP.md and OpenSpec proposals.

#### Scenario: Starting new work
- **WHEN** a developer starts working on a new feature
- **THEN** they must check ROADMAP.md for conflicts
- **AND** add the feature to "In Progress" section
- **AND** include OpenSpec change ID reference
- **AND** commit ROADMAP updates with the proposal

#### Scenario: Completing feature
- **WHEN** a developer completes a feature
- **THEN** they must archive the OpenSpec proposal
- **AND** move the item to "Completed" in ROADMAP
- **AND** update any relevant metrics
- **AND** commit changes together

#### Scenario: Weekly review
- **WHEN** the team performs weekly review
- **THEN** ROADMAP must reflect current state
- **AND** all In Progress items must show task counts
- **AND** blockers must be documented
- **AND** priorities must be adjusted if needed

#### Scenario: Periodic reconciliation with OpenSpec state
- **WHEN** a reconciliation pass is performed on ROADMAP.md
- **THEN** every "In Progress" and "Planned" item with an OpenSpec change ID SHALL have its task count updated to match the actual checked/total count in the corresponding `tasks.md`
- **AND** items whose OpenSpec change directory no longer exists (not in `changes/` or `changes/archive/`) SHALL be removed from the roadmap
- **AND** active OpenSpec changes that are missing from the roadmap SHALL be added to the appropriate section
- **AND** the "Last updated" date SHALL be set to the current date
