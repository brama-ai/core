# Pipeline Handoff

- **Task**: # Central cron/scheduler system for the platform

Зараз news-maker використовує вбудований APScheduler, а інші агенти не мають механізму планування задач. Потрібна централізована система розкладу, яка дозволяє будь-якому агенту реєструвати періодичні та разові задачі.

## Вимоги

### Архітектура

- Центральний scheduler живе в core (`apps/core/`) як Symfony Command (long-running process)
- Агенти реєструють свої задачі через manifest (секція `scheduled_jobs`)
- Scheduler викликає агентів через A2A protocol (вже є `A2AClient`)
- Persisted jobs — стан зберігається в PostgreSQL, не втрачається при рестарті

### Database

- Таблиця `scheduled_jobs`:
  - `id UUID PRIMARY KEY`
  - `agent_name VARCHAR(64) NOT NULL` — який агент зареєстрував
  - `job_name VARCHAR(128) NOT NULL` — унікальне ім'я задачі
  - `skill_id VARCHAR(128) NOT NULL` — A2A skill для виклику
  - `payload JSONB DEFAULT '{}'` — аргументи для skill
  - `cron_expression VARCHAR(64)` — crontab формат (null = одноразова)
  - `next_run_at TIMESTAMPTZ NOT NULL` — коли наступний запуск
  - `last_run_at TIMESTAMPTZ` — коли був останній запуск
  - `last_status VARCHAR(32)` — completed/failed
  - `retry_count INTEGER DEFAULT 0`
  - `max_retries INTEGER DEFAULT 3`
  - `retry_delay_seconds INTEGER DEFAULT 60`
  - `enabled BOOLEAN DEFAULT TRUE`
  - `timezone VARCHAR(64) DEFAULT 'UTC'`
  - `created_at TIMESTAMPTZ DEFAULT now()`
  - `updated_at TIMESTAMPTZ DEFAULT now()`
- Унікальний constraint на `(agent_name, job_name)`
- Індекс на `(enabled, next_run_at)` для ефективного polling

### Scheduler Command

- `php bin/console scheduler:run` — long-running process
- Кожні 10 секунд: SELECT jobs WHERE enabled AND next_run_at <= now()
- Для кожної задачі: виклик через `A2AClient::invoke($skillId, $payload)`
- Після виклику: оновити `last_run_at`, `last_status`, обчислити `next_run_at` з cron expression
- При помилці: інкрементувати `retry_count`, обчислити наступний retry з `retry_delay_seconds`
- Dead letter: якщо `retry_count >= max_retries`, вимкнути задачу (`enabled = false`) і залогувати
- Catch-up policy: якщо scheduler був вимкнений і `next_run_at` в минулому — запустити один раз і обчислити наступний

### Manifest інтеграція

- Агенти декларують scheduled_jobs в manifest.json:

```json
{
  "scheduled_jobs": [
    {
      "job_name": "crawl_pipeline",
      "skill_id": "news_maker.trigger_crawl",
      "cron": "0 */4 * * *",
      "payload": {},
      "max_retries": 3,
      "retry_delay_seconds": 120
    }
  ]
}
```

- При `install` агента — зареєструвати всі scheduled_jobs в таблиці
- При `uninstall` — видалити всі jobs цього агента
- При `disable` агента — disable всі його jobs
- При `enable` — enable назад

### Ідемпотентність

- Якщо задача вже running (concurrent call) — пропустити
- Lock через advisory lock або `FOR UPDATE SKIP LOCKED`
- Запобігти дублюванню при multiple scheduler instances

### Admin UI

- Сторінка `/admin/scheduler` з таблицею всіх задач
- Колонки: Agent, Job, Cron, Next Run, Last Run, Status, Enabled
- Кнопка "Run Now" для ручного тригера
- Toggle enabled/disabled

### Тести

- Unit тест: обчислення `next_run_at` з cron expression
- Unit тест: retry policy (increment, max retries, disable)
- Unit тест: catch-up policy
- Functional тест: реєстрація jobs при install агента
- Functional тест: видалення jobs при uninstall

## Контекст

- A2AClient: `apps/core/src/A2AGateway/A2AClient.php` — `invoke($skill, $input, $traceId, $requestId)`
- AgentInstaller: `apps/core/src/AgentInstaller/AgentInstallerService.php` — `install()`, `uninstall()`
- AgentRegistry: `apps/core/src/AgentRegistry/AgentRegistryRepository.php` — `enable()`, `disable()`
- Існуючий APScheduler в news-maker: `apps/news-maker-agent/app/services/scheduler.py` — для reference
- Cron expression парсинг: використати `dragonmantank/cron-expression` (PHP бібліотека)
- Міграції: `apps/core/migrations/`
- Compose: `compose.core.yaml`

## Обмеження

- Не видаляти APScheduler з news-maker на цьому етапі (це окрема міграція)
- Scheduler Command має gracefully завершуватись при SIGTERM/SIGINT
- Не створювати окремий мікросервіс — scheduler живе в core
- PHP бібліотека для cron: `dragonmantank/cron-expression` (перевірити сумісність)
- **Started**: 2026-03-10 23:34:04
- **Branch**: pipeline/central-cron-scheduler-system-for-the-platform
- **Pipeline ID**: 20260310_233403

---

## Architect

