<!-- batch: 20260311_095618 | status: pass | duration: 1013s | branch: pipeline/implement-openspec-change-update-news-digest-manua -->
# Implement OpenSpec change: update-news-digest-manual-channel-delivery

Потрібно реалізувати ручний запуск digest з адмінки news-maker-agent з публікацією результату в канал через платформний маршрут `Core A2A -> OpenClaw`.

## Вимоги

### Manual trigger в адмінці
- Додати endpoint `POST /admin/trigger/digest` в `news-maker-agent`
- Додати кнопку в `admin/settings` для запуску digest вручну
- Запуск має бути фоновим (не блокувати HTTP-відповідь адмінки)

### Single-flight захист
- Додати lock/guard для ручного digest run
- Якщо digest вже виконується: другий клік не запускає новий run
- Логувати accepted/skipped outcome

### Публікація в канал через Core A2A
- Після успішного створення digest викликати `POST /api/v1/a2a/send-message`
- Викликати з `tool=openclaw.send_message`
- Передавати корисне повідомлення (title/body) + metadata (`digest_id`, `item_count`, source)
- Додати auth заголовок `Authorization: Bearer <OPENCLAW_GATEWAY_TOKEN>`

### Надійність
- Якщо outbound publish падає, digest у БД НЕ відкатується
- `curated_news_items` залишаються в `published` стані після успішної генерації digest
- Помилка доставки має логуватись з `trace_id/request_id`

### Тести
- Тест: manual trigger accepted
- Тест: manual trigger skipped при активному run
- Тест: successful manual digest -> 1 outbound publish request
- Тест: no eligible items -> publish request не викликається
- Тест: delivery failure -> digest збережений, warning зафіксований

## Контекст
- OpenSpec change:
  - `openspec/changes/update-news-digest-manual-channel-delivery/proposal.md`
  - `openspec/changes/update-news-digest-manual-channel-delivery/design.md`
  - `openspec/changes/update-news-digest-manual-channel-delivery/tasks.md`
- Spec deltas:
  - `openspec/changes/update-news-digest-manual-channel-delivery/specs/news-digest/spec.md`
  - `openspec/changes/update-news-digest-manual-channel-delivery/specs/news-digest-admin/spec.md`
- Поточний код:
  - `apps/news-maker-agent/app/routers/admin/settings.py`
  - `apps/news-maker-agent/templates/admin/settings.html`
  - `apps/news-maker-agent/app/services/scheduler.py`
  - `apps/news-maker-agent/app/services/digest.py`
  - `apps/news-maker-agent/app/config.py`

## Обмеження
- Не викликати Telegram API напряму з news-maker-agent; лише через Core A2A
- Не ламати існуючі manual trigger для crawl/cleanup
- Не міняти зовнішній API контракт Core (`/api/v1/a2a/send-message`)
- Після змін пройти OpenSpec валідацію для цього change і релевантні тести news-maker-agent
