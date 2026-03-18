## 1. Database & Dependencies

- [x] 1.1 Add `dragonmantank/cron-expression` to `apps/core/composer.json` and run `composer update`
- [x] 1.2 Create migration `Version20260310000001.php` in `apps/core/migrations/`:
  - Table `scheduled_jobs` with columns: `id` (UUID PK), `agent_name` (VARCHAR 64 NOT NULL), `job_name` (VARCHAR 128 NOT NULL), `skill_id` (VARCHAR 128 NOT NULL), `payload` (JSONB DEFAULT '{}'), `cron_expression` (VARCHAR 64 nullable), `next_run_at` (TIMESTAMPTZ NOT NULL), `last_run_at` (TIMESTAMPTZ nullable), `last_status` (VARCHAR 32 nullable), `retry_count` (INTEGER DEFAULT 0), `max_retries` (INTEGER DEFAULT 3), `retry_delay_seconds` (INTEGER DEFAULT 60), `enabled` (BOOLEAN DEFAULT TRUE), `timezone` (VARCHAR 64 DEFAULT 'UTC'), `created_at` (TIMESTAMPTZ DEFAULT now()), `updated_at` (TIMESTAMPTZ DEFAULT now())
  - UNIQUE constraint on `(agent_name, job_name)`
  - Index on `(enabled, next_run_at)` for polling

## 2. Core Scheduler Services

- [x] 2.1 Create `apps/core/src/Scheduler/ScheduledJobRepository.php` — DBAL-based repository:
  - `findDueJobs(): array` — `SELECT ... WHERE enabled = TRUE AND next_run_at <= now() FOR UPDATE SKIP LOCKED`
  - `registerJob(string $agentName, string $jobName, string $skillId, array $payload, ?string $cronExpression, int $maxRetries, int $retryDelaySeconds, string $timezone): void` — INSERT with ON CONFLICT (agent_name, job_name) DO UPDATE
  - `deleteByAgent(string $agentName): int` — DELETE WHERE agent_name = :name
  - `enableByAgent(string $agentName): int` — UPDATE SET enabled = TRUE
  - `disableByAgent(string $agentName): int` — UPDATE SET enabled = FALSE
  - `findAll(): array` — for admin listing
  - `findById(string $id): ?array`
  - `updateAfterRun(string $id, string $status, ?string $nextRunAt): void`
  - `updateRetry(string $id, int $retryCount, string $nextRunAt): void`
  - `disableJob(string $id): void` — dead letter
  - `triggerNow(string $id): void` — set next_run_at = now()
  - `toggleEnabled(string $id, bool $enabled): void`
- [x] 2.2 Create `apps/core/src/Scheduler/CronExpressionHelper.php` — wrapper around `dragonmantank/cron-expression`:
  - `computeNextRun(string $cronExpression, string $timezone = 'UTC'): \DateTimeImmutable`
  - `isValid(string $cronExpression): bool`
- [x] 2.3 Create `apps/core/src/Scheduler/SchedulerService.php` — orchestration logic:
  - `tick(): int` — find due jobs, invoke each via A2AClient, handle success/failure/retry/dead-letter, return count of executed jobs
  - `registerFromManifest(string $agentName, array $manifest): int` — extract `scheduled_jobs` from manifest, call repository for each
  - `removeByAgent(string $agentName): int`
  - `enableByAgent(string $agentName): int`
  - `disableByAgent(string $agentName): int`

## 3. Scheduler Command

- [x] 3.1 Create `apps/core/src/Command/SchedulerRunCommand.php`:
  - Symfony Command name: `scheduler:run`
  - Implements `SignalableCommandInterface` for SIGTERM/SIGINT handling
  - Main loop: sleep 10 seconds, call `SchedulerService::tick()`, log results
  - On signal: set `$shouldStop = true`, exit loop gracefully
  - Catch-up: handled by `tick()` — if `next_run_at` is in the past, run once and compute next

## 4. Agent Lifecycle Integration

- [x] 4.1 Modify `AgentInstallController::__invoke()` — after successful install, call `SchedulerService::registerFromManifest($name, $manifest)`
- [x] 4.2 Modify `AgentDeleteController` (uninstall path) — before/after uninstall, call `SchedulerService::removeByAgent($name)`
- [x] 4.3 Modify `AgentEnableController::__invoke()` — after enable, call `SchedulerService::enableByAgent($name)`
- [x] 4.4 Modify `AgentDisableController::__invoke()` — after disable, call `SchedulerService::disableByAgent($name)`

## 5. Admin UI

- [x] 5.1 Create `apps/core/src/Controller/Admin/SchedulerController.php`:
  - `GET /admin/scheduler` — list all jobs from `ScheduledJobRepository::findAll()`
  - Render Twig template with table: Agent, Job, Skill, Cron, Next Run, Last Run, Status, Enabled
- [x] 5.2 Create Twig template `apps/core/templates/admin/scheduler/index.html.twig`
- [x] 5.3 Create `apps/core/src/Controller/Api/Internal/SchedulerRunNowController.php`:
  - `POST /api/v1/internal/scheduler/{id}/run` — call `ScheduledJobRepository::triggerNow($id)`, return JSON
- [x] 5.4 Create `apps/core/src/Controller/Api/Internal/SchedulerToggleController.php`:
  - `POST /api/v1/internal/scheduler/{id}/toggle` — call `ScheduledJobRepository::toggleEnabled($id, ...)`, return JSON