- **Status**: done
- **Change ID**: `add-central-scheduler`
- **Apps affected**: core (primary), news-maker-agent (reference only, no changes)
- **DB changes**: Yes — new `scheduled_jobs` table (migration `Version20260310000001`)
- **API changes**:
  - New admin page: `GET /admin/scheduler`
  - New internal API: `POST /api/v1/internal/scheduler/{id}/run` (manual trigger)
  - New internal API: `POST /api/v1/internal/scheduler/{id}/toggle` (enable/disable)
  - Modified: `POST /api/v1/internal/agents/{name}/install` (now also registers scheduled jobs)
  - Modified: `POST /api/v1/internal/agents/{name}/enable` (now also enables scheduled jobs)
  - Modified: `POST /api/v1/internal/agents/{name}/disable` (now also disables scheduled jobs)
  - Modified: uninstall path (now also deletes scheduled jobs)
- **Key design decisions**:
  - Polling-based scheduler (10s interval) as Symfony Command, not Messenger/RabbitMQ
  - `FOR UPDATE SKIP LOCKED` for concurrency control (no advisory locks)
  - Manifest-driven registration (agents declare jobs, platform manages them)
  - `dragonmantank/cron-expression` for cron parsing (same lib used by Laravel/Symfony)
  - Separate Docker service `core-scheduler` with `unless-stopped` restart policy
  - Catch-up policy: run once on restart, compute next from now (no replay)
- **Risks**:
  - Single scheduler instance = SPOF (mitigated by Docker restart + catch-up)
  - 10s polling granularity (acceptable for cron-scale jobs)
  - A2A call timeout (30s) may block tick for slow agents

## Coder

- **Status**: done
- **Files created**:
  - `apps/core/migrations/Version20260310000001.php` — `scheduled_jobs` table migration
  - `apps/core/src/Scheduler/ScheduledJobRepository.php` — DBAL repository for scheduled jobs
  - `apps/core/src/Scheduler/CronExpressionHelper.php` — wrapper around dragonmantank/cron-expression
  - `apps/core/src/Scheduler/SchedulerService.php` — orchestration: tick, registerFromManifest, lifecycle methods
  - `apps/core/src/A2AGateway/A2AClientInterface.php` — interface extracted from A2AClient for testability
  - `apps/core/src/Command/SchedulerRunCommand.php` — long-running `scheduler:run` command with SIGTERM/SIGINT handling
  - `apps/core/src/Controller/Admin/SchedulerController.php` — `GET /admin/scheduler` admin page
  - `apps/core/src/Controller/Api/Internal/SchedulerRunNowController.php` — `POST /api/v1/internal/scheduler/{id}/run`
  - `apps/core/src/Controller/Api/Internal/SchedulerToggleController.php` — `POST /api/v1/internal/scheduler/{id}/toggle`
  - `apps/core/templates/admin/scheduler/index.html.twig` — admin UI template
  - `apps/core/tests/Unit/Scheduler/CronExpressionHelperTest.php` — unit tests for cron helper
  - `apps/core/tests/Unit/Scheduler/SchedulerServiceTest.php` — unit tests for scheduler service (retry, dead-letter, catch-up)
  - `apps/core/tests/Functional/Scheduler/ScheduledJobRepositoryTest.php` — functional tests for repository
  - `apps/core/tests/Functional/Scheduler/AgentInstallSchedulerTest.php` — functional tests for install/uninstall lifecycle
  - `docs/scheduler.md` — developer documentation
- **Files modified**:
  - `apps/core/composer.json` — added `dragonmantank/cron-expression:^3.3`
  - `apps/core/composer.lock` — updated by composer
  - `apps/core/src/A2AGateway/A2AClient.php` — implements `A2AClientInterface`
  - `apps/core/src/Controller/Api/Internal/AgentInstallController.php` — calls `SchedulerService::registerFromManifest` after install
  - `apps/core/src/Controller/Api/Internal/AgentDeleteController.php` — calls `SchedulerService::removeByAgent` before uninstall
  - `apps/core/src/Controller/Api/Internal/AgentEnableController.php` — calls `SchedulerService::enableByAgent` after enable
  - `apps/core/src/Controller/Api/Internal/AgentDisableController.php` — calls `SchedulerService::disableByAgent` after disable
  - `apps/core/templates/admin/layout.html.twig` — added "Планувальник" nav link
  - `compose.core.yaml` — added `core-scheduler` service
  - `docs/agent-requirements/storage-provisioning.md` — added `scheduled_jobs` section
  - `openspec/changes/add-central-scheduler/tasks.md` — marked all tasks `[x]`
- **Migrations created**: `Version20260310000001` — run on both dev and test databases
- **Deviations**:
  - `ScheduledJobRepository` and `CronExpressionHelper` are not `final` (removed to allow PHPUnit mocking in unit tests)
  - Created `A2AClientInterface` to allow mocking `A2AClient` in `SchedulerServiceTest` (A2AClient is final)
  - Tasks 7.4 and 7.5 combined into `AgentInstallSchedulerTest` (install and uninstall tests in one file)
  - PHPStan reports 2 pre-existing errors in `NewsCrawlTrigger.php` and `OpenSearchInstallStrategy.php` — not introduced by this change

## Validator

- **Status**: pending
- **PHPStan**: —
- **CS-check**: —
- **Files fixed**: —

## Tester

- **Status**: pending
- **Test results**: —
- **New tests written**: —

## Documenter

- **Status**: pending
- **Docs created/updated**: —

---

- **Commit (architect)**: c9352f0
