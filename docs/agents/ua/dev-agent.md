# Dev Agent

## Призначення
Dev Agent оркеструє задачі розробки через мультиагентний pipeline платформи. Надає веб-інтерфейс для створення задач з уточненням через LLM, виконання pipeline з live-стрімінгом логів та автоматичним створенням GitHub PR після успіху.

## Можливості
- `POST /api/v1/a2a` — A2A ендпоінт з 4 навичками
- `GET /health` — стандартна перевірка стану (`{"status": "ok", "service": "dev-agent"}`)
- `GET /api/v1/manifest` — Agent Card за конвенціями платформи
- `GET /admin/tasks` — список задач з фільтрацією та статистикою
- `GET /admin/tasks/create` — створення задачі з уточненням через Opus
- `GET /admin/tasks/{id}` — деталі задачі з SSE live-логами pipeline
- `GET /admin/tasks/api/{id}/logs/stream` — SSE ендпоінт для стрімінгу логів

## Навички

| ID навички | Опис | Ключові параметри |
|---|---|---|
| `dev.create_task` | Створити задачу розробки | `title`, `description`, `pipeline_options` |
| `dev.run_pipeline` | Поставити задачу в чергу на виконання (async) | `task_id` |
| `dev.get_status` | Отримати статус задачі, гілку, URL PR, кількість логів | `task_id` |
| `dev.list_tasks` | Список останніх задач з опціональним фільтром | `status_filter`, `limit` |

### Payload `dev.create_task`

```json
{
  "intent": "dev.create_task",
  "payload": {
    "title": "Додати пагінацію пошуку",
    "description": "Пошук знань повертає всі результати. Додати limit/offset.",
    "pipeline_options": { "skip_architect": false, "audit": false }
  }
}
```

Повертає `{ "status": "completed", "data": { "task_id": 42 } }`.

### Payload `dev.run_pipeline`

```json
{
  "intent": "dev.run_pipeline",
  "payload": { "task_id": 42 }
}
```

Задача повинна бути в статусі `draft` або `failed`. Повертає `{ "status": "completed", "data": { "task_id": 42, "pipeline_status": "pending" } }`.

### Відповідь `dev.get_status`

```json
{
  "status": "completed",
  "data": {
    "task_id": 42,
    "title": "Додати пагінацію пошуку",
    "task_status": "success",
    "branch": "pipeline/add-search-pagination",
    "pr_url": "https://github.com/nmdimas/ai-community-platform/pull/15",
    "log_count": 234,
    "created_at": "2026-03-09T10:00:00Z",
    "started_at": "2026-03-09T10:05:00Z",
    "finished_at": "2026-03-09T10:47:00Z"
  }
}
```

## База даних

### Таблиця: `dev_tasks`

| Колонка | Тип | Примітки |
|---|---|---|
| `id` | SERIAL PK | Автоінкремент |
| `title` | VARCHAR(200) | Назва задачі |
| `description` | TEXT | Початковий опис |
| `refined_spec` | TEXT | Специфікація після уточнення Opus (nullable) |
| `status` | VARCHAR(20) | `draft`, `pending`, `running`, `success`, `failed`, `cancelled` |
| `branch` | VARCHAR(100) | Назва git-гілки (nullable) |
| `pipeline_id` | VARCHAR(30) | ID pipeline на основі timestamp (nullable) |
| `pr_url` | VARCHAR(500) | URL GitHub PR (nullable) |
| `pr_number` | INTEGER | Номер PR (nullable) |
| `pipeline_options` | JSONB | Опції: `skip_architect`, `audit` |
| `chat_history` | JSONB | Історія чату з Opus |
| `error_message` | TEXT | Деталі помилки (nullable) |
| `duration_seconds` | INTEGER | Тривалість pipeline в секундах (nullable) |
| `created_at` | TIMESTAMPTZ | Час створення задачі |
| `started_at` | TIMESTAMPTZ | Початок pipeline (nullable) |
| `finished_at` | TIMESTAMPTZ | Завершення pipeline (nullable) |

### Таблиця: `dev_task_logs`

| Колонка | Тип | Примітки |
|---|---|---|
| `id` | BIGSERIAL PK | Автоінкремент |
| `task_id` | INTEGER FK | Посилання на `dev_tasks(id)` |
| `agent_step` | VARCHAR(30) | architect, coder, validator, tester, documenter (nullable) |
| `level` | VARCHAR(10) | info, warn, error |
| `message` | TEXT | Зміст рядка логу |
| `created_at` | TIMESTAMPTZ | Час запису логу |