- [x] 5.5 Add "Scheduler" link to admin navigation sidebar

## 6. Docker Compose

- [x] 6.1 Add `core-scheduler` service to `compose.core.yaml`:
  - Same image as core
  - Command: `php bin/console scheduler:run`
  - Depends on: postgres, core (for migrations)
  - Restart policy: `unless-stopped`
  - No port exposure needed

## 7. Tests

- [x] 7.1 Unit test: `CronExpressionHelperTest` — compute next run from various cron expressions, timezone handling
- [x] 7.2 Unit test: `SchedulerServiceTest` — retry policy (increment count, compute next retry time), dead-letter (disable after max retries), catch-up policy (past next_run_at triggers one run)
- [x] 7.3 Functional test: `ScheduledJobRepositoryTest` — register, find due, update after run, delete by agent
- [x] 7.4 Functional test: `AgentInstallSchedulerTest` — installing agent with `scheduled_jobs` in manifest creates rows in `scheduled_jobs` table
- [x] 7.5 Functional test: `AgentUninstallSchedulerTest` — uninstalling agent removes its scheduled jobs (combined in `AgentInstallSchedulerTest`)

## 8. Execution Logs

- [x] 8.1 Create migration `Version20260311000002.php` for `scheduler_job_logs` table with FK, indexes
- [x] 8.2 Create `SchedulerJobLogRepository.php` + `SchedulerJobLogRepositoryInterface.php` — logStart, logFinish, findByJob, countByJob
- [x] 8.3 Modify `SchedulerService::tick()` — logStart before A2A call, logFinish after (success + failure + exception)
- [x] 8.4 Create `SchedulerJobLogsController.php` — `/admin/scheduler/{id}/logs` with pagination
- [x] 8.5 Create `logs.html.twig` — log viewer with status badges, duration, error tooltips, pagination
- [x] 8.6 Create `SchedulerJobLogsApiController.php` — `GET /api/v1/internal/scheduler/{id}/logs` JSON endpoint
- [x] 8.7 Add "Логи" link per job row in `index.html.twig`

## 9. Visual Cron Builder

- [x] 9.1 Add Vue 3 via esm.sh importmap — loaded only on scheduler page via lazy `initCronBuilder()`
- [x] 9.2 Add @vue-js-cron/light v5.1.1 via esm.sh (bundle-deps, external=vue) + CSS from esm.sh CDN
- [x] 9.3 Modify create modal: toggle "Візуальний"/"Текстовий" button, `<div id="cron-builder-app">` mount point, bidirectional sync between Vue component and `#cj_cron` text input
- [x] 9.4 Add CSS overrides for dark admin theme (transparent background, themed selects, border colors)

## 10. Documentation

- [x] 10.1 Create `docs/scheduler.md` — developer-facing doc: how the scheduler works, manifest format for `scheduled_jobs`, retry/dead-letter policy, admin UI usage
- [x] 10.2 Update `docs/agent-requirements/storage-provisioning.md` — add `scheduled_jobs` section
- [x] 10.3 Update `docs/scheduler.md` — added execution logs section, log table schema, log viewer, API endpoints
- [x] 10.4 Update `docs/scheduler.md` — added visual cron builder section, stale detection, job sources

## 11. Tests

- [x] 11.1 Unit test: `SchedulerServiceTest::testTickLogsStartAndCompletedOnSuccess` — verifies logStart + logFinish('completed')
- [x] 11.2 Unit test: `SchedulerServiceTest::testTickLogsFailedOnException` — verifies logFinish('failed') on exception
- [x] 11.3 Unit test: `SchedulerServiceTest::testTickLogsFailedOnAgentFailedStatus` — verifies logFinish('failed') on agent status=failed
- [x] 11.4 E2E tests: all 10 existing scheduler tests pass (log page and cron builder tested via integration)

## 12. Scheduler Runtime & Status Dashboard

- [x] 12.0.1 Fix `pcntl` extension missing in Docker image — `SIGTERM`/`SIGINT` undefined; added `pcntl` to `docker-php-ext-install`
- [x] 12.0.2 Fix visual cron builder not rendering — Vue ESM import used runtime-only build (no template compiler); switched to `vue.esm-browser.prod.js`
- [x] 12.0.3 Fix cron builder race condition — replaced event-based coordination with global function + flag pattern
- [x] 12.0.4 Fix cron builder dark theme — added `class="dark"` to mount container for library built-in `.dark .ant` theme
- [x] 12.0.5 Add scheduler status dashboard — stat cards (total/enabled, 24h runs, problems, last run) above job table
- [x] 12.0.6 Add `computeStats()` to `SchedulerController` — aggregates job states + 24h log stats via SQL
- [x] 12.0.7 E2E: add `@visual-cron` test scenario — opens modal, toggles visual builder, selects hourly, saves job

## 13. Quality Checks

- [x] 13.1 Run `phpstan analyse` — 0 new errors
- [x] 13.2 Run `php-cs-fixer check` — no style violations
- [x] 13.3 Run `codecept run` — all tests pass
- [x] 13.4 Run `phpstan analyse` — 0 errors (new log code included)
- [x] 13.5 Run `php-cs-fixer check` — 0 violations (151 files)
- [x] 13.6 Run `codecept run Unit Scheduler` — 19 tests, 99 assertions, all pass
- [x] 13.7 Run E2E tests — 11 scheduler tests pass (including @visual-cron)
