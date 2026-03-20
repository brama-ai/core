<!-- batch: 20260312_160247 | status: pass | duration: 1202s | branch: pipeline/implement-change-add-scheduler-delivery -->
<!-- priority: 2 -->
# Implement change: add-scheduler-delivery

Connect the scheduler to the delivery channel system so scheduled job results can be pushed to Telegram, Slack, Teams, or any configured channel. Adds `delivery_target` to jobs, delivery status tracking in logs, and admin UI for target management.

## OpenSpec

- Proposal: openspec/changes/add-scheduler-delivery/proposal.md
- Tasks: openspec/changes/add-scheduler-delivery/tasks.md
- Spec delta: openspec/changes/add-scheduler-delivery/specs/job-scheduling/spec.md

## Context

- Depends on: `add-delivery-channels` (DeliveryService must exist) and `add-openclaw-push-endpoint` (for Telegram delivery)
- This is the final piece: enables "news-maker posts daily digest to Telegram" use case
- Modifies existing scheduler code — must preserve all existing behavior
- Delivery failure does NOT trigger job retry — delivery is fire-and-forget on top of job execution
- `DeliveryServiceInterface` is optional dependency — if not wired, scheduler works as before (backward compatible)
- Idempotency key format: `sched_{job_id}_{log_id}` — unique per execution

## Key files to create/update

### In apps/core/:
- `migrations/Version20260314000001.php` (new — delivery_target column on scheduled_jobs)
- `migrations/Version20260314000002.php` (new — delivery columns on scheduler_job_logs)
- `src/Scheduler/SchedulerService.php` (modified — delivery after successful job)
- `src/Scheduler/ScheduledJobRepository.php` (modified — delivery_target in CRUD)
- `src/Scheduler/SchedulerJobLogRepository.php` (modified — delivery status columns)
- `src/Controller/Admin/SchedulerController.php` (modified — delivery channel dropdown)
- `templates/admin/scheduler/index.html.twig` (modified — delivery target in modal + column)
- `templates/admin/scheduler/logs.html.twig` (modified — delivery status column)
- `tests/Unit/Scheduler/SchedulerServiceTest.php` (modified — delivery test cases)
- `tests/Functional/Scheduler/ScheduledJobRepositoryTest.php` (modified — delivery_target round-trip)

### In docs/:
- `docs/scheduler.md` (modified — delivery section)
- `docs/delivery-channels.md` (modified — scheduler integration section)

## Validation

- openspec validate add-scheduler-delivery --strict
- `make analyse` — 0 errors
- `make cs-check` — 0 violations
- `make test` — all tests pass
- Manual: create scheduled job with delivery target, trigger "Run Now", verify message arrives in Telegram
