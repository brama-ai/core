## 1. Database & Dependencies

- [ ] 1.1 Add `dragonmantank/cron-expression` to `apps/core/composer.json` and run `composer update`
- [ ] 1.2 Create migration `Version20260310000001.php` in `apps/core/migrations/`:
  - Table `scheduled_jobs` with columns: `id` (UUID PK), `agent_name` (VARCHAR 64 NOT NULL), `job_name` (VARCHAR 128 NOT NULL), `skill_id` (VARCHAR 128 NOT NULL), `payload` (JSONB DEFAULT '{}'), `cron_expression` (VARCHAR 64 nullable), `next_run_at` (TIMESTAMPTZ NOT NULL), `last_run_at` (TIMESTAMPTZ nullable), `last_status` (VARCHAR 32 nullable), `retry_count` (INTEGER DEFAULT 0), `max_retries` (INTEGER DEFAULT 3), `retry_delay_seconds` (INTEGER DEFAULT 60), `enabled` (BOOLEAN DEFAULT TRUE), `timezone` (VARCHAR 64 DEFAULT 'UTC'), `created_at` (TIMESTAMPTZ DEFAULT now()), `updated_at` (TIMESTAMPTZ DEFAULT now())
  - UNIQUE constraint on `(agent_name, job_name)`
  - Index on `(enabled, next_run_at)` for polling

## 2. Core Scheduler Services

- [ ] 2.1 Create `apps/core/src/Scheduler/ScheduledJobRepository.php` — DBAL-based repository:
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
- [ ] 2.2 Create `apps/core/src/Scheduler/CronExpressionHelper.php` — wrapper around `dragonmantank/cron-expression`:
  - `computeNextRun(string $cronExpression, string $timezone = 'UTC'): \DateTimeImmutable`
  - `isValid(string $cronExpression): bool`
- [ ] 2.3 Create `apps/core/src/Scheduler/SchedulerService.php` — orchestration logic:
  - `tick(): int` — find due jobs, invoke each via A2AClient, handle success/failure/retry/dead-letter, return count of executed jobs
  - `registerFromManifest(string $agentName, array $manifest): int` — extract `scheduled_jobs` from manifest, call repository for each
  - `removeByAgent(string $agentName): int`
  - `enableByAgent(string $agentName): int`
  - `disableByAgent(string $agentName): int`

## 3. Scheduler Command

- [ ] 3.1 Create `apps/core/src/Command/SchedulerRunCommand.php`:
  - Symfony Command name: `scheduler:run`
  - Implements `SignalableCommandInterface` for SIGTERM/SIGINT handling
  - Main loop: sleep 10 seconds, call `SchedulerService::tick()`, log results
  - On signal: set `$shouldStop = true`, exit loop gracefully
  - Catch-up: handled by `tick()` — if `next_run_at` is in the past, run once and compute next

## 4. Agent Lifecycle Integration

- [ ] 4.1 Modify `AgentInstallController::__invoke()` — after successful install, call `SchedulerService::registerFromManifest($name, $manifest)`
- [ ] 4.2 Modify `AgentDeleteController` (uninstall path) — before/after uninstall, call `SchedulerService::removeByAgent($name)`
- [ ] 4.3 Modify `AgentEnableController::__invoke()` — after enable, call `SchedulerService::enableByAgent($name)`
- [ ] 4.4 Modify `AgentDisableController::__invoke()` — after disable, call `SchedulerService::disableByAgent($name)`

## 5. Admin UI

- [ ] 5.1 Create `apps/core/src/Controller/Admin/SchedulerController.php`:
  - `GET /admin/scheduler` — list all jobs from `ScheduledJobRepository::findAll()`
  - Render Twig template with table: Agent, Job, Skill, Cron, Next Run, Last Run, Status, Enabled
- [ ] 5.2 Create Twig template `apps/core/templates/admin/scheduler/index.html.twig`
- [ ] 5.3 Create `apps/core/src/Controller/Api/Internal/SchedulerRunNowController.php`:
  - `POST /api/v1/internal/scheduler/{id}/run` — call `ScheduledJobRepository::triggerNow($id)`, return JSON
- [ ] 5.4 Create `apps/core/src/Controller/Api/Internal/SchedulerToggleController.php`:
  - `POST /api/v1/internal/scheduler/{id}/toggle` — call `ScheduledJobRepository::toggleEnabled($id, ...)`, return JSON
- [ ] 5.5 Add "Scheduler" link to admin navigation sidebar

## 6. Docker Compose

- [ ] 6.1 Add `core-scheduler` service to `compose.core.yaml`:
  - Same image as core
  - Command: `php bin/console scheduler:run`
  - Depends on: postgres, core (for migrations)
  - Restart policy: `unless-stopped`
  - No port exposure needed

## 7. Tests

- [ ] 7.1 Unit test: `CronExpressionHelperTest` — compute next run from various cron expressions, timezone handling
- [ ] 7.2 Unit test: `SchedulerServiceTest` — retry policy (increment count, compute next retry time), dead-letter (disable after max retries), catch-up policy (past next_run_at triggers one run)
- [ ] 7.3 Functional test: `ScheduledJobRepositoryTest` — register, find due, update after run, delete by agent
- [ ] 7.4 Functional test: `AgentInstallSchedulerTest` — installing agent with `scheduled_jobs` in manifest creates rows in `scheduled_jobs` table
- [ ] 7.5 Functional test: `AgentUninstallSchedulerTest` — uninstalling agent removes its scheduled jobs

## 8. Documentation

- [ ] 8.1 Create `docs/scheduler.md` — developer-facing doc: how the scheduler works, manifest format for `scheduled_jobs`, retry/dead-letter policy, admin UI usage
- [ ] 8.2 Update `docs/agent-requirements/` if agent manifest contract docs exist — add `scheduled_jobs` section

## 9. Quality Checks

- [ ] 9.1 Run `phpstan analyse` — zero errors at level 8
- [ ] 9.2 Run `php-cs-fixer check` — no style violations
- [ ] 9.3 Run `codecept run` — all unit + functional suites pass
