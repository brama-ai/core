# Human-in-the-loop approval queue

Агенти генерують контент (news digests, knowledge entries, summaries) який публікується автоматично. Потрібна черга модерації де людина може переглянути, відредагувати та схвалити/відхилити результати перед публікацією.

## Вимоги

### Approval Queue в Core
- Центральна таблиця `approval_queue` в core database:
  - `id UUID PRIMARY KEY`
  - `agent_name VARCHAR(64) NOT NULL` — хто створив
  - `action_type VARCHAR(64) NOT NULL` — тип дії (publish_digest, add_knowledge, send_notification)
  - `title VARCHAR(256) NOT NULL` — короткий опис для модератора
  - `payload JSONB NOT NULL` — повний контент для перегляду
  - `preview_html TEXT` — rendered preview для UI (optional)
  - `status VARCHAR(32) DEFAULT 'pending'` — pending/approved/rejected/expired
  - `priority INTEGER DEFAULT 5` — 1-10, вище = важливіше
  - `reviewer VARCHAR(128)` — хто прийняв рішення
  - `reviewer_comment TEXT` — коментар модератора
  - `reviewed_at TIMESTAMPTZ`
  - `expires_at TIMESTAMPTZ` — auto-expire якщо не переглянуто (optional)
  - `callback_skill VARCHAR(128)` — A2A skill для виклику після рішення
  - `callback_payload JSONB DEFAULT '{}'` — додаткові дані для callback
  - `created_at TIMESTAMPTZ DEFAULT now()`
- Індекси: `(status, priority DESC, created_at)`, `(agent_name, status)`

### A2A Skill: `core.submit_for_approval`
- Input:
  - `action_type: string` (required)
  - `title: string` (required)
  - `payload: object` (required)
  - `preview_html: string` (optional)
  - `priority: integer` (optional, default 5)
  - `expires_in_hours: integer` (optional)
  - `callback_skill: string` (optional — skill для виклику після approve/reject)
  - `callback_payload: object` (optional)
- Output: `{ queue_id: string, status: "queued" }`

### A2A Skill: `core.approval_decision`
- Внутрішній skill для callback після рішення
- Викликається автоматично коли модератор approve/reject
- Якщо `callback_skill` задано — викликає агента через A2A з:
  - `decision: "approved" | "rejected"`
  - `reviewer_comment: string`
  - `original_payload: object`
  - `callback_payload: object`

### Admin UI
- Сторінка `/admin/approvals` — черга на модерацію
- Фільтри: agent, action_type, status, priority
- Для кожного item:
  - Preview контенту (rendered HTML або JSON)
  - Кнопки: Approve / Reject
  - Текстове поле для коментаря
  - Можливість редагування payload перед approve
- Badge з кількістю pending items в navigation
- Auto-refresh кожні 30 секунд

### Інтеграція з news-maker
- Як перший use case: коли rewriter генерує curated_news_item зі status=ready
- Замість автоматичної публікації — submit_for_approval з:
  - action_type: "publish_digest"
  - payload: { title, summary, body, source_url }
  - preview_html: rendered Telegram message format
  - callback_skill: "news_maker.publish" (буде створено пізніше)
- Це optional — контролюється через `AgentSettings.require_approval: bool`

### Expiration
- Якщо `expires_at` задано і час вийшов — автоматично змінити status на "expired"
- Cleanup command або scheduled job для перевірки expired items
- Якщо інтегрований з scheduler (задача central-scheduler-system) — зареєструвати як periodic job

### Тести
- Unit тест: створення approval item
- Unit тест: approve/reject flow з callback
- Unit тест: expiration logic
- Functional тест: submit_for_approval через A2A
- Functional тест: Admin UI approve flow

## Контекст

- A2AClient: `apps/core/src/A2AGateway/A2AClient.php`
- Admin controllers pattern: `apps/core/src/Controller/Admin/` — дивитись існуючі admin controllers
- Admin templates pattern: `apps/core/templates/admin/` — Twig templates
- News-maker rewriter: `apps/news-maker-agent/app/services/rewriter.py` — `run_rewriting()`
- AgentSettings pattern: `apps/news-maker-agent/app/models/models.py` — `AgentSettings` table

## Обмеження

- Approval queue живе в core, не в окремому мікросервісі
- Не блокувати агентів — submit_for_approval повертається одразу (async pattern)
- Payload JSONB — без обмежень на структуру (кожен action_type має свій формат)
- Preview rendering — відповідальність агента (core тільки зберігає і показує HTML)
- Максимум 1000 pending items (soft limit, логувати warning)
