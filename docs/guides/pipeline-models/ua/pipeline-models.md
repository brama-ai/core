# Політика маршрутизації моделей для Pipeline

## Правила

### Пріоритет провайдерів

1. **Direct providers** — anthropic, openai, google, minimax (прямий API ключ)
2. **OpenCode Zen** (`opencode/*`) / **OpenCode Go** (`opencode-go/*`)
3. **OpenRouter** — **ТІЛЬКИ безкоштовні моделі** (суфікс `:free`)

### Tier policy

- `tier1` — довгі та найскладніші agentic-задачі
- `tier2` — сильний збалансований workhorse
- `fast` — низька затримка для масових перевірок
- `free` — OpenCode Zen free + OpenRouter free резерв, щоб пайплайн не зупинявся

Primary-моделі варто навмисно розносити між провайдерами. Це зменшує шанс, що короткі rate limits одного провайдера зупинять весь пайплайн.
Виключення для Google: Gemini не ставимо primary на довгі high-fanout фази, але використовуємо як primary для one-shot задач, де він особливо сильний: writing та analysis.

### Заборони

- **НІКОЛИ** не використовувати `openrouter/anthropic/*`, `openrouter/openai/*` — це платні моделі через посередника
- **НІКОЛИ** не використовувати `openrouter/google/*` якщо є прямий `google/*`
- OpenRouter = тільки community та open-source моделі з суфіксом `:free`

### Правило 6 провайдерів

Кожен агент має брати моделі з цього core-набору провайдерів:

| # | Провайдер | Приклад моделей |
|---|-----------|-----------------|
| 1 | **anthropic** | `claude-opus-4-6`, `claude-sonnet-4-6` |
| 2 | **openai** | `gpt-5.4`, `gpt-5.3-codex`, `gpt-5.2` |
| 3 | **google** | `gemini-3.1-pro-preview`, `gemini-3-flash-preview`, `gemini-3.1-flash-lite-preview` |
| 4 | **minimax** | `MiniMax-M2.7`, `MiniMax-M2.7-highspeed`, `MiniMax-M2.5-highspeed` |
| 5 | **opencode-go** | `glm-5`, `kimi-k2.5` |
| 6 | **opencode** (Zen) | `big-pickle`, `gpt-5-nano`, `minimax-m2.5-free` |
| 7 | **openrouter** (:free) | `openrouter/free`, `deepseek-r1-0528:free`, `qwen3-coder:free` |

### Тіри за ролями

| Роль | Primary | Fallback (openai → google → minimax → zen → openrouter:free) |
|------|---------|--------------------------------------------------------------|
| Sisyphus | `opencode-go/glm-5` | claude-opus-4-6, gpt-5.4, M2.7, big-pickle, gemini-3.1-pro-preview, openrouter/free |
| Architect | `anthropic/claude-opus-4-6` | gpt-5.4, glm-5, M2.7, gemini-3.1-pro-preview, big-pickle, openrouter/free |
| Coder | `anthropic/claude-sonnet-4-6` | M2.7, gpt-5.3-codex, glm-5, gemini-3.1-pro-preview, big-pickle, qwen3-coder:free |
| Reviewer | `minimax/MiniMax-M2.7` | gpt-5.4, glm-5, big-pickle, gemini-3.1-pro-preview, qwen3-coder:free |
| Tester | `opencode-go/kimi-k2.5` | gpt-5.3-codex, M2.7-highspeed, big-pickle, gemini-3.1-pro-preview, qwen3-coder:free |
| Auditor | `anthropic/claude-opus-4-6` | gpt-5.4, glm-5, M2.7, big-pickle, gemini-3.1-pro-preview, openrouter/free |
| Validator | `minimax/MiniMax-M2.5-highspeed` | gpt-5.2, kimi-k2.5, minimax-m2.5-free, gemini-3.1-flash-lite-preview, deepseek-r1-qwen3-8b:free |
| Documenter | `openai/gpt-5.4` | claude-sonnet-4-6, gemini-3-flash-preview, M2.5, kimi-k2.5, big-pickle, openrouter/free |
| Summarizer | `openai/gpt-5.4` | claude-opus-4-6, gemini-3.1-pro-preview, M2.7, glm-5, big-pickle, deepseek-r1-0528:free |

## Ultraworks Agent Matrix

