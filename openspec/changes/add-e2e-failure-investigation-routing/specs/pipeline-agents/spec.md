## ADDED Requirements
### Requirement: E2E Failures Spawn Investigation Tasks

When a pipeline `tester` or `e2e` phase detects a failed end-to-end user flow, the workflow SHALL create a separate follow-up investigation task for the `investigator` / `u-investigator` flow instead of only reporting the failure in the parent run.

The spawned task SHALL contain the minimum investigation context:
- parent task identifier
- source workflow
- failing stage
- failing test identity
- reproduction command
- branch and commit
- artifact or log paths
- preliminary failure classification

#### Scenario: Builder spawns investigation task after failed E2E run
- **WHEN** Builder executes `tester` or `e2e`
- **AND** the stage reports a failed E2E scenario
- **THEN** Builder creates a separate investigation task in its task queue
- **AND** the spawned task starts with `investigator`
- **AND** the parent task records a link to the spawned task

#### Scenario: Ultraworks spawns unified investigation task after failed E2E run
- **WHEN** Ultraworks detects a failed E2E validation stage
- **THEN** it creates a separate follow-up task or delegation payload
- **AND** the receiving workflow starts with the unified `u-investigator` logic
- **AND** the payload contains the same minimum context contract as Builder

### Requirement: Investigation Task Deduplication Per Run

The workflow SHALL avoid creating duplicate investigation tasks for the same failing E2E symptom within one parent pipeline run.

If several failing tests point to the same upstream flow breakage, the workflow SHALL group them into one investigation task with multiple evidence items.

#### Scenario: Multiple failures share one root symptom
- **WHEN** one E2E run contains several failing assertions caused by the same broken flow
- **THEN** the workflow creates one investigation task
- **AND** the task lists all related failing tests as evidence

#### Scenario: Distinct flows fail independently
- **WHEN** one E2E run contains failures from different user flows with different symptoms
- **THEN** the workflow may create separate investigation tasks per flow cluster
- **AND** each task references only its own evidence set
