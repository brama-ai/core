# Builder Pipeline Agents

Комплексна документація про агентів builder pipeline - як вони працюють, де знаходяться їх конфігурації, і як додати власного агента.

## Огляд системи

Builder pipeline використовує **8 спеціалізованих AI агентів**, кожен з яких виконує певну роль в процесі розробки:

```
Task → Planner → Architect → Coder → Validator → Tester → Documenter → Summarizer
                     ↓
                  Auditor (optional)
```

### Агенти за ролями

| Агент | Роль | Вендор | Модель |
|-------|------|--------|--------|
| **planner** | Аналізує задачу, обирає profile та список агентів | Anthropic | claude-opus-4-6 |
| **architect** | Створює OpenSpec proposal (specs, design, tasks) | Anthropic | claude-opus-4-6 |
| **coder** | Пише код на основі specs | Anthropic | claude-sonnet-4-6 |
| **auditor** | Quality gate — перевіряє якість agent-related змін | Anthropic | claude-opus-4-6 |
| **validator** | Запускає PHPStan, CS-Fixer, виправляє помилки | MiniMax | MiniMax-M2.5-highspeed |
| **tester** | Запускає тести, пише нові, виправляє failures | OpenCode Go | kimi-k2.5 |
| **documenter** | Пише білінгвальну документацію (UA+EN) | OpenAI | gpt-5.4 |
| **summarizer** | Створює фінальний звіт про виконану роботу | OpenAI | gpt-5.4 |

### Profiles (обирає planner)

| Profile | Коли використовується | Агенти |
|---------|----------------------|--------|
| **docs-only** | Тільки документація, README | documenter → summarizer |
| **quality-gate** | PHPStan/CS-Fixer/тести, без нового коду | coder → validator → summarizer |
| **tests-only** | Написати тести для існуючого коду | coder → tester → summarizer |
| **quick-fix** | Дрібні правки, 1-3 файли | coder → validator → summarizer |
| **standard** | Звичайна фіча, один app | coder → validator → tester → summarizer |
| **standard+docs** | Фіча + документація | coder → validator → tester → documenter → summarizer |
| **complex** | Multi-service, міграції, API зміни | coder → validator → tester → summarizer |
| **complex+agent** | Зміни що торкаються агентів | coder → auditor → validator → tester → summarizer |

> **Примітка:** Planner може створити довільний список агентів через `agents` поле в plan.json. Profiles — це лише стартові шаблони.

## Структура файлів агента

Кожен агент має **два файли конфігурації**:

### 1. Agent definition (`.opencode/agents/<agent>.md`)

**Призначення:** Системний промпт агента + технічні параметри для OpenCode CLI.

**Формат:**
```markdown
---
description: "Short description of agent role"
mode: primary
model: claude-sonnet-4-20250514
temperature: 0.1
tools:
  read: true
  write: true
  edit: true
  bash: true
  glob: true
  grep: true
  list: true
---

You are the **Agent Name** agent for the AI Community Platform.

## Your Role

[Detailed role description]

## Workflow

1. Step 1
2. Step 2
...

## Rules

- Rule 1
- Rule 2
...

## Handoff

Update `.opencode/pipeline/handoff.md` — **Agent Name** section with:
- Key information to pass to next agent
```

**Важливі поля:**
- `model:` - ID моделі (див. `opencode models`)
- `temperature:` - 0-2, контролює креативність (0 = детермінований, 2 = креативний)
- `tools:` - які інструменти доступні агенту

**Приклад:** [.opencode/agents/coder.md](.opencode/agents/coder.md)

### 2. Agent model config (`.opencode/agents.yaml`)

**Призначення:** Конфігурація моделей (primary + fallback) для всіх агентів в одному файлі.

**Формат:**
```yaml
agent_name:
  primary: claude-opus-4-20250514
  fallback: openrouter/google/gemini-2.0-flash-exp,free,cheap
  description: Опис агента
```

**Приклад:** [.opencode/agents.yaml](.opencode/agents.yaml)

## Де знаходяться файли

```
.opencode/agents/
├── architect.md          # Системний промпт architect
├── coder.md             # Системний промпт coder
├── documenter.md        # Системний промпт documenter
├── planner.md           # Системний промпт planner
├── summarizer.md        # Системний промпт summarizer
├── tester.md            # Системний промпт tester
├── validator.md         # Системний промпт validator
└── agents.yaml          # Конфігурація моделей для всіх агентів

builder/
├── pipeline.sh          # Головний orchestrator
├── pipeline-batch.sh    # Паралельне виконання через git worktrees
├── agents-config.sh     # CLI для управління моделями
└── validate-config.sh   # Валідація конфігурації
```