| Агент | Workflow | Primary | Fallback 1 | Fallback 2 | Fallback 3 | Призначення |
|------|----------|---------|------------|------------|------------|-------------|
| `sisyphus` | `Ultraworks only` | `opencode-go/glm-5` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `minimax/MiniMax-M2.7` | оркестрація повного автоматичного пайплайну |
| `s-architect` | `Ultraworks` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | OpenSpec, архітектура, технічний план |
| `s-coder` | `Ultraworks` | `anthropic/claude-sonnet-4-6` | `minimax/MiniMax-M2.7` | `openai/gpt-5.3-codex` | `opencode-go/glm-5` | основна реалізація коду |
| `s-reviewer` | `Ultraworks only` | `minimax/MiniMax-M2.7` | `openai/gpt-5.4` | `opencode-go/glm-5` | `opencode/big-pickle` | safe refactor, SOLID/DRY/KISS покращення |
| `s-validator` | `Ultraworks` | `minimax/MiniMax-M2.5-highspeed` | `openai/gpt-5.2` | `opencode-go/kimi-k2.5` | `opencode/minimax-m2.5-free` | static analysis, CS/PHPStan, auto-fix |
| `s-tester` | `Ultraworks` | `opencode-go/kimi-k2.5` | `openai/gpt-5.3-codex` | `minimax/MiniMax-M2.7-highspeed` | `opencode/big-pickle` | тести, test fixes, CUJ/E2E мислення |
| `s-auditor` | `Ultraworks` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | audit, compliance, quality gate |
| `s-documenter` | `Ultraworks` | `openai/gpt-5.4` | `anthropic/claude-sonnet-4-6` | `google/gemini-3-flash-preview` | `minimax/MiniMax-M2.5` | оновлення документації |
| `s-summarizer` | `Ultraworks` | `openai/gpt-5.4` | `anthropic/claude-opus-4-6` | `google/gemini-3.1-pro-preview` | `minimax/MiniMax-M2.7` | фінальний аналіз і summary |

## Builder Agent Matrix

| Агент | Workflow | Primary | Fallback 1 | Fallback 2 | Fallback 3 | Призначення |
|------|----------|---------|------------|------------|------------|-------------|
| `planner` | `Builder only` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | аналіз задачі та вибір профілю |
| `architect` | `Builder` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | OpenSpec, архітектура, технічний план |
| `coder` | `Builder` | `anthropic/claude-sonnet-4-6` | `minimax/MiniMax-M2.7` | `openai/gpt-5.3-codex` | `opencode-go/glm-5` | основна реалізація коду |
| `validator` | `Builder` | `minimax/MiniMax-M2.5-highspeed` | `openai/gpt-5.2` | `opencode-go/kimi-k2.5` | `opencode/minimax-m2.5-free` | static analysis, CS/PHPStan, auto-fix |
| `tester` | `Builder` | `opencode-go/kimi-k2.5` | `openai/gpt-5.3-codex` | `minimax/MiniMax-M2.7-highspeed` | `opencode/big-pickle` | тести, test fixes, CUJ/E2E мислення |
| `auditor` | `Builder` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | audit, compliance, quality gate |
| `documenter` | `Builder` | `openai/gpt-5.4` | `anthropic/claude-sonnet-4-6` | `google/gemini-3-flash-preview` | `minimax/MiniMax-M2.5` | оновлення документації |
| `summarizer` | `Builder` | `openai/gpt-5.4` | `anthropic/claude-opus-4-6` | `google/gemini-3.1-pro-preview` | `minimax/MiniMax-M2.7` | фінальний аналіз і summary |

## Конфігурація

### oh-my-opencode.jsonc

Конфіг субагентів Sisyphus pipeline. Кожен субагент має:
- `model` — primary модель
- `fallback_models` — впорядкований масив fallback-ів, зазвичай 5 записів для покриття решти провайдерів

### ultraworks-monitor.sh

Функція `_detect_model()` підбирає найкращу доступну модель для Sisyphus orchestrator при запуску через `opencode run`.

### Перевірка доступних моделей

```bash
# Всі моделі
opencode models

# Тільки безкоштовні openrouter
opencode models | grep ':free'

# Direct providers
opencode models | grep -v '^openrouter/'
```

## Додавання нової моделі

1. Перевірити доступність: `opencode models | grep "model-name"`
2. Визначити тір за якістю
3. Додати в `oh-my-opencode.jsonc` для відповідних агентів
4. Додати в `_detect_model()` в `ultraworks-monitor.sh` якщо це orchestrator-level модель
5. OpenRouter — тільки якщо має `:free` суфікс
