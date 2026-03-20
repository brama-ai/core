# Пакетний запуск пайплайну

## Огляд

`pipeline-batch.sh` запускає список задач через мультиагентний пайплайн (architect → coder → validator → tester → documenter). Кожна задача виконується окремим запуском `pipeline.sh`.

Два формати введення:
- **Файл** (`tasks.txt`) — один рядок = одна задача (простий формат)
- **Папка** (`tasks/`) — один `.md` файл = задача з повним описом (kanban)

Для нічних запусків підтримується паралельне виконання: `--workers N` запускає до N задач одночасно через ізольовані git worktree.

## Швидкий старт

### Варіант 1: Папка задач (рекомендовано)

Кожна задача — окремий `.md` файл з розгорнутим промтом:

```
tasks/
├── todo/                         ← задачі, що чекають
│   ├── add-streaming-support.md
│   └── fix-session-timeout.md
├── in-progress/                  ← зараз виконується
├── done/                         ← завершені успішно
└── failed/                       ← завершені з помилкою
```

Формат файлу задачі (`tasks/todo/add-streaming-support.md`):

```markdown
# Add streaming support to A2A gateway

WebSocket streaming потрібен для real-time відповідей агентів.

## Вимоги
- Підтримка в knowledge-agent (пріоритет)
- Зворотна сумісність з HTTP polling
- Тести на streaming endpoint

## Контекст
- A2A протокол: docs/specs/en/a2a-protocol.md
- HTTP handler: apps/core/src/A2AGateway/A2AClient.php
```

Перший `# заголовок` = назва задачі (для логів, гілки). Весь файл = повний промт для агента.

Запуск:

```bash
# Послідовно
./scripts/pipeline-batch.sh tasks/

# Паралельно (2 воркери)
./scripts/pipeline-batch.sh --workers 2 tasks/

# Без аргументів — шукає tasks/ за замовчуванням
./scripts/pipeline-batch.sh
```

Файли автоматично переміщуються: `todo/` → `in-progress/` → `done/` (або `failed/`).
В `done/` та `failed/` додається мета-коментар з результатом:

```markdown
<!-- batch: 20260309_220000 | status: pass | duration: 1545s | branch: pipeline/add-streaming-support -->
# Add streaming support ...
```

### Варіант 2: Текстовий файл (простий)

```
# tasks.txt — один рядок = одна задача
Add streaming support to A2A gateway
Implement retry logic for LiteLLM client
# Рядки з # ігноруються
```

```bash
./scripts/pipeline-batch.sh tasks.txt
./scripts/pipeline-batch.sh --workers 2 --telegram tasks.txt
```

### Перевірити результати

Звіт зберігається в `.opencode/pipeline/reports/batch_<timestamp>.md`:

```markdown
| # | Task | Status | Duration | Branch |
|---|------|--------|----------|--------|
| 1 | Add streaming support | ✓ PASS | 1545s | `pipeline/add-streaming-support` |
| 2 | Implement retry logic | ✗ FAIL | 890s  | `pipeline/implement-retry-logic` |
```

## Паралельне виконання

### Як працює `--workers N`

```
tasks.txt (10 задач)
    │
    ├── worker-1 (git worktree) ──→ Task 1 ──→ Task 3 ──→ Task 5 ──→ ...
    │
    └── worker-2 (git worktree) ──→ Task 2 ──→ Task 4 ──→ Task 6 ──→ ...
```

Кожен воркер працює в ізольованому git worktree — окрема копія робочого дерева, спільна `.git` база. Це дозволяє паралельно створювати різні гілки та коміти без конфліктів.

Коли воркер закінчує задачу — він бере наступну з черги. Якщо одна задача довга, а інша коротка — воркери автоматично балансуються.

### Вибір кількості воркерів

Кількість воркерів залежить від лімітів API-провайдера:

| Провайдер | RPM ліміт | Рекомендовані воркери |
|-----------|-----------|----------------------|
| Claude (підписка) | 40–80 RPM | 2–3 |
| OpenRouter Free | 20 RPM | 1 |
| OpenRouter Paid | 200+ RPM | 3–5 |
| Codex | 60 RPM | 2–3 |

