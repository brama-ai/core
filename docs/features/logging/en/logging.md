# Централізоване логування

## Огляд

Платформа використовує OpenSearch 2.11.1 як єдине сховище логів для всіх додатків. Кожен додаток пише логи напряму в OpenSearch через HTTP `_bulk` API без посередників (Filebeat, Logstash тощо).

```
  core / knowledge-agent / hello-agent / news-maker-agent
       │  Monolog Handler (PHP)  │  Python Handler  │
       └──────── HTTP POST ──────┘─────────────────┘
                          ▼
              OpenSearch 2.11.1
              Index: platform_logs_YYYY_MM_DD
                          ▲
              Admin UI: /admin/logs
              Cleanup: logs:cleanup (cron)
```

**Ключові принципи:**

- Логуємо якомога більше — краще мати зайвий лог, ніж не мати потрібного
- Silent fail — логування ніколи не ламає додаток
- Trace propagation — кожен запит має trace_id для відстеження через сервіси
- Daily indices — швидке видалення старих логів (drop index замість delete_by_query)

## Trace ID та Request ID

Кожен HTTP запит отримує:

- **trace_id** — ідентифікатор ланцюга запитів через сервіси. Береться з `X-Trace-Id` header або генерується автоматично (UUID v4)
- **request_id** — унікальний ідентифікатор конкретного запиту

Ці ID додаються автоматично до кожного лог-запису та повертаються у response headers:

```
X-Trace-Id: 550e8400-e29b-41d4-a716-446655440000
X-Request-Id: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
```

При A2A виклику core → agent, trace_id передається далі, що дозволяє бачити повний ланцюг в `/admin/logs/trace/{traceId}`.

## Як писати логи

### PHP (Symfony)

Inject `Psr\Log\LoggerInterface` через конструктор — Symfony autowiring підставить Monolog logger автоматично:

```php
use Psr\Log\LoggerInterface;

final class MyService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function doSomething(): void
    {
        $this->logger->info('Processing started', [
            'tool' => $toolName,
            'input_size' => count($input),
        ]);

        try {
            // ... business logic
            $this->logger->info('Processing completed', [
                'duration_ms' => $durationMs,
                'result_status' => $status,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Processing failed', [
                'exception' => $e,
                'tool' => $toolName,
            ]);
            throw $e;
        }
    }
}
```

**Контекст додається автоматично:** trace_id, request_id, app_name, request_uri, request_method, client_ip — не потрібно передавати вручну.

**Exception logging:** передавайте exception об'єкт через ключ `'exception'` в контексті — handler автоматично витягне class, message та stack trace.

### Python (FastAPI)

Використовуйте стандартний `logging` модуль:

```python
import logging

logger = logging.getLogger(__name__)

def process_something(data: dict) -> dict:
    logger.info("Processing started", extra={"tool": tool_name})

    try:
        result = do_work(data)
        logger.info("Processing completed", extra={
            "duration_ms": duration_ms,
            "status": "ok",
        })
        return result
    except Exception as e:
        logger.error("Processing failed: %s", str(e), exc_info=True)
        raise
```

Trace context (trace_id, request_id) додається автоматично через `TraceMiddleware` та `OpenSearchHandler`.

### TypeScript / JavaScript (OpenClaw plugin)

Використовуйте `api.log` з OpenClaw plugin API:

```javascript
module.exports = function myPlugin(api) {
  const log = api.log || console;

  log.info?.("[my-plugin] Plugin loaded");

  // Логуємо операції
  log.info?.(`[my-plugin] Processing ${toolName}`);
  log.warn?.(`[my-plugin] Slow response: ${durationMs}ms`);
  log.error?.(`[my-plugin] Failed: ${error.message}`);
};
```

## Рівні логів

| Рівень      | Коли використовувати              | Приклади                                                                 |
| ----------- | --------------------------------- | ------------------------------------------------------------------------ |
| **DEBUG**   | Деталі для розробки і діагностики | Cache hit/miss, parameter parsing, query details                         |
| **INFO**    | Значимі операції та їх результати | Incoming request, A2A call completed, agent registered, discovery pushed |
| **WARNING** | Проблеми що не ламають роботу     | Agent disabled, health check failed, timeout, deprecated usage           |
| **ERROR**   | Помилки що потребують уваги       | HTTP failure, exception, invalid data, auth failure                      |

**Правило:** якщо сумніваєтесь між INFO та DEBUG — обирайте INFO. Краще мати більше логів.

## Що логуємо (best practices)

### Обов'язково логуємо:

- **Вхідні HTTP запити** — INFO: метод, URI, ключові параметри
- **A2A виклики** — INFO: який agent, який tool, duration, status
- **Зовнішні HTTP виклики** — INFO: URL, status code, duration
- **Зміни стану** — INFO: agent enabled/disabled, config updated, index created/deleted
- **Помилки** — ERROR: exception з повним stack trace та контекстом
- **Auth failures** — WARNING: unauthorized attempts
- **Health checks** — INFO: результати, WARNING: threshold breaches

### Корисно логувати:

- Cache hits/misses — DEBUG
- Discovery payload size — DEBUG
- Query parameters — DEBUG
- Slow operations (>1s) — WARNING

### Не логуємо:

- Паролі, токени, секрети
- Повний request/response body (тільки summary або розмір)
- PII без необхідності

