# Перевірка вимог оточення

## Призначення

Скрипт `builder/env-check.sh` перевіряє вимоги оточення перед запуском будь-якого агента pipeline. Він перевіряє:

- **Глобальні інструменти**: git, jq
- **Сервіси**: PostgreSQL, Redis
- **Провайдери OpenCode**: щонайменше 2 налаштовані провайдери, видимі в `opencode auth list`
- **Раннтайми**: PHP (>= 8.5), Python (>= 3.12), Node (>= 20)
- **Менеджери пакетів**: composer, npm, pip
- **Розширення PHP**: json, mbstring, xml, pdo_pgsql, intl, curl (налаштовується для кожного app)
- **Залежності app**: Налаштовані в `builder/env-requirements.json`

## Використання

### Базовий виклик

```bash
# Перевірити тільки глобальні вимоги
./builder/env-check.sh

# Перевірити вимоги для конкретного app
./builder/env-check.sh --app core

# Перевірити вимоги для декількох apps
./builder/env-check.sh --app core --app knowledge-agent
```

### Параметри командного рядка

| Параметр | Опис |
|----------|------|
| `--app <name>` | Перевірити вимоги для конкретного app (можна вказати декілька разів) |
| `--json` | Вивести JSON-звіт у stdout |
| `--report-file <path>` | Записати JSON-звіт у файл |
| `--quiet` | Приховати вивід для людей |
| `--help` | Показати довідку |

### Коди виходу

| Код | Значення | Поведінка pipeline |
|-----|----------|---------------------|
| `0` | Всі перевірки пройшли | Продовжити нормально |
| `1` | Тільки попередження | Продовжити з обмеженнями |
| `2` | Критичні помилки | Скасувати задачу негайно |

## Формати виводу

### Людино-читаний

```
Environment Check

Global checks
  ✓ git 2.43.0
  ✓ jq 1.7.1
  ✓ postgresql accepting connections
  ✓ redis PONG
  ✓ opencode_providers 7 configured (>= 2)

App: core (php >= 8.5)
  ✓ php_version 8.5.1 (>= 8.5)
  ✓ composer 2.8.1
  ✓ php_ext_json loaded
  ✓ php_ext_mbstring loaded

─────────────────────────────────────────
All 12 checks passed.
Report: .opencode/pipeline/env-report.json
```

### JSON-звіт

```json
{
  "timestamp": "2026-03-20T12:00:00Z",
  "exit_code": 0,
  "summary": "All 12 checks passed",
  "duration_ms": 1200,
  "checks": [
    {
      "name": "postgresql",
      "category": "service",
      "status": "pass",
      "detail": "PostgreSQL accepting connections",
      "required_by": ["global"]
    }
  ],
  "environment": {
    "php": "8.5.1",
    "python": "3.12.4",
    "node": "22.3.0",
    "composer": "2.8.1",
    "npm": "10.8.0",
    "opencode_providers": "7",
    "postgresql": "16.2",
    "redis": "7.2.5"
  }
}
```

## Реєстр вимог

`builder/env-requirements.json` оголошує вимоги для кожного app:

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
      "tools": ["composer"],
      "extensions": ["json", "mbstring", "xml", "pdo_pgsql", "intl", "curl"],
      "deps_check": "composer check-platform-reqs"
    },
    "news-maker-agent": {
      "runtime": "python",
      "min_version": "3.12",
      "tools": ["pip"],
      "deps_check": "pip check"
    }
  }
}
```

### Розширення для нових app

Щоб додати новий app:

1. Додайте запис у `builder/env-requirements.json`
2. Вкажіть `runtime`, `min_version`, `tools` та `extensions` за потреби
3. Опціонально додайте команду `deps_check` для перевірки залежностей

## Точки інтеграції

### Інтеграція з pipeline

`builder/pipeline.sh` запускає env-check автоматично після preflight:

```bash
preflight()      # Існуючий: перевіряє opencode, docker, git
env_check()      # НОВИЙ: перевіряє runtimes, services, per-app deps
setup_branch()   # Створює гілку pipeline
run_agents()     # Запускає послідовність агентів
```

### Збагачення handoff

При успіху env-check записує у `.opencode/pipeline/handoff.md`:

```markdown
## Environment

**Runtime Versions**: PHP 8.5, Python 3.12, Node 22
**Services**: PostgreSQL 16, Redis 7
**Check Status**: pass — All 12 checks passed
```

### Відображення в моніторі

Монітор pipeline читає `.opencode/pipeline/env-report.json` і показує компактний статус:

```
Env: PHP 8.5 | Python 3.12 | Node 22 | PG | Redis | 12/12 checks
```

## Обробка помилок

### Критичні помилки (exit 2)

Коли вимоги відсутні:
1. Pipeline генерує подію `ENV_FATAL`
2. Задача переміщується в `failed/` з метаданими оточення
3. Відправляється Telegram-повідомлення (якщо налаштовано)
4. Pipeline завершується з кодом 3

### Попередження (exit 1)

Коли некритичні інструменти відсутні:
1. Pipeline генерує подію `ENV_WARN`
2. Попередження записуються в handoff
3. Pipeline продовжує з обмеженими можливостями

### Пропуск env-check

Використайте прапор `--skip-env-check`:

```bash
./builder/pipeline.sh --skip-env-check "Опис задачі"
```

## Вирішення проблем

### "jq not found"

Встановіть jq:
```bash
apt install jq    # Ubuntu/Debian
brew install jq   # macOS
```

### "PostgreSQL not accepting connections"

Перевірте, чи працює PostgreSQL:
```bash
docker ps | grep postgres
# або
pg_isready
```

### "Redis not responding"

```bash
docker ps | grep redis
# або
redis-cli ping
```

### "PHP extension not loaded"

Увімкніть розширення в `php.ini`:
```bash
php -m | grep mbstring   # Перевірити чи завантажено
docker-php-ext-enable mbstring  # У контейнері
```

## Майбутнє розширення

Архітектура підтримує майбутній режим `--auto-fix`, який викликатиме навик `devcontainer-provisioner` для виправлення проблем.
