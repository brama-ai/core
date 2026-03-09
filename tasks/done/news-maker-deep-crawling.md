<!-- batch: 20260308_213736 | status: pass | duration: 866s | branch: pipeline/news-maker-deep-crawling-2-level-depth -->
# News-maker: deep crawling (2-level depth)

Поточний кролер обробляє лише одну сторінку (base_url) кожного джерела, витягуючи до 20 посилань і статей. Це дає неглибоке покриття. Потрібно додати рекурсивний кролінг з глибиною 2: base_url → сторінка 1 → сторінка 2.

## Вимоги

### Рекурсивний кролінг
- Додати параметр `max_depth` (за замовчуванням 1, щоб зберегти поточну поведінку)
- При `max_depth=2`: після витягування посилань з base_url, кожне отримане посилання також обробляється `_extract_links()` для виявлення додаткових статей
- Обмеження ширини: `max_links_per_depth` — скільки посилань обходити на кожному рівні глибини (наприклад, 10 на рівень)
- Domain scoping: кролити лише в межах того самого домену/субдомену (вже є в `_extract_links()`)

### Зміни в базі даних
- Додати колонку `crawl_depth INTEGER DEFAULT 0` в таблицю `raw_news_items` — показує на якому рівні глибини знайдена стаття
- Додати колонку `discovered_from_url VARCHAR(1024)` — URL батьківської сторінки, з якої знайшли посилання
- Створити Alembic міграцію для цих змін

### Конфігурація
- Додати в `AgentSettings` (та адмін UI) параметри:
  - `crawl_max_depth` (1 або 2, за замовчуванням 1)
  - `crawl_max_links_per_depth` (за замовчуванням 10)
- Додати в `config.py` відповідні поля з default-значеннями

### Timebox і ресурси
- Збільшити `crawl_source_timebox_seconds` для глибоких кролів (наприклад, 240с замість 120с)
- Загальний `crawl_run_timebox_seconds` залишити 900с (15 хв)
- Додати лічильник HTTP-запитів на рівень глибини для логування

### Дедуплікація
- Поточна дедуплікація через `dedup_hash` (SHA256 URL) вже покриває випадок, коли одна стаття знайдена на різних рівнях глибини — перевірити що це працює коректно

### Тести
- Оновити існуючі тести в `tests/test_crawler.py`
- Додати тест на рекурсивний кролінг з mock-даними (2 рівні)
- Додати тест на обмеження глибини (не заходити на 3й рівень)
- Додати тест на domain scoping (не кролити зовнішні домени)

## Контекст

- Поточний кролер: `apps/news-maker-agent/app/services/crawler.py`
  - `run_crawl()` — основна функція (рядки 130-284)
  - `_extract_links()` — витягує посилання з HTML (рядки 287-322)
  - `_fetch_html()` — HTTP запит з таймаутом 20с (рядки 34-52)
  - `_extract_article()` — trafilatura extraction (рядки 92-123)
  - `MAX_LINKS = 20` (рядок 23)
- Моделі: `apps/news-maker-agent/app/models/models.py`
- Конфіг: `apps/news-maker-agent/app/config.py`
  - `crawl_max_links_per_source: int = 20`
  - `crawl_source_timebox_seconds: int = 120`
  - `crawl_run_timebox_seconds: int = 900`
- Міграції: `apps/news-maker-agent/alembic/versions/001_initial.py`
- Тести: `apps/news-maker-agent/tests/test_crawler.py`
- Scheduler: `apps/news-maker-agent/app/services/scheduler.py`

## Обмеження

- Не ламати існуючу поведінку з `max_depth=1` — всі поточні тести мають проходити
- Не змінювати API контракт (A2A endpoint)
- Trafilatura залишається основною бібліотекою для extraction
- Зберігати зворотну сумісність з поточною схемою `raw_news_items`
