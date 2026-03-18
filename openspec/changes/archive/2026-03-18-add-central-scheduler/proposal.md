# Change: Add central cron/scheduler system for the platform

## Why

Currently only news-maker-agent has scheduling via its embedded APScheduler, while other agents have no mechanism for periodic or one-shot tasks. A centralized scheduler in core allows any agent to declare scheduled jobs in its manifest and have them executed reliably via the existing A2A protocol, with persistence, retries, and admin visibility.

## What Changes

- **New DB table** `scheduled_jobs` in core — stores job definitions, next-run times, retry state, and enabled flag
- **New Doctrine migration** `Version20260310000001.php` — creates the table with unique constraint and index
- **New Composer dependency** `dragonmantank/cron-expression` — cron expression parsing
- **New Symfony Command** `scheduler:run` — long-running polling loop (10s interval) that picks due jobs, invokes agent skills via `A2AClient`, handles retries and dead-letter
- **New service** `ScheduledJobRepository` — DBAL-based CRUD for `scheduled_jobs`
- **New service** `SchedulerService` — orchestrates tick logic: find due jobs, lock, invoke, update state
- **Modified** `AgentInstallController` — on install, register `scheduled_jobs` from manifest into DB
- **Modified** `AgentDeleteController` (uninstall path) — on uninstall, delete agent's scheduled jobs
- **Modified** `AgentEnableController` — on enable, re-enable agent's scheduled jobs
- **Modified** `AgentDisableController` — on disable, disable agent's scheduled jobs
- **New admin page** `/admin/scheduler` — table of all jobs with Run Now and toggle enabled
- **New internal API** `POST /api/v1/internal/scheduler/{id}/run` — manual trigger
- **New internal API** `POST /api/v1/internal/scheduler/{id}/toggle` — enable/disable toggle
- **New Docker service entry** in `compose.core.yaml` — scheduler worker process
- **Manifest schema extension** — new optional `scheduled_jobs` array in agent manifests

- **New DB table** `scheduler_job_logs` in core — stores execution history: job reference, start/finish timestamps, status, error message, payload sent, response received
- **New admin page** `/admin/scheduler/{id}/logs` — execution log viewer per job with status badges, timing, and error details
- **Modified admin page** `/admin/scheduler` — "Logs" link per job row; latest log status visible inline
- **New internal API** `GET /api/v1/internal/scheduler/{id}/logs` — paginated log retrieval
- **Modified** `SchedulerService::tick()` — writes a log entry before and after each job execution
- **New Doctrine migration** — creates `scheduler_job_logs` table
- **Modified create/edit modal** — adds visual cron builder (@vue-js-cron/light via CDN) alongside classic text input, allowing non-technical users to configure schedules via clickable UI

## Impact

- Affected specs: new capability `job-scheduling`; modifies `hello-world-agent` spec (manifest schema extension)
- Affected code:
  - `apps/core/src/Scheduler/` (new namespace)
  - `apps/core/src/Controller/Api/Internal/AgentInstallController.php`
  - `apps/core/src/Controller/Api/Internal/AgentDeleteController.php`
  - `apps/core/src/Controller/Api/Internal/AgentEnableController.php`
  - `apps/core/src/Controller/Api/Internal/AgentDisableController.php`
  - `apps/core/src/Controller/Admin/SchedulerController.php` (new)
  - `apps/core/migrations/Version20260310000001.php` (new)
  - `compose.core.yaml`
- Affected code (new):
  - `apps/core/src/Scheduler/SchedulerJobLogRepository.php` (new)
  - `apps/core/src/Controller/Admin/SchedulerJobLogsController.php` (new)
  - `apps/core/src/Controller/Api/Internal/SchedulerJobLogsApiController.php` (new)
  - `apps/core/templates/admin/scheduler/logs.html.twig` (new)
  - `apps/core/templates/admin/scheduler/index.html.twig` (modified — cron builder, log links)
  - `apps/core/src/Scheduler/SchedulerService.php` (modified — log writes)
- Does NOT remove APScheduler from news-maker-agent (separate future migration)
