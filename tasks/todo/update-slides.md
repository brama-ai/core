<!-- priority: 6 -->
# Оновити слайди на основі нових фіч та виправити галюцінації

Слайди (`slides/slides.md`) — Slidev-презентація про AI Community Platform. З моменту створення реалізовано кілька великих фіч і додано нового агента (dev-agent), але слайди не оновлені. Також є фактичні помилки.

## Вимоги

### 1. Виправити фактичні помилки (Flask → FastAPI)

news-maker-agent використовує **FastAPI**, не Flask. Помилка зустрічається у трьох місцях:

- Діаграма мікросервісів (~рядок 733): `NMA["news-maker-agent\n· Python/Flask"]` → `Python/FastAPI`
- Q&A слайд (~рядок 1000): `Python<br/>Flask` → `Python<br/>FastAPI`
- Code example (~рядки 395-409): декоратор `@app.route("/api/v1/a2a", methods=["POST"])` — це Flask-синтаксис, замінити на FastAPI-еквівалент (`@app.post("/api/v1/a2a")` з FastAPI router)

### 2. Додати dev-agent у всі діаграми та таблиці

Новий агент `dev-agent` (PHP/Symfony) існує в `apps/dev-agent/` але відсутній у слайдах.

**Manifest:** `apps/dev-agent/src/Controller/Api/ManifestController.php`
**Skills:** `dev.create_task`, `dev.run_pipeline`, `dev.get_status`, `dev.list_tasks`
**Опис:** Development orchestration — створення задач з LLM refinement, запуск мультиагентного пайплайну, SSE стрімінг логів, авто-створення PR.

Де додати:
- **Архітектурна діаграма** (~рядок 131, subgraph Agents): додати `DevAgent["dev-agent\n· PHP"]` поруч з іншими агентами
- **Таблиця "Наші агенти"** (~рядок 839): додати рядок `dev-agent | PHP | Development orchestration | dev.create_task, .run_pipeline, .get_status, .list_tasks`
- **Діаграма мікросервісів** (~рядок 730): додати `DA["dev-agent\n· PHP/Symfony"]` в Backend Layer + `DevUI["Dev Admin\n· Symfony Twig"]` в Frontend Layer + `Admin -->|iframe| DevUI` та `Core <-->|A2A| DA`

### 3. Оновити roadmap "Що далі?" (~рядки 929-985)

Чотири пункти з "В розробці" та "Заплановано" вже реалізовані (є в `tasks/done/`):
- Central Scheduler → `tasks/done/central-scheduler-system.md`
- Workflow Engine → `tasks/done/workflow-orchestration.md`
- Deep Crawling → `tasks/done/news-maker-deep-crawling.md`
- Discussion Summarization → `tasks/done/discussion-summarization.md`

Замінити 3 колонки roadmap:

**Колонка 1 — "Реалізовано" (зелена):**
- Central Scheduler — cron-задачі з manifest
- Workflow Engine — мультикрокові сценарії
- Deep Crawling — 2-рівневий парсинг
- Discussion Summarization
- Dev Agent — orchestration через web UI

**Колонка 2 — "Заплановано" (синя):**
- Agent Marketplace (залишити)
- Anti-fraud signals agent (залишити)
- Human-in-the-Loop Queue — черга схвалення контенту (з `tasks/todo/human-in-the-loop-queue.md`)

**Колонка 3 — "Візія" (зелена) — залишити як є**

### 4. Перевірити решту слайдів

- Впевнитись що кількість агентів у тексті (де згадується) відповідає реальності (тепер 6, не 5)
- Слайд "Pipeline: 5 агентів, одне завдання" (~рядок 770) — це про pipeline stages, не про кількість агентів, тому заголовок ОК
- Перевірити що `news.curate`, `news.publish` — актуальні skills (вже підтверджено)

## Контекст

- Слайди: `slides/slides.md` (Slidev формат, ~1013 рядків)
- dev-agent manifest: `apps/dev-agent/src/Controller/Api/ManifestController.php`
- Реалізовані задачі: `tasks/done/central-scheduler-system.md`, `tasks/done/workflow-orchestration.md`, `tasks/done/news-maker-deep-crawling.md`, `tasks/done/discussion-summarization.md`
- Заплановані задачі: `tasks/todo/human-in-the-loop-queue.md`
- news-maker-agent framework: FastAPI (`apps/news-maker-agent/requirements.txt`, `apps/news-maker-agent/app/main.py`)

## Обмеження

- Не змінювати структуру Slidev (frontmatter, layout директиви, v-click анімації)
- Зберегти українську мову презентації
- Mermaid діаграми повинні залишатись валідними (перевірити синтаксис)
- Не додавати нових слайдів — оновити існуючі