## Системні промпти агентів

### Анатомія системного промпту

Кожен системний промпт в `.opencode/agents/<agent>.md` містить:

1. **Your Role** - хто ти і що робиш
2. **Workflow** - покрокова інструкція виконання задачі
3. **Tech Stack Reference** - інформація про технології проекту
4. **Rules** - заборони та обмеження
5. **Handoff** - що передати наступному агенту

### Приклад: Coder agent

```markdown
You are the **Coder** agent for the AI Community Platform.

## Your Role

You implement code changes based on approved OpenSpec proposals.
You write clean, minimal, production-ready code.

## Workflow

1. Read `.opencode/pipeline/handoff.md` for context from architect
2. Read the full proposal: `openspec/changes/<id>/proposal.md`
3. Implement tasks from `tasks.md` sequentially
4. Follow existing codebase patterns
5. Keep edits minimal and focused

## Rules

- Follow existing code conventions
- Do NOT add unnecessary abstractions
- Do NOT over-engineer
- Write tests alongside code when specs require them
```

### Ключові принципи системних промптів

✅ **DO:**
- Чітко описуй роль і обов'язки
- Давай покрокові інструкції (numbered lists)
- Вказуй конкретні файли для читання
- Обмежуй scope агента (що він НЕ робить)
- Вказуй формат output (handoff секція)

❌ **DON'T:**
- Не роби промпт занадто довгим (> 100 рядків)
- Не дублюй інформацію між агентами
- Не давай абстрактних інструкцій ("зроби якісно")
- Не описуй інструменти (agent знає їх сам)

## Конфігурація Fallback моделей

### Як працює fallback chain

Коли primary модель недоступна (rate limit, API error), pipeline автоматично пробує fallback моделі **в порядку черги**:

```yaml
coder:
  primary: claude-opus-4-20250514
  fallback: openrouter/google/gemini-2.0-flash-thinking-exp,free,cheap
```

