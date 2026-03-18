# Gemini Models - Стратегія Використання

Аналіз де Gemini моделі найкорисніші як основні або резервні.

## Доступні Gemini Моделі

### Безкоштовні (через OpenRouter)
- `openrouter/google/gemini-2.0-flash-exp:free` - Gemini 2.0 Flash (experimental, free tier)

### Платні (через OpenRouter)
- `openrouter/google/gemini-2.5-pro` - найкращий, повільніше, дорожче
- `openrouter/google/gemini-2.5-flash` - швидкий, збалансований
- `openrouter/google/gemini-2.5-flash-lite` - дуже швидкий, дешевий
- `openrouter/google/gemini-3-flash-preview` - preview Gemini 3.0
- `openrouter/google/gemini-3.1-pro-preview` - preview Gemini 3.1 Pro

### Прямий API (якщо налаштовано GEMINI_API_KEY)
- `google/gemini-2.0-flash-exp` - безкоштовно
- `google/gemini-2.0-flash-thinking-exp` - з reasoning, безкоштовно
- `google/gemini-2.5-pro` - найкращий

---

## Переваги Gemini

### 1. **Ціна/Якість Ratio** 💰
- Gemini 2.5 Flash: ~$0.10-0.15 per 1M tokens
- Claude Sonnet: ~$3.00 per 1M tokens
- **20-30x дешевше за Claude!**

### 2. **Швидкість** ⚡
- Flash моделі дуже швидкі (1-2 секунди на відповідь)
- Добре для швидких перевірок (validator, tester)

### 3. **Безкоштовний Tier** 🎁
- `gemini-2.0-flash-exp:free` через OpenRouter
- `gemini-2.0-flash-thinking-exp` через direct API
- Підходить для fallback

### 4. **Довгий Context** 📚
- Gemini 2.5 Pro: до 2M tokens context
- Claude Opus: 200K tokens
- **10x більше context!**

### 5. **Multimodal** 🖼️
- Gemini native підтримує зображення, відео, аудіо
- Може бути корисно для аналізу скриншотів, діаграм

---

## Недоліки Gemini

### 1. **Якість Reasoning** 🤔
- Claude краще для складного reasoning
- Gemini може пропускати edge cases

### 2. **Код Generation** 💻
- Claude/GPT кращі для складного коду
- Gemini добрий для простих task'ів

### 3. **Інструкції** 📝
- Claude краще слідує складним інструкціям
- Gemini може "фантазувати"

### 4. **API Stability** 🔧
- Experimental моделі можуть змінюватися
- Claude API стабільніший

---

## Рекомендована Стратегія по Агентам

### ✅ Gemini як **Primary** (Дешевше, Достатньо Якісно)

#### 1. **Validator** (перевірка коду)
```yaml
validator:
  primary: openrouter/google/gemini-2.5-flash  # швидко + дешево
  fallback: claude-sonnet-4-20250514,free,cheap
```
**Чому:**
- Validator робить просту перевірку (PHPStan, CS-Fixer)
- Не потрібен складний reasoning
- Швидкість важлива
- **Економія: 20x дешевше**

#### 2. **Tester** (запуск тестів)
```yaml
tester:
  primary: openrouter/google/gemini-2.5-flash
  fallback: claude-sonnet-4-20250514,free,cheap
```
**Чому:**
- Аналіз test output не дуже складний
- Швидкість важлива (тести можуть бути довгими)
- **Економія: 20x дешевше**

#### 3. **Summarizer** (фінальний звіт)
```yaml
summarizer:
  primary: openrouter/google/gemini-2.5-flash
  fallback: claude-sonnet-4-20250514,free,cheap
```
**Чому:**
- Summarization - проста задача
- Gemini добре summarize
- **Економія: 20x дешевше**

---

### ⚖️ Gemini як **Fallback** (Backup після Claude)

