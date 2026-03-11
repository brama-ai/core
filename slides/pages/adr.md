---
theme: seriph
title: Architecture Decision Records - AI Community Platform
mdc: true
---

# Architecture Decision Records
## AI Community Platform

Курс: побудова AI-агентів на платформі

<!-- Пояснити формат: кожен ADR = контекст, рішення, альтернативи + trade-off. -->

---

# ADR-01: A2A Gateway як центральний хаб

## Контекст
- Потрібен єдиний маршрут для `OpenClaw -> Core -> Agent`
- Потрібно централізовано вести `trace_id`, аудит і помилки

## Рішення
```php
// apps/core/src/Controller/Api/A2AGateway/SendMessageController.php
$tool = (string) ($body['tool'] ?? '');
$result = $this->a2aClient->invoke($tool, $input, $traceId, $requestId);
```

## Trade-off
- Плюси: одна точка авторизації, rate-limit, спостережуваності
- Мінуси: додаткова латентність, SPOF (компенсується health checks)

---

# ADR-02: Мультимовний стек

## Контекст
- Core та knowledge-agent написані на `PHP/Symfony`
- News-maker-agent написаний на `Python/FastAPI`
- Потрібен спільний контракт при різних мовах

## Рішення
```dockerfile
# docker/core/Dockerfile
FROM php:8.5-apache

# docker/news-maker-agent/Dockerfile  
FROM python:3.12-slim
```

## Trade-off
- Плюси: best tool for the job, незалежність від мови
- Мінуси: різні тулчейни, CI і експертиза команди

---

# ADR-03: Doctrine DBAL без ORM

## Контекст
- Потрібен контроль над SQL і predictability запитів
- Важливо уникати зайвої магії ORM

## Рішення
```php
// apps/knowledge-agent/src/Repository/SourceMessageRepository.php
public function upsert(array $payload, string $requestId): string
{
    $id = $this->connection->fetchOne(<<<'SQL'
        INSERT INTO knowledge_source_messages (...) VALUES (...)
        ON CONFLICT (source_platform, chat_id, message_id)
        DO UPDATE SET ...
        RETURNING id
    SQL, [...]);
    return (string) $id;
}
```

## Trade-off
- Плюси: повний контроль над SQL, менше N+1
- Мінуси: більше boilerplate, ручні міграції

---

# ADR-04: LiteLLM як єдиний LLM gateway

## Контекст
- Різні агенти мають звертатись до LLM однаково
- Потрібні routing, retries, callbacks і cost/log tracing

## Рішення
```php
// apps/core/src/LLM/LiteLlmClient.php
$endpoint = rtrim($this->baseUrl, '/').'/v1/chat/completions';
$headers = [
  'Authorization: Bearer '.$this->apiKey,
  'X-Request-Id: '.$context->requestId,
];
```

## Trade-off
- Плюси: єдиний інтерфейс, ротація моделей, unified telemetry
- Мінуси: додатковий hop і залежність від LiteLLM

---

# ADR-05: Agent Card + Manifest для discovery

## Контекст
- Потрібен runtime-discovery без жорсткого hardcode
- Потрібна перевірка контрактів при реєстрації агентів

## Рішення
```php
// apps/knowledge-agent/src/Controller/Api/ManifestController.php
return $this->json([
  'name' => 'knowledge-agent',
  'version' => '1.0.0',
  'url' => 'http://knowledge-agent/api/v1/knowledge/a2a',
  'skills' => [
    ['id' => 'knowledge.search', 'name' => 'Knowledge Search'],
  ],
]);
```

## Trade-off
- Плюси: self-service для агентів, прозорий контракт
- Мінуси: cold start при первинному discover

---

# ADR-06: RabbitMQ для async workflows

## Контекст
- Knowledge extraction не повинен блокувати синхронний A2A-response
- Потрібні черги, retry і dead-letter для стабільності

## Рішення
```php
// apps/knowledge-agent/src/RabbitMQ/RabbitMQPublisher.php
$channel->queue_declare('knowledge.chunks', false, true, false, false, false, [
  'x-dead-letter-exchange' => ['S', 'knowledge.dlx'],
]);
```

## Trade-off
- Плюси: routing, DLQ, low-ops
- Мінуси: ще один сервіс у compose

---

# ADR-07: Traefik як reverse proxy + edge router

## Контекст
- Потрібно маршрутизувати багато сервісів через єдину edge-точку
- Потрібний Docker-native discovery і middleware chain
- **Всі інструменти мають бути захищені** — без логіну в адмінку нікуди не пускає

## Рішення
```yaml
# compose.core.yaml — edge-auth middleware
labels:
  - traefik.http.middlewares.edge-auth.forwardauth.address=http://core/edge/auth/verify
```

Кожен сервіс підключає `edge-auth@docker`:
- Traefik Dashboard, LiteLLM, агентські admin UI
- Без авторизованої сесії → 401/302 redirect на логін

## Trade-off
- Плюси: labels, автодискавері, єдина точка авторизації для всіх інструментів
- Мінуси: менше low-level контролю, залежність від Core для auth

---

# ADR-08: OpenSpec + Convention Tests

## OpenSpec: управління змінами

```
openspec/changes/<change-id>/
├── proposal.md    # Що і чому
├── design.md      # Як технічно
├── tasks.md       # Чекліст імплементації
└── specs/         # Файли що змінюються
```

## Convention tests: гарантія сумісності

```js
// tests/agent-conventions/tests/manifest_test.js
const res = await I.sendGetRequest('/api/v1/manifest');
assert.strictEqual(res.status, 200);
assert.ok(/^[a-z][a-z0-9-]*$/.test(res.data.name));
```

## Trade-off
- Плюси: формальний change-log + автоматичний compliance
- Мінуси: процесний overhead

---

# Summary: Всі ADR і вердикт

| ADR | Рішення | Вердикт |
|-----|---------|----------|
| ADR-01 | Core як A2A Gateway | ✅ Accepted |
| ADR-02 | Polyglot stack | ✅ Accepted |
| ADR-03 | Doctrine DBAL без ORM | ✅ Accepted |
| ADR-04 | LiteLLM gateway | ✅ Accepted |
| ADR-05 | Manifest-first discovery | ✅ Accepted |
| ADR-06 | RabbitMQ async | ✅ Accepted |
| ADR-07 | Traefik edge routing + auth | ✅ Accepted |
| ADR-08 | OpenSpec change management | ✅ Accepted |
| ADR-09 | Agent convention tests | ✅ Accepted |

<!-- Висновок: архітектура тримається на явних контрактах і централізованій спостережуваності -->
