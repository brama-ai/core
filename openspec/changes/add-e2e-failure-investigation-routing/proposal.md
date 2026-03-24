# Change: Route Failed E2E Runs Into Investigation Workflow

## Why
E2E failures currently stop the Builder pipeline, but they do not consistently create a separate investigation work item for the next workflow stage. This leaves operators to manually copy logs, summarize the failing flow, and decide whether the issue is a product bug, flaky test, environment problem, or missing spec coverage.

The team needs a deterministic handoff: when `tester` or `e2e` detects a real failing end-to-end flow, the system should create a dedicated investigation task for the `u-investigator` flow so another agent can analyze root cause and recommend the next path.

## What Changes
- Add a pipeline contract for automatic follow-up task creation when E2E validation fails.
- Require Builder and Ultraworks to create a separate investigation task artifact instead of only marking the parent run as failed.
- Define the minimum payload for the spawned investigation task: failing test identity, affected flow, reproduction command, artifacts/log paths, branch/commit, and preliminary classification hints.
- Define routing rules so infrastructure/bootstrap failures can be tagged differently from product regressions while still entering investigation.
- Require deduplication per pipeline run so one failing E2E run does not create multiple equivalent investigation tasks for the same failing flow.
- Update workflow documentation to describe the new bug-triage path.

## Impact
- Affected specs: `pipeline-agents`, `e2e-testing`
- Affected code: `builder/pipeline.sh`, Builder task lifecycle helpers, Ultraworks orchestration/config, task artifact generation, workflow docs under `docs/`
- Architectural impact: yes, this adds cross-workflow task spawning and a new required handoff contract for failed E2E gates