#### 4. **Architect** (планування)
```yaml
architect:
  primary: claude-sonnet-4-20250514
  fallback: openrouter/google/gemini-2.5-pro,free,cheap
```
**Чому:**
- Планування потребує якісного reasoning → Claude primary
- Gemini 2.5 Pro як fallback (все ще дешевше, довгий context)
- Безкоштовні моделі як останній resort

#### 5. **Coder** (написання коду)
```yaml
coder:
  primary: claude-opus-4-20250514
  fallback: openrouter/google/gemini-2.5-pro,openrouter/google/gemini-2.5-flash,free
```
**Чому:**
- Складний код → Claude Opus primary
- Gemini 2.5 Pro як fallback (може справитися з простішими задачами)
- Gemini 2.5 Flash для дуже простого коду
- Безкоштовні як останній resort

#### 6. **Documenter** (документація)
```yaml
documenter:
  primary: claude-opus-4-20250514
  fallback: openrouter/google/gemini-2.5-pro,free,cheap
```
**Чому:**
- Документація потребує якості → Claude primary
- Gemini 2.5 Pro добрий для summarization і writing
- Довгий context корисний для великих файлів

---

### 🎯 Gemini як **Co-Primary** (Паралельно з Claude)

#### 7. **Auditor** (quality gate)
```yaml
auditor:
  primary: claude-sonnet-4-20250514
  fallback: openrouter/google/gemini-2.5-pro,openrouter/google/gemini-2.5-flash,free,cheap
```
**Чому:**
- Quality check потребує уваги → Claude primary
- Але Gemini може знайти інші проблеми (різна перспектива)
- Два AI краще за один!

---

### ❌ Gemini НЕ рекомендується як Primary

#### 8. **Planner** (аналіз задачі)
```yaml
planner:
  primary: claude-sonnet-4-20250514  # НЕ gemini!
  fallback: openrouter/google/gemini-2.5-pro,free,cheap
```
**Чому:**
- Planning потребує глибокого reasoning
- Claude краще розуміє контекст проекту
- Помилка в плануванні → вся задача провалюється

---

## Оптимальні Конфігурації

### Стратегія 1: **Максимальна Економія** 💰
Gemini для всіх швидких агентів, Claude тільки для складних:

```yaml
architect: claude-sonnet → gemini-2.5-pro → free
coder: claude-opus → gemini-2.5-pro → gemini-2.5-flash → free
validator: gemini-2.5-flash → free → claude-sonnet  # Gemini ПЕРШИЙ!
tester: gemini-2.5-flash → free → claude-sonnet     # Gemini ПЕРШИЙ!
documenter: claude-opus → gemini-2.5-pro → free
auditor: claude-sonnet → gemini-2.5-pro → free
summarizer: gemini-2.5-flash → free → claude-sonnet # Gemini ПЕРШИЙ!
planner: claude-sonnet → gemini-2.5-pro → free
```

**Очікувана економія: 50-60% витрат** (validator, tester, summarizer використовують дешевий Gemini)

---

### Стратегія 2: **Максимальна Якість** 🏆
Claude primary всюди, Gemini тільки як fallback:

```yaml
architect: claude-sonnet → gemini-2.5-pro → free
coder: claude-opus → gemini-2.5-pro → free
validator: claude-sonnet → gemini-2.5-flash → free
tester: claude-sonnet → gemini-2.5-flash → free
documenter: claude-opus → gemini-2.5-pro → free
auditor: claude-sonnet → gemini-2.5-pro → free
summarizer: claude-sonnet → gemini-2.5-flash → free
planner: claude-sonnet → gemini-2.5-pro → free
```

**Результат: найкраща якість, але дорожче**

---

### Стратегія 3: **Hybrid (Рекомендована)** ⚖️
Gemini для простих задач, Claude для складних:

