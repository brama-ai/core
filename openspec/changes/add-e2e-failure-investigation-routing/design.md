# Design: Failed E2E Investigation Routing

## Context
The current pipeline runtime already has:
- `investigator` / `u-investigator` as a bug-analysis role
- `tester` and `e2e` stages in Builder
- task queue directories under `builder/tasks/*`

What is missing is a required orchestration rule that bridges a failed E2E gate into a new investigation work item.

## Goals
- Preserve the parent implementation task outcome and failing artifacts.
- Create a separate investigation task that can be processed independently.
- Keep the spawned task payload deterministic and compact.
- Reuse existing `investigator` / `u-investigator` roles instead of inventing a new bug-triage agent.

## Non-Goals
- Automatic bug fixing as part of the spawn step
- Automatic issue tracker integration
- Automatic retry loops for flaky tests beyond basic failure classification hints

## Proposed Flow
1. Parent pipeline reaches `tester` or `e2e`.
2. One or more E2E scenarios fail.
3. The runtime clusters failures by flow or shared root symptom.
4. For each cluster, the runtime creates one follow-up investigation task.
5. The parent task records links to the spawned investigation task files/artifacts.
6. The follow-up task starts with `investigator` and decides whether the next path is:
   - direct bugfix
   - bugfix plus spec change
   - infra/environment remediation
   - test-only remediation

## Task Payload
Each spawned investigation task should include:
- parent task slug/id
- workflow source (`builder` or `ultraworks`)
- failing stage (`tester` or `e2e`)
- test file/name and failure message summary
- affected user flow / CUJ if known
- reproduction command (`make e2e`, filtered command, or exact suite command)
- branch and latest commit
- artifact/log/report paths
- preliminary classification hint: `product-bug`, `infra`, `flaky`, or `unknown`

## Routing Rules
- Environment/bootstrap failures that prevent test execution still create an investigation task, but must be tagged `infra`.
- Assertion failures in executed user flows create investigation tasks tagged `product-bug` unless stronger evidence indicates `flaky`.
- If multiple tests fail from the same upstream symptom in the same run, the runtime should emit one investigation task with grouped evidence.

## Builder Shape
- Builder can materialize spawned work as a new markdown task under `builder/tasks/todo/`.
- The task title should clearly indicate it is an auto-generated E2E investigation.
- Parent artifacts should include a small machine-readable index of spawned tasks for monitor/UI linkage.

## Ultraworks Shape
- Ultraworks should emit the same logical task payload, but through its own queue/delegation mechanism.
- The receiving flow must still begin with the unified `u-investigator` logic.
