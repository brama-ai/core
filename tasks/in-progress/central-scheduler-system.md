# Central cron/scheduler system for the platform

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
