# Change: Integrate scheduler with delivery channels for proactive agent messaging

## Why

The scheduler can execute agent jobs on a cron schedule, but results are only logged — they never reach end-users. With the delivery channel abstraction (`add-delivery-channels`) and OpenClaw push endpoint (`add-openclaw-push-endpoint`) in place, scheduled jobs can now deliver their results to Telegram chats, Slack channels, Teams, webhooks, or any configured channel. This is the final piece that enables the "news-maker posts daily digest to Telegram" use case and any future proactive agent communication.

## What Changes

- **Modified DB column** `scheduled_jobs.delivery_target` — new JSONB column storing `{ "channel_id": "...", "address": "...", "metadata": {} }`
- **New Doctrine migration** `Version20260314000001.php` — adds `delivery_target` column to `scheduled_jobs`
- **Modified** `SchedulerService::tick()` — after successful job execution, if `delivery_target` is present, call `DeliveryService::deliver()` with the agent's response as content
- **Modified** `SchedulerService::registerFromManifest()` — parse `delivery_target` from manifest's `scheduled_jobs[].delivery_target`
- **Modified** `ScheduledJobRepository` — include `delivery_target` in CRUD operations
- **Modified admin UI** `/admin/scheduler` — delivery target selector in create/edit job modal: channel dropdown + address input
- **Modified admin UI** `/admin/scheduler/{id}/logs` — delivery status column in log viewer (delivered/failed/skipped)
- **Modified** `scheduler_job_logs` — new columns: `delivery_status`, `delivery_error`, `delivery_channel_id`
- **New Doctrine migration** `Version20260314000002.php` — adds delivery columns to `scheduler_job_logs`
- **Manifest schema extension** — `scheduled_jobs[].delivery_target` optional object in agent manifests

## What Does NOT Change

- `DeliveryService` and channel adapters — used as-is from `add-delivery-channels`
- Scheduler tick loop, retry policy, dead-letter logic — unchanged
- `AsyncA2ADispatcher` — unchanged (dispatches agent calls, not delivery)
- Agent A2A endpoints — agents return results as before, unaware of delivery

## Impact

- Affected specs: modifies `job-scheduling` (if archived) or adds delta; modifies `delivery-channels` (delivery from scheduler context)
- Affected code:
  - `apps/brama-core/src/Scheduler/SchedulerService.php` (modified)
  - `apps/brama-core/src/Scheduler/ScheduledJobRepository.php` (modified)
  - `apps/brama-core/src/Scheduler/SchedulerJobLogRepository.php` (modified)
  - `apps/brama-core/src/Controller/Admin/SchedulerController.php` (modified)
  - `apps/brama-core/templates/admin/scheduler/index.html.twig` (modified)
  - `apps/brama-core/templates/admin/scheduler/logs.html.twig` (modified)
  - `apps/brama-core/migrations/Version20260314000001.php` (new)
  - `apps/brama-core/migrations/Version20260314000002.php` (new)
- Depends on: `add-delivery-channels` (DeliveryService must exist)
