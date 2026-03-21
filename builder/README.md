# Builder Agent

Автономний мульти-агентний білдер для виконання задач через пайплайн.

## Як це працює

1. **Skill** створює файл задачі в `builder/tasks/todo/`
2. **Monitor** виявляє нову задачу та запускає worker
3. **Pipeline** (`builder/pipeline.sh`) виконує задачу через послідовність агентів:
   Architect → Coder → Validator → Tester → Summarizer
4. Результат — коміти на гілці `pipeline/<slug>`, summary в `builder/tasks/summary/`

## Встановлення (після git clone)

```bash
./builder/setup.sh
# або
make builder-setup
```

Скрипт створює `builder/tasks/` директорії (todo, in-progress, done, failed, summary, artifacts, archive).

Симлінки в `scripts/` для зворотної сумісності вже включені в репо.

## Структура

```
builder/
  README.md                     # цей файл (загальний огляд)
  AGENTS.md                     # 📖 Документація про агентів
  PROVIDERS.md                  # 📖 Налаштування AI провайдерів
  setup.sh                      # створює gitignored директорії
  pipeline.sh                   # основний оркестратор агентів
  pipeline-batch.sh             # пакетний запуск задач
  pipeline-stats.sh             # статистика виконання
  agents-config.sh              # CLI для управління моделями агентів
  validate-config.sh            # валідація конфігурації
  skill/                        # Claude skill для створення задач
    SKILL.md
    references/example-task.md
  monitor/                      # TUI монітор
    pipeline-monitor.sh         # bash TUI (основний)
    pipeline-monitor-ink.sh     # Node.js wrapper
    ink/                        # React ink монітор
  tasks/                        # черга задач (gitignored, створюється setup.sh)
    todo/ in-progress/ done/ failed/ summary/ artifacts/ archive/
```

## Документація

- **[AGENTS.md](AGENTS.md)** - Детальна документація про агентів:
  - Як працюють агенти
  - Де знаходяться системні промпти
  - Як конфігурувати fallback моделі
  - Як додати свого агента

- **[PROVIDERS.md](PROVIDERS.md)** - Налаштування AI провайдерів (Claude, Codex, OpenRouter, Gemini)

- **[docs/setup/QUICKSTART.md](../docs/setup/QUICKSTART.md)** - Швидкий старт для нових користувачів

- **[docs/setup/ai-providers.md](../docs/setup/ai-providers.md)** - Покрокове налаштування провайдерів

- **[docs/setup/gemini-strategy.md](../docs/setup/gemini-strategy.md)** - Оптимізація витрат через Gemini

## Перевірка оточення (env-check)

Перед запуском pipeline рекомендується перевірити оточення:

```bash
./builder/env-check.sh
```

### Параметри env-check.sh

| Параметр | Опис |
|----------|------|
| `--app <name>` | Перевірити вимоги для конкретного app (можно вказати кілька разів) |
| `--json` | Вивести JSON-звіт у stdout |
| `--report-file <path>` | Записати звіт у файл (за замовчуванням: `.opencode/pipeline/env-report.json`) |
| `--quiet` | Приховати вивід для людей (тільки JSON) |
| `--help` | Показати довідку |

### Коди виходу

| Код | Значення |
|-----|----------|
| `0` | Всі перевірки пройшли успішно |
| `1` | Є попередження (pipeline може продовжити з обмеженнями) |
| `2` | Критична помилка (pipeline повинен зупинитися) |

### Приклади

```bash
# Перевірити глобальні вимоги
./builder/env-check.sh

# Перевірити вимоги для конкретного app
./builder/env-check.sh --app core

# Перевірити кілька apps
./builder/env-check.sh --app core --app knowledge-agent

# Отримати JSON-звіт
./builder/env-check.sh --json

# Записати звіт у файл
./builder/env-check.sh --app news-maker-agent --report-file /tmp/env.json
```

### Інтеграція з pipeline

`builder/pipeline.sh` автоматично запускає env-check перед виконанням задач. Результат записується у handoff:

```markdown
## Environment
**Runtime Versions**: PHP 8.5, Python 3.12, Node 22
**Services**: PostgreSQL 16, Redis 7
**OpenCode Providers**: 2+
**Check Status**: pass — All 12 checks passed
```

Глобальний env-check тепер також перевіряє `opencode auth list` і очікує щонайменше `2` налаштовані провайдери OpenCode (`Credentials` + `Environment` сумарно).

### Реєстр вимог

Файл `builder/env-requirements.json` оголошує вимоги для кожного app:

```json
{
  "global": {
    "tools": ["git", "jq"],
    "services": ["postgresql", "redis"]
  },
  "apps": {
    "core": {
      "runtime": "php",
      "min_version": "8.5",
      "extensions": ["json", "mbstring", "xml", "pdo_pgsql"]
    }
  }
}
```

Дивіться [docs/guides/env-checker](../docs/guides/env-checker/) для повної документації.

---

## Використання

### Створити задачу

Через Claude: скажіть "делегувати білдеру" або "на білдера".

Вручну: створіть `.md` файл в `builder/tasks/todo/`:

```markdown
<!-- priority: 1 -->
# Назва задачі

Опис що потрібно зробити.

## Validation

- PHPStan level 8 passes
- CS-Fixer passes
```

### Моніторинг

```bash
./builder/monitor/pipeline-monitor.sh
```

Клавіші: `[s]` старт, `[k]` зупинити, `[f]` повторити failed, `[+/-]` пріоритет, `[q]` вийти.

### Web UI в Core

Для builder workflow тепер доступний Core admin UI:

- `/admin/coder` — список задач, статуси, воркери, активність
- `/admin/coder/create` — створення задачі з шаблону
- `/admin/coder/{id}` — деталі задачі, stage timeline, live logs

Поточна реалізація працює в режимі сумісності:

- Core UI зберігає стан у БД
- `builder/tasks/*` і `.opencode/pipeline/*` лишаються runtime-шаром для існуючих скриптів
- `pipeline-monitor.sh` лишається підтриманим fallback-інструментом для операторів

### Прямий запуск

```bash
# одна задача
./builder/pipeline.sh "Add feature X"
make pipeline TASK="Add feature X"

# пакетний запуск
./builder/pipeline-batch.sh builder/tasks/
make pipeline-batch FILE=builder/tasks/

# статистика
./builder/pipeline-stats.sh
```

## Конфігурація

| Змінна | За замовчуванням | Опис |
|--------|-----------------|------|
| `MONITOR_WORKERS` | `1` | Кількість паралельних workers |
| `MONITOR_AUTOSTART` | `true` | Автозапуск при появі задач |
| `MONITOR_LOG_RETENTION` | `7` | Днів зберігання логів |

## Зворотна сумісність

Симлінки в `scripts/` дозволяють старим посиланням працювати:

```
scripts/pipeline.sh           → builder/pipeline.sh
scripts/pipeline-batch.sh     → builder/pipeline-batch.sh
scripts/pipeline-stats.sh     → builder/pipeline-stats.sh
scripts/pipeline-monitor.sh   → builder/monitor/pipeline-monitor.sh
```