```yaml
architect: claude-sonnet → gemini-2.5-pro → free → cheap
coder: claude-opus → gemini-2.5-pro → free
validator: gemini-2.5-flash → claude-sonnet → free  # ⭐ Gemini primary!
tester: gemini-2.5-flash → claude-sonnet → free     # ⭐ Gemini primary!
documenter: claude-opus → gemini-2.5-pro → free
auditor: claude-sonnet → gemini-2.5-pro → free
summarizer: gemini-2.5-flash → claude-sonnet → free # ⭐ Gemini primary!
planner: claude-sonnet → gemini-2.5-pro → free
```

**Результат: оптимальний баланс якості і ціни (30-40% економії)**

---

## Як Налаштувати Gemini Primary

### Варіант 1: Вручну (edit agents.yaml)

```bash
# Edit config
vim .opencode/agents.yaml

# Змініть validator, tester, summarizer:
validator:
  primary: openrouter/google/gemini-2.5-flash
  fallback: claude-sonnet-4-20250514,free,cheap
```

### Варіант 2: CLI (agents-config.sh)

```bash
# Встановити Gemini для конкретного агента
./builder/agents-config.sh set validator openrouter/google/gemini-2.5-flash
./builder/agents-config.sh set tester openrouter/google/gemini-2.5-flash
./builder/agents-config.sh set summarizer openrouter/google/gemini-2.5-flash

# Validate
./builder/agents-config.sh validate

# Export
./builder/agents-config.sh export > .env.agents
source .env.agents
```

### Варіант 3: Створити Gemini Strategy

Додайте в `.opencode/agents.yaml`:

```yaml
strategies:
  gemini_hybrid:
    architect: claude-sonnet-4-20250514,openrouter/google/gemini-2.5-pro,free
    coder: claude-opus-4-20250514,openrouter/google/gemini-2.5-pro,free
    validator: openrouter/google/gemini-2.5-flash,claude-sonnet-4-20250514,free
    tester: openrouter/google/gemini-2.5-flash,claude-sonnet-4-20250514,free
    documenter: claude-opus-4-20250514,openrouter/google/gemini-2.5-pro,free
    auditor: claude-sonnet-4-20250514,openrouter/google/gemini-2.5-pro,free
    summarizer: openrouter/google/gemini-2.5-flash,claude-sonnet-4-20250514,free
    planner: claude-sonnet-4-20250514,openrouter/google/gemini-2.5-pro,free
```

Потім:
```bash
./builder/agents-config.sh strategy gemini_hybrid
```

---

## Моніторинг Ефективності

Після налаштування Gemini, відстежуйте:

### 1. Cost Savings
```bash
# Monitor показує витрати по моделях
./builder/monitor/pipeline-monitor.sh

# Порівняйте витрати до/після
cat .opencode/pipeline/reports/batch_*.md | grep "Cost:"
```

### 2. Quality
```bash
# Перевірте чи validator пропускає помилки
./builder/pipeline.sh "test task" --audit

# Порівняйте audit results
```

### 3. Speed
```bash
# Час виконання агентів
cat builder/tasks/done/*.md | grep "duration:"
```

---

## Висновок

### Найкращі Use Cases для Gemini Primary:

1. ✅ **Validator** - швидкий, простий, не критичний
2. ✅ **Tester** - швидкий, аналіз output
3. ✅ **Summarizer** - summarization задача, Gemini добрий

### Найкращі Use Cases для Gemini Fallback:

4. ✅ **Architect** - Claude primary, Gemini довгий context як backup
5. ✅ **Coder** - Claude primary, Gemini для простих задач
6. ✅ **Documenter** - Claude primary, Gemini для summarization

### НЕ рекомендується Gemini:

7. ❌ **Planner** - потребує найкращого reasoning

---

## Next Steps

1. **Спробуйте hybrid стратегію** (30-40% економії)
2. **Моніторьте результати** (якість vs ціна)
3. **Adjust по потребі** (якщо Gemini validator пропускає багато → повертайте Claude)

**Recommended:** Почніть з Gemini для validator/tester/summarizer → найменший ризик, відчутна економія!