## Structured Context

Завжди додавайте структурований контекст замість inline в повідомлення:

```php
// Правильно
$this->logger->info('A2A call completed', [
    'agent' => $agentName,
    'tool' => $tool,
    'duration_ms' => $durationMs,
    'status' => $status,
]);

// Неправильно
$this->logger->info("A2A call to {$agentName} for {$tool} took {$durationMs}ms with status {$status}");
```

Структурований контекст дозволяє шукати та фільтрувати логи в OpenSearch.

## Canonical Trace Step Event

Для кроків A2A/OpenClaw використовуйте уніфікований event-контракт у context:

- `event_name` — стабільний machine-readable ідентифікатор (`core.a2a.outbound.started`, `openclaw.discovery.snapshot`, ...)
- `step` — нормалізований крок (`discovery_fetch`, `invoke_request`, `tool_resolve`, `a2a_outbound`, `a2a_inbound`, `llm_call`)
- `source_app`, `target_app`
- `trace_id`, `request_id`, `agent_run_id` (якщо є)
- `tool`, `intent`
- `status` (`started|completed|failed|skipped`)
- `duration_ms`
- `error_code` (для failure)
- `sequence_order` (монотонний порядок подій)

Для drill-down:

- `step_input` — санітизований input кроку
- `step_output` — санітизований output кроку
- `request_headers` — санітизовані заголовки
- `capture_meta` — `{is_truncated, original_size_bytes, captured_size_bytes, redacted_fields_count, truncated_values_count}`

## Redaction Policy

Payload sanitizer обов'язково редагує ключі, що містять:

- `token`
- `authorization`
- `api_key` / `apikey`
- `secret`
- `password`
- `cookie`

Великі поля обрізаються, але структура payload зберігається. UI має показувати лише санітизовані дані.

## Admin UI

### Перегляд логів: `/admin/logs`

- **Пошук** — повнотекстовий по message, trace_id, URI
- **Фільтр по рівню** — DEBUG, INFO, WARNING, ERROR
- **Фільтр по додатку** — core, knowledge-agent, hello-agent, news-maker-agent
- **Фільтр по даті** — від/до
- **Пагінація** — 50 записів на сторінку
- **Візуальне групування**:
  - Логи, що розшарюють `trace_id`, мають підсвічуватись спільним кольором або індикатором.
  - У списку логів має бути помітним, чи належить лог до дочірнього `request_id`.
  - Відступи в списку мають візуально відображати ієрархію викликів (від батьківського сервісу до дочірнього), якщо це можливо визначити з контексту.

### Trace view: `/admin/logs/trace/{traceId}`

Показує всі логи одного trace відсортовані по часу.

- **Sequence Diagram (primary):** відображає виклики у форматі `source_app -> target_app` по структурованих trace event.
- **Click-to-inspect:** клік по події відкриває деталі `step_input`, `step_output`, `headers`, `capture_meta`, `error_code`, `exception`.

- **Групування та Відступи:** Логи мають бути згруповані по `request_id` (одному A2A виклику). Відступи зліва або лінійні маркери мають чітко показувати: "Хто викликав (наприклад, OpenClaw) → Що відповів Core → Як він передав виклик в Hello-agent".
- **Кольорова індикація:** Різні додатки (core, hello-agent) мають маркуватися різними кольорами для швидкого візуального сприйняття в ланцюгу `trace_id`.

### Налаштування: `/admin/settings`

- **Log Level** — мінімальний рівень логів для запису в OpenSearch
- **Retention Days** — скільки днів зберігати логи (default: 7)
- **Max Size GB** — максимальний розмір всіх індексів (default: 2)

## Index Management

### Структура індексів

Формат: `platform_logs_YYYY_MM_DD` (щоденна ротація).

Index template автоматично задає mapping для нових індексів.

### Команди

```bash
# Створити index template та сьогоднішній індекс
make logs-setup
# або
docker compose exec core php bin/console logs:index:setup

# Очистити старі індекси (щогодинний cron)
make logs-cleanup
# або
docker compose exec core php bin/console logs:cleanup

# Dry run — подивитись що буде видалено
docker compose exec core php bin/console logs:cleanup --dry-run

# Кастомні параметри
docker compose exec core php bin/console logs:cleanup --max-age=3 --max-size-gb=1
```

## Конфігурація

### PHP apps (`.env`)

```env
OPENSEARCH_URL=http://opensearch:9200
```

### services.yaml (core)

```yaml
parameters:
  app.log_app_name: "core" # або 'knowledge-agent', 'hello-agent'
  app.log_retention_days: 7
  app.log_max_size_gb: 2
```

### Python app (`config.py`)

```python
opensearch_url: str = "http://opensearch:9200"
```

## Архітектурні рішення

| Рішення                         | Причина                                          |
| ------------------------------- | ------------------------------------------------ |
| Raw HTTP замість opensearch-php | Не тягнемо великий клієнт в кожен додаток        |
| Буфер 50 записів                | Зменшуємо кількість HTTP запитів                 |
| Daily indices                   | Швидке видалення (drop index vs delete_by_query) |
| Silent fail                     | Логування не повинно ламати додаток              |
| JSON файл для settings          | Уникаємо DB запиту на кожен log emit             |
| Копія класів в кожен app        | Немає shared library pattern в проекті           |