Практична порада: починайте з `--workers 2`, спостерігайте за 429 помилками в логах. Якщо помилок немає — можна додати ще.

### `--stop-on-failure` в паралельному режимі

При `--workers > 1` параметр `--stop-on-failure` ігнорується — всі задачі запускаються. Це пов'язано з тим, що зупинити паралельних воркерів атомарно складно, а для нічних запусків бажано виконати максимум задач.

## Нічний запуск

### Через `nohup` + `tmux`

```bash
# Варіант 1: nohup (найпростіше)
nohup ./scripts/pipeline-batch.sh --workers 2 --telegram tasks.txt \
  > batch.log 2>&1 &

# Варіант 2: tmux (можна підключитись і подивитись)
tmux new-session -d -s pipeline \
  './scripts/pipeline-batch.sh --workers 2 --telegram tasks.txt'
# Потім підключитись: tmux attach -t pipeline
```

### Через cron

```bash
# Щодня о 22:00
0 22 * * * cd /path/to/repo && ./scripts/pipeline-batch.sh \
  --workers 2 --telegram --no-stop-on-failure tasks.txt \
  >> /var/log/pipeline-batch.log 2>&1
```

### Telegram-сповіщення

Додайте `--telegram` для отримання сповіщень у Telegram на кожному етапі:
- Початок/завершення кожного агента
- Фінальний підсумок пакетного запуску

Потрібні змінні середовища: `PIPELINE_TELEGRAM_BOT_TOKEN` та `PIPELINE_TELEGRAM_CHAT_ID`.

### Моніторинг через Dev Reporter

Якщо dev-reporter-agent запущений, кожен pipeline автоматично відправляє результати через A2A. Переглядати історію та статистику можна на:

```
http://localhost:8087/admin/pipeline
```

## Фолбек моделей

Пайплайн використовує каскадну систему фолбеків:

```
Підписки (Claude, Codex)     ← вже оплачені, використовуються першими
    ↓ якщо 429/помилка
Безкоштовні (free tier)       ← без додаткових витрат
    ↓ якщо 429/помилка
Платні per-token (cheap tier) ← останній варіант, мінімальна ціна
```

Фолбеки налаштовуються через змінні середовища в `pipeline.sh`:

```bash
FALLBACK_ARCHITECT="claude-sonnet,gpt-5.3-codex,free,cheap"
FALLBACK_CODER="gpt-5.3-codex,claude-opus,free,cheap"
```

## Опції

| Опція | Опис |
|-------|------|
| `--workers N` | Кількість паралельних воркерів (за замовчуванням 1) |
| `--no-stop-on-failure` | Продовжити після помилки (у послідовному режимі) |
| `--skip-architect` | Пропустити етап архітектора |
| `--from <agent>` | Почати з конкретного агента |
| `--only <agent>` | Запустити тільки одного агента |
| `--audit` | Додати якісний аудит в кінці |
| `--telegram` | Telegram-сповіщення |
| `--webhook <url>` | Webhook-сповіщення |

## Приклади

### 10 задач на ніч з 2 воркерами

```bash
nohup ./scripts/pipeline-batch.sh \
  --workers 2 \
  --telegram \
  --no-stop-on-failure \
  tasks.txt > batch.log 2>&1 &
```

### Тільки код + тести (без архітектора)

```bash
./scripts/pipeline-batch.sh --workers 3 --skip-architect tasks.txt
```

### Продовжити з тестера для всіх задач

```bash
./scripts/pipeline-batch.sh --from tester tasks.txt
```

## Звіти

Кожен пакетний запуск генерує звіт:

```
.opencode/pipeline/reports/batch_20260308_220000.md
```

Структура звіту:

```markdown
# Batch Pipeline Results
- Started: 2026-03-08 22:00:00
- Total tasks: 10
- Workers: 2

| # | Task | Status | Duration | Branch |
|---|------|--------|----------|--------|
| 1 | ... | ✓ PASS | 1234s | ... |

## Summary
- Passed: 8/10
- Failed: 2/10
- Workers: 2
- Total duration: 14400s (240 min)
```

Окремі логи кожної задачі зберігаються в `.opencode/pipeline/logs/`.