Індекси: `idx_dev_tasks_status`, `idx_dev_tasks_created_at`, `idx_dev_task_logs_task_id`, `idx_dev_task_logs_task_created`

## Технічний стек
- PHP 8.5 + Symfony 7
- Doctrine DBAL 4 (PostgreSQL)
- Apache (Docker)
- Traefik роутинг на порті **8088**
- Claude Opus 4.6 через LiteLLM (OpenRouter)
- `gh` CLI для створення GitHub PR

## Автентифікація

A2A ендпоінт захищений заголовком `X-Platform-Internal-Token`. Адмін-сторінки захищені edge auth middleware Core (Traefik `edge-auth`).

```bash
curl -X POST http://localhost:8088/api/v1/a2a \
  -H "Content-Type: application/json" \
  -H "X-Platform-Internal-Token: ${APP_INTERNAL_TOKEN}" \
  -d '{"intent": "dev.list_tasks", "payload": {}}'
```

## Життєвий цикл задачі

```
draft → [уточнення Opus] → pending → running → success / failed
                                                     │
                                                     └→ PR створено (при успіху, якщо GH_TOKEN задано)
```

1. **Draft** — користувач створює задачу з назвою та описом
2. **Уточнення** (опціонально) — багатокроковий чат з Claude Opus 4.6 уточнює специфікацію
3. **Pending** — задача в черзі для фонового worker'а pipeline
4. **Running** — `pipeline.sh` виконується через `proc_open`, логи стрімляться в БД
5. **Success/Failed** — pipeline завершено, логи збережено, статус оновлено
6. **PR** — при успіху, `git push` + `gh pr create`

## SSE Live Логи

Агент надає Server-Sent Events для стрімінгу логів pipeline в реальному часі:

```
GET /admin/tasks/api/{id}/logs/stream?last_id=0
```

- `Content-Type: text/event-stream`
- Кожен запис логу надсилається як JSON data event
- Heartbeat кожні 15 секунд
- `event: complete` коли задача досягає фінального статусу
- Клієнт перепідключається використовуючи `last_id` з останнього отриманого event'у

## Змінні оточення

| Змінна | Опис | За замовчуванням |
|---|---|---|
| `DATABASE_URL` | Рядок підключення Postgres | `postgresql://dev_agent:dev_agent@postgres:5432/dev_agent` |
| `LITELLM_BASE_URL` | URL LiteLLM proxy | `http://litellm:4000` |
| `LITELLM_API_KEY` | API ключ LiteLLM | `dev-key` |
| `LLM_MODEL` | Модель для уточнення задач | `claude-opus-4-6` |
| `REPO_ROOT` | Корінь репозиторію для виконання pipeline | `/repo` |
| `GH_TOKEN` | GitHub токен для створення PR | (порожній — PR пропускається) |

## Команди Makefile
- `make dev-agent-setup` — збірка контейнера та встановлення залежностей
- `make dev-agent-install` — встановлення PHP залежностей
- `make dev-agent-migrate` — запуск Doctrine міграцій
- `make dev-agent-test` — запуск Codeception тестів
- `make dev-agent-analyse` — PHPStan аналіз (рівень 8)
- `make dev-agent-cs-check` / `make dev-agent-cs-fix` — перевірка/виправлення стилю коду

## Адмін-панель

### Список задач (`/admin/tasks`)
- Рядок статистики: всього, активних, успішних, невдалих, чернеток (за 7 днів)
- Фільтрована таблиця: Всі / Виконуються / Успішні / Невдалі / Чернетки
- В кожному рядку: id, назва, бейдж статусу, гілка, тривалість, посилання на PR, дата

### Створення задачі (`/admin/tasks/create`)
- Форма з назвою та описом
- Опції pipeline: пропустити архітектора, увімкнути аудитора, автозапуск
- "Уточнити з Opus" — відкриває чат-інтерфейс
- "Прийняти та створити задачу" — зберігає уточнену специфікацію та створює задачу

### Деталі задачі (`/admin/tasks/{id}`)
- Картка метаданих: статус, гілка, pipeline ID, тривалість, посилання на PR
- Картка специфікації: уточнена специфікація або початковий опис
- Панель live-логів: моноширинний переглядач з SSE автооновленням
- Кнопка "Запустити Pipeline" (для чернеток/невдалих задач)