**Fallback chain:**
1. `claude-opus-4-20250514` (primary)
2. `openrouter/google/gemini-2.0-flash-thinking-exp` (fallback #1)
3. `free` → розгортається в список безкоштовних моделей
4. `cheap` → розгортається в список дешевих моделей

### Virtual models

**`free`** - безкоштовні моделі через OpenRouter:
```yaml
virtual_models:
  free:
    - openrouter/deepseek/deepseek-chat:free
    - openrouter/google/gemini-2.0-flash-exp:free
    - openrouter/meta-llama/llama-3.3-70b-instruct:free
    - openrouter/qwen/qwen3-235b-a22b:free
    - openrouter/mistralai/mistral-small-3.2-24b-instruct:free
```

**`cheap`** - дешеві моделі (< $1 per 1M tokens):
```yaml
virtual_models:
  cheap:
    - openrouter/deepseek/deepseek-v3.2        # $0.30/1M
    - openrouter/google/gemini-2.0-flash-thinking-exp  # $0.10/1M
    - openrouter/qwen/qwen3-coder              # $0.15/1M
    - openrouter/google/gemini-2.5-flash-lite  # $0.08/1M
```

### Зміна fallback моделі через CLI

```bash
# Показати поточну конфігурацію
./builder/agents-config.sh show

# Змінити primary модель для агента
./builder/agents-config.sh set coder claude-opus-4-20250514

# Змінити fallback chain
yq eval -i '.coder.fallback = "gemini-2.5-pro,free,cheap"' .opencode/agents.yaml

# Перевірити конфігурацію
./builder/validate-config.sh
```

### Стратегії fallback

`.opencode/agents.yaml` містить готові стратегії:

**1. Optimal (за замовчуванням)** - Claude для якості, fallback на free:
```yaml
strategies:
  optimal:
    coder: claude-opus-4-20250514,gemini-2.0-flash-thinking,free
    validator: claude-sonnet-4-20250514,free,cheap
```

**2. Free-only** - тільки безкоштовні моделі:
```yaml
strategies:
  free_only:
    coder: free
    validator: free
```

**3. Subscription-only** - тільки Claude (якщо є підписка):
```yaml
strategies:
  subscription_only:
    coder: claude-opus-4-20250514
    validator: claude-sonnet-4-20250514
```

**Змінити стратегію:**
```bash
./builder/agents-config.sh strategy free_only
```

## Як додати нового агента

### Крок 1: Визначити роль агента

Задай собі питання:
- Що робить цей агент?
- Яку проблему він вирішує?
- Де він стоїть в pipeline? (після якого агента, перед яким)
- Які інструменти йому потрібні? (read, write, edit, bash, grep, glob)
- Який output він передає наступному агенту?

**Приклад:** Додаємо `reviewer` агента - code review перед тестуванням.

### Крок 2: Створити agent definition

Створи файл `.opencode/agents/reviewer.md`:

```markdown
---
description: "Reviewer agent: performs code review and suggests improvements"
mode: primary
model: claude-sonnet-4-20250514
temperature: 0.2
tools:
  read: true
  grep: true
  glob: true
  list: true
---

You are the **Reviewer** agent for the AI Community Platform.

## Your Role

You perform code review on changes made by the coder agent. You identify:
- Code quality issues (complexity, duplication)
- Potential bugs and edge cases
- Security vulnerabilities
- Performance concerns

You do NOT write code - only review and suggest improvements.

## Workflow

1. Read `.opencode/pipeline/handoff.md` to know which files were changed
2. Read each modified file and analyze:
   - Correctness (does it match the spec?)
   - Readability (is it easy to understand?)
   - Maintainability (is it easy to change?)
   - Security (are there vulnerabilities?)
3. Check for common issues:
   - SQL injection, XSS vulnerabilities
   - Unhandled errors and exceptions
   - Race conditions in async code
   - Missing validation of user input
4. Suggest specific improvements with file:line references

## Output Format

Write review comments in `.opencode/pipeline/review.md`:

```markdown
## Critical Issues (must fix)

- [apps/core/src/Foo.php:42] SQL injection vulnerability - use prepared statements
- [apps/core/src/Bar.php:15] Unhandled exception in async operation

## Suggestions (nice to have)

- [apps/core/src/Baz.php:30] Extract complex logic into separate method
```

## Rules

- Focus on correctness and security first, style second
- Reference specific lines: `file.php:line`
- Suggest fixes, don't just complain
- If no issues found, say so explicitly

## Handoff

Update `.opencode/pipeline/handoff.md` — **Reviewer** section with:
- Critical issues count
- Suggestions count
- Overall code quality assessment (good/acceptable/needs work)
```

### Крок 3: Додати конфігурацію моделі

Додай агента в `.opencode/agents.yaml`:

```yaml
reviewer:
  primary: claude-sonnet-4-20250514
  fallback: openrouter/google/gemini-2.5-pro,free,cheap
  description: Code review та пошук проблем
```

І в кожну стратегію:

```yaml
strategies:
  optimal:
    reviewer: claude-sonnet-4-20250514,gemini-2.5-pro,free
  free_only:
    reviewer: free
  subscription_only:
    reviewer: claude-sonnet-4-20250514
```

### Крок 4: Інтегрувати в pipeline

Відредагуй `builder/pipeline.sh`:

**4.1. Додай timeout для агента:**
```bash
PIPELINE_TIMEOUT_REVIEWER="${PIPELINE_TIMEOUT_REVIEWER:-600}"  # 10 min
```

**4.2. Додай fallback конфігурацію:**
```bash
FALLBACK_REVIEWER="${PIPELINE_FALLBACK_REVIEWER:-claude-sonnet-4-20250514,gemini-2.5-pro,free,cheap}"
```

**4.3. Додай агента в порядок виконання:**
```bash
# Agent order
AGENTS=(architect coder reviewer validator tester summarizer)
```

**4.4. Додай в profiles (якщо потрібно):**

Відредагуй `.opencode/pipeline/profiles.json`:

```json
{
  "standard": {
    "agents": ["architect", "coder", "reviewer", "validator", "tester", "summarizer"],
    "description": "Standard feature with code review"
  },
  "complex": {
    "agents": ["architect", "coder", "reviewer", "auditor", "validator", "tester", "summarizer"],
    "description": "Complex change with review and audit"
  }
}
```

### Крок 5: Тестування

```bash
# 1. Валідація конфігурації
./builder/validate-config.sh

# 2. Тестова задача
echo "# Test reviewer

Simple test to verify reviewer agent works.

Create file test.txt with content 'hello'." > builder/tasks/todo/test-reviewer.md

# 3. Запуск pipeline
./builder/pipeline.sh --task-file builder/tasks/todo/test-reviewer.md

# 4. Перевірка результату
cat .opencode/pipeline/handoff.md  # Чи є секція Reviewer?
cat .opencode/pipeline/review.md   # Чи створив reviewer свій output?
```

### Крок 6: Документація

Оновити документи:
- `builder/AGENTS.md` (цей файл) - додати в таблицю агентів
- `docs/setup/QUICKSTART.md` - згадати нового агента якщо він критичний
- `builder/skill/SKILL.md` - оновити список агентів для builder-agent skill

## Best Practices для створення агентів

### 1. Принцип Single Responsibility

✅ **DO:** Один агент = одна чітка роль
```
coder: пише код
validator: перевіряє код
tester: тестує код
```

❌ **DON'T:** Агент робить все
```
mega-agent: пише код, тестує, деплоїть, пише docs
```

### 2. Четкий Input/Output через Handoff

Кожен агент **читає** handoff попереднього агента і **пише** свою секцію:

```markdown
## Coder

Files changed:
- apps/core/src/Foo.php (created)
- apps/core/src/Bar.php (modified)

Migrations:
- apps/core/migrations/Version20260318000001.php

## Validator

PHPStan: ✅ PASS (0 errors)
CS-Check: ✅ PASS (0 violations)

Files fixed: none
```

### 3. Fallback chain: від якісних до дешевих

```yaml
# ✅ GOOD: якість → швидкість → ціна
primary: claude-opus-4
fallback: claude-sonnet-4,gemini-2.5-pro,free,cheap

# ❌ BAD: одразу дешеві
primary: free
fallback: cheap
```

**Reasoning:** Primary має бути найякіснішим, fallback - поступово дешевшим.

### 4. Temperature за типом задачі

```yaml
# Детермінований output (code, validation)
coder:
  temperature: 0.1

validator:
  temperature: 0

# Креативний output (architecture, design)
architect:
  temperature: 0.3

# Аналітичний output (review, summary)
reviewer:
  temperature: 0.2
```

### 5. Мінімальний набір tools

Давай агенту **тільки потрібні** інструменти:

```yaml
# ✅ Validator: тільки читання + bash для запуску phpstan
validator:
  tools:
    read: true
    bash: true
    edit: true  # для фіксу помилок

# ❌ Validator з зайвими tools
validator:
  tools:
    read: true
    write: true   # не потрібен
    bash: true
    websearch: true  # не потрібен
```

## Troubleshooting

### Проблема: "Model not found"

**Причина:** model ID в `.opencode/agents/<agent>.md` не існує.

**Рішення:**
```bash
# 1. Перевірити доступні моделі
opencode models | grep claude

# 2. Використати точний ID з списку
# ✅ anthropic/claude-sonnet-4-20250514
# ❌ anthropic/claude-sonnet-4-6

# 3. Оновити agent file
vim .opencode/agents/coder.md
# model: claude-opus-4-20250514
```

### Проблема: Agent не отримує context з попереднього агента

**Причина:** Попередній агент не оновив handoff.

**Рішення:** Додай в системний промпт попереднього агента:

```markdown
## Handoff

Update `.opencode/pipeline/handoff.md` — **Your Agent** section with:
- Key information for next agent
- Status of your work
- Any blockers or issues
```

### Проблема: Fallback не спрацьовує

**Причина:** Fallback models не в `.opencode/agents.yaml`.

**Рішення:**
```bash
# Перевірити конфігурацію
./builder/agents-config.sh show

# Додати fallback
yq eval -i '.your_agent.fallback = "gemini-2.5-pro,free,cheap"' .opencode/agents.yaml

# Валідувати
./builder/validate-config.sh
```

### Проблема: Agent занадто повільний

**Причина:** Використовується велика модель (claude-opus) для простої задачі.

**Рішення:** Використай меншу модель:

```yaml
# Для простих задач (validation, testing)
validator:
  primary: claude-sonnet-4-20250514  # швидша, дешевша

# Для складних задач (architecture, coding)
architect:
  primary: claude-opus-4-20250514  # якісніша, повільніша
```

## Корисні команди

```bash
# Показати конфігурацію всіх агентів
./builder/agents-config.sh show

# Валідувати конфігурацію
./builder/validate-config.sh

# Перелік доступних моделей
opencode models

# Змінити модель для агента
./builder/agents-config.sh set coder claude-opus-4-20250514

# Експортувати конфігурацію для pipeline
./builder/agents-config.sh export

# Змінити стратегію
./builder/agents-config.sh strategy free_only

# Тест pipeline з одним агентом
./builder/pipeline.sh --only coder "Test task"
```

## Додаткові ресурси

- **Provider setup:** [docs/setup/ai-providers.md](../docs/setup/ai-providers.md)
- **Gemini strategy:** [docs/setup/gemini-strategy.md](../docs/setup/gemini-strategy.md)
- **Quickstart:** [docs/setup/QUICKSTART.md](../docs/setup/QUICKSTART.md)
- **OpenSpec conventions:** [openspec/AGENTS.md](../openspec/AGENTS.md)
- **Builder skill:** [.claude/skills/builder-agent/skill.md](../.claude/skills/builder-agent/skill.md)

---

**Підтримка:** Якщо є питання або знайшли баг - створи issue або питай в чаті платформи.
