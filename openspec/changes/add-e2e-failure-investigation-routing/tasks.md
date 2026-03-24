## 1. Spec And Routing Contract
- [ ] 1.1 Define when `tester` and `e2e` MUST spawn a follow-up investigation task versus only reporting a warning
- [ ] 1.2 Define the task payload contract for spawned investigation work items
- [ ] 1.3 Define deduplication and idempotency rules per failing pipeline run

## 2. Builder Runtime
- [ ] 2.1 Add task-generation logic in `builder/pipeline.sh` for failed E2E gates
- [ ] 2.2 Persist investigation task artifacts and link them from the parent task summary/checkpoint
- [ ] 2.3 Route spawned tasks into the existing Builder task queue with `investigator` as the first execution phase

## 3. Ultraworks Runtime
- [ ] 3.1 Add equivalent E2E failure routing for Ultraworks/Sisyphus orchestration
- [ ] 3.2 Ensure spawned investigation tasks use the unified `u-investigator` contract and carry the same minimum context payload

## 4. Documentation
- [ ] 4.1 Update pipeline workflow documentation to describe the automatic E2E bug-triage flow
- [ ] 4.2 Update operator guidance for distinguishing infra failures, flaky tests, and product regressions

## 5. Verification
- [ ] 5.1 Validate the OpenSpec change with `openspec validate add-e2e-failure-investigation-routing --strict`
- [ ] 5.2 Verify one failed E2E run creates one investigation task per failing flow cluster, not duplicate tasks on the same run
- [ ] 5.3 Verify investigation tasks contain reproduction command, artifact paths, and routing metadata for both workflows
