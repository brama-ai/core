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

# Як читати цей deck

- `ADR-01..ADR-10` покривають ключові архітектурні вузли платформи
- На кожен ADR: `контекст` -> `рішення` -> `альтернативи`
- У snippet-ах використаний реальний код репозиторію

<!-- На цьому слайді коротко задати очікування та структуру презентації. -->

---

# ADR-01: A2A Gateway як центральний хаб
## Контекст + проблема

- Потрібен єдиний маршрут для `OpenClaw -> Core -> Agent`
- Потрібно централізовано вести `trace_id`, аудит і помилки
- Прямі виклики між агентами ускладнюють контроль і дебаг

<!-- Пояснити, що core виступає gateway і policy-enforcement точкою. -->

---

# ADR-01: Рішення + обґрунтування

```php
// apps/core/src/Controller/Api/A2AGateway/SendMessageController.php
$tool = (string) ($body['tool'] ?? '');
$input = (array) ($body['input'] ?? []);
$result = $this->a2aClient->invoke($tool, $input, $traceId, $requestId);

return $this->json(array_merge($result, [
    'trace_id' => $traceId,
    'request_id' => $requestId,
]));
```

```php
// apps/core/src/A2AGateway/A2AClient.php
foreach ($this->registry->findEnabled() as $agent) {
    $skillIds = ManifestValidator::extractSkillIds($manifest);
    if (in_array($tool, $skillIds, true)) {
        return $this->callAgent($agent, $manifest, $tool, $input, $traceId, $requestId, $actor);
    }
}
```

<!-- Наголосити: маршрутизація, аудит і trace робляться в одному місці. -->

---

# ADR-01: Альтернативи + trade-off

- Альтернатива: mesh між агентами (прямі peer-to-peer виклики)
- Плюси gateway: одна точка авторизації, rate-limit, спостережуваності
- Trade-off: додаткова латентність і ризик `single point of failure`

<!-- Пояснити, що SPOF компенсується health checks, retry і горизонтальним масштабуванням core. -->

---

# ADR-02: Мультимовний стек
## Контекст + проблема

- Core та knowledge-agent написані на `PHP/Symfony`
- News-maker-agent написаний на `Python/FastAPI`
- Потрібен спільний контракт при різних мовах

<!-- Розкрити мотив: best tool for the job без лому протоколів. -->

---

# ADR-02: Рішення + обґрунтування

```dockerfile
# docker/core/Dockerfile
FROM php:8.5-apache
```

```dockerfile
# docker/news-maker-agent/Dockerfile
FROM python:3.12-slim
```

```yaml
# compose.agent-news-maker.yaml
environment:
  LITELLM_BASE_URL: http://litellm:4000
  PLATFORM_CORE_URL: http://core
  APP_INTERNAL_TOKEN: dev-internal-token
```

<!-- Пояснити: мова різна, але A2A + internal token + infra контракти спільні. -->

---

# ADR-02: Альтернативи + trade-off

- Альтернатива: monostack (`all Python` або `all PHP`)
- Плюси обраного підходу: швидша розробка під конкретні задачі
- Trade-off: різні тулчейни, CI і експертиза команди

<!-- Зафіксувати, що стандартизація протоколу важливіша за уніфікацію мови. -->

---

# ADR-03: Doctrine DBAL без ORM
## Контекст + проблема

- Потрібний контроль над SQL і predictability запитів
- Агентам потрібні специфічні upsert/DDL сценарії
- Важливо уникати зайвої магії ORM на інфраструктурному рівні

<!-- Пояснити, що тут ставка на явний SQL і прозорість. -->

---

# ADR-03: Рішення + обґрунтування

```php
// apps/knowledge-agent/src/Repository/SourceMessageRepository.php
final class SourceMessageRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function upsert(array $payload, string $requestId, ?string $traceId = null): string
    {
        $id = $this->connection->fetchOne(<<<'SQL'
            INSERT INTO knowledge_source_messages (...) VALUES (...)
            ON CONFLICT (source_platform, chat_id, message_id)
            DO UPDATE SET ...
            RETURNING id
        SQL, [...]);

        return (string) $id;
    }
}
```

<!-- Підкреслити, що DBAL + SQL дають повний контроль над поведінкою та продуктивністю. -->

---

# ADR-03: Альтернативи + trade-off

- Альтернатива: повний ORM (entities/repositories)
- Плюси DBAL: менше прихованих запитів, простіше оптимізувати
- Trade-off: більше boilerplate, ручні SQL-міграції

<!-- Зауважити, що для platform-core це свідомий компроміс на користь керованості. -->

---

# ADR-04: OpenSearch замість Elasticsearch
## Контекст + проблема

- Потрібен search-engine для knowledge і log use-cases
- Важлива сумісність API і простий локальний запуск
- Потрібна відкрита модель ліцензування

<!-- Пояснити критерії: low-ops, open license, достатня функціональність. -->

---

# ADR-04: Рішення + обґрунтування

```yaml
# compose.yaml
opensearch:
  image: opensearchproject/opensearch:2.11.1
  environment:
    discovery.type: single-node
    DISABLE_SECURITY_PLUGIN: "true"
```

```php
// apps/core/src/AgentInstaller/Strategy/OpenSearchInstallStrategy.php
$indexName = sprintf('%s_%s', str_replace('-', '_', $agentName), $collection);
if (!$this->indexExists($indexName)) {
    $this->createIndex($indexName);
}
```

<!-- Наголосити на API-сумісності та простому provision/deprovision індексів. -->

---

# ADR-04: Альтернативи + trade-off

- Альтернатива: Elasticsearch, pgvector, Meilisearch
- Плюси OpenSearch: сумісний API, community-driven, open source
- Trade-off: окремий сервіс і додаткове адміністрування

<!-- Додати, що у MVP обрали найменш ризиковий шлях для команди. -->

---

# ADR-05: LiteLLM як єдиний LLM gateway
## Контекст + проблема

- Різні агенти мають звертатись до LLM однаково
- Потрібні routing, retries, callbacks і cost/log tracing
- Прямі SDK в кожному агенті дають дублікацію й хаос

<!-- Підвести до ідеї централізованого LLM proxy. -->

---

# ADR-05: Рішення + обґрунтування

```php
// apps/core/src/LLM/LiteLlmClient.php
$endpoint = rtrim($this->baseUrl, '/').'/v1/chat/completions';
$headers = [
  'Authorization: Bearer '.$this->apiKey,
  'X-Request-Id: '.$context->requestId,
  'X-Agent-Name: '.$context->agentName,
];
$result = file_get_contents($endpoint, false, $context);
```

```yaml
# docker/litellm/config.yaml
litellm_settings:
  success_callback: ["langfuse"]
  failure_callback: ["langfuse"]
```

<!-- Пояснити, що LiteLLM є policy/routing шаром, а не бізнес-логікою агента. -->

---

# ADR-05: Альтернативи + trade-off

- Альтернатива: прямі `openai/anthropic` SDK виклики в кожному агенті
- Плюси gateway: єдина точка контролю, fallback, unified telemetry
- Trade-off: додатковий hop і залежність від LiteLLM

<!-- Показати, що operational simplicity переважає невелике зростання latency. -->

---

# ADR-06: Agent Card + Manifest для discovery
## Контекст + проблема

- Потрібен runtime-discovery без жорсткого hardcode
- Потрібна перевірка контрактів при реєстрації агентів
- Потрібен version-aware і self-describing підхід

<!-- Сформулювати проблему динамічного складу агентів. -->

---

# ADR-06: Рішення + обґрунтування

```php
// apps/knowledge-agent/src/Controller/Api/ManifestController.php
return $this->json([
  'name' => 'knowledge-agent',
  'version' => '1.0.0',
  'url' => 'http://knowledge-agent/api/v1/knowledge/a2a',
  'skills' => [
    ['id' => 'knowledge.search', 'name' => 'Knowledge Search'],
    ['id' => 'knowledge.upload', 'name' => 'Knowledge Upload'],
  ],
]);
```

```php
// apps/core/src/A2AGateway/AgentCardFetcher.php
$url = sprintf('http://%s:%d/api/v1/manifest', $hostname, $port);
$raw = file_get_contents($url, false, $context);
```

<!-- Пояснити контракт: manifest-first discovery + валідація на core. -->

---

# ADR-06: Альтернативи + trade-off

- Альтернатива: Consul/etcd або hardcoded routing table
- Плюси manifest-first: self-service для агентів, прозорий контракт
- Trade-off: cold start і sync-затримка при первинному discover

<!-- Зазначити, що cache у core зменшує навантаження при повторних запитах. -->

---

# ADR-07: RabbitMQ для async workflows
## Контекст + проблема

- Knowledge extraction не повинен блокувати синхронний A2A-response
- Потрібні черги, retry і dead-letter для стабільності
- Синхронні LLM-виклики погіршують latency chat-флоу

<!-- Підкреслити, що асинхронність критична для UX і стабільності. -->

---

# ADR-07: Рішення + обґрунтування

```php
// apps/knowledge-agent/src/A2A/KnowledgeA2AHandler.php
foreach ($chunks as $chunk) {
    $chunk['meta'] = $meta;
    $this->publisher->publishChunk($chunk);
}

return [
  'status' => 'queued',
  'result' => ['chunks_queued' => count($chunks)],
];
```

```php
// apps/knowledge-agent/src/RabbitMQ/RabbitMQPublisher.php
$channel->exchange_declare('knowledge.direct', 'direct', false, true, false);
$channel->queue_declare('knowledge.chunks', false, true, false, false, false, [
  'x-dead-letter-exchange' => ['S', 'knowledge.dlx'],
]);
```

<!-- Пояснити, що DLQ тут є частиною контракту на надійність. -->

---

# ADR-07: Альтернативи + trade-off

- Альтернатива: Redis Streams (менше сервісів), Kafka (більше масштабу)
- Плюси RabbitMQ: простий routing + DLQ + low-ops
- Trade-off: ще один сервіс в `docker compose`

<!-- Додати, що для поточного масштабу Kafka є надлишковим. -->

---

# ADR-08: Traefik як reverse proxy + edge router
## Контекст + проблема

- Потрібно маршрутизувати багато сервісів через єдину edge-точку
- Потрібен Docker-native discovery і middleware chain
- Потрібен forward-auth до core

<!-- Пояснити, чому обрана декларативна маршрутизація через labels. -->

---

# ADR-08: Рішення + обґрунтування

```yaml
# compose.yaml
traefik:
  image: traefik:v3.1
  command:
    - --configFile=/etc/traefik/traefik.yml
```

```yaml
# compose.core.yaml
labels:
  - traefik.http.routers.core.entrypoints=web
  - traefik.http.middlewares.edge-auth.forwardauth.address=http://core/edge/auth/verify
```

```php
// apps/core/src/A2AGateway/AgentDiscoveryService.php
private const TRAEFIK_API_URL = 'http://traefik:8080/api/http/services';
```

<!-- Відмітити: той самий Traefik дає і routing, і discovery для core. -->

---

# ADR-08: Альтернативи + trade-off

- Альтернатива: Nginx/HAProxy з ручним конфігом
- Плюси Traefik: labels, автодискавері, менше ручної підтримки
- Trade-off: менше low-level контролю і залежність від YAML/labels

<!-- Сказати, що для швидких змін у multi-agent середовищі це вигідний компроміс. -->

---

# ADR-09: OpenSpec для управління змінами
## Контекст + проблема

- Багато архітектурних змін виконуються паралельно різними агентами
- Потрібна формальна трасованість: що/чому/як змінюємо
- Потрібна однакова дисципліна для proposal -> implementation -> archive

<!-- Пояснити, що ADR сам по собі не покриває повний change lifecycle. -->

---

# ADR-09: Рішення + обґрунтування

```md
# openspec/AGENTS.md (витяг)
## Three-Stage Workflow
### Stage 1: Creating Changes
### Stage 2: Implementing Changes
### Stage 3: Archiving Changes

Validate: openspec validate <change-id> --strict
```

- Кожна істотна зміна оформлюється як `openspec/changes/<change-id>/...`
- Перед імплементацією проходить proposal + tasks

<!-- Пояснити, що це знижує ризик хаотичних архітектурних відхилень. -->

---

# ADR-09: Альтернативи + trade-off

- Альтернатива: тільки GitHub Issues + PR або тільки ADR
- Плюси OpenSpec: формальний change-log, кращий multi-agent review
- Trade-off: додатковий процесний overhead на дрібних змінах

<!-- Зафіксувати, що процес оплачується передбачуваністю і аудитабельністю. -->

---

# ADR-10: Convention tests для агентів
## Контекст + проблема

- Нові агенти повинні бути сумісні з платформою "за замовчуванням"
- Ручний review погано масштабується
- Потрібен автоматичний gate на manifest/health/A2A envelope

<!-- Пояснити, що це контрактні тести, а не заміна unit/e2e. -->

---

# ADR-10: Рішення + обґрунтування

```js
// tests/agent-conventions/tests/manifest_test.js
const res = await I.sendGetRequest('/api/v1/manifest');
assert.strictEqual(res.status, 200);
assert.ok(/^[a-z][a-z0-9-]*$/.test(res.data.name));
assert.ok(/^\d+\.\d+\.\d+$/.test(res.data.version));
```

```js
// tests/agent-conventions/tests/a2a_observability_test.js
const res = await I.sendPostRequest('/api/v1/a2a', {
  intent: 'hello.greet',
  payload: { name: 'ConventionAudit' },
  request_id: requestId,
  trace_id: traceId,
});
assert.strictEqual(res?.data?.request_id, requestId);
```

<!-- Підкреслити, що gate ловить контрактні поломки до інтеграції в платформу. -->

---

# ADR-10: Альтернативи + trade-off

- Альтернатива: manual checklist або лише integration/e2e
- Плюси convention-suite: швидкий, чіткий, повторюваний compliance check
- Trade-off: суїту треба підтримувати в актуальному стані

<!-- Пояснити межі: це baseline, а не повне тестове покриття продукту. -->

---

# Summary: Всі ADR і вердикт

| ADR | Рішення | Вердикт |
|---|---|---|
| ADR-01 | Core як A2A Gateway | `Accepted` |
| ADR-02 | Polyglot stack | `Accepted` |
| ADR-03 | Doctrine DBAL без ORM | `Accepted` |
| ADR-04 | OpenSearch 2.11 | `Accepted` |
| ADR-05 | LiteLLM gateway | `Accepted` |
| ADR-06 | Manifest-first discovery | `Accepted` |
| ADR-07 | RabbitMQ async workflows | `Accepted` |
| ADR-08 | Traefik edge routing | `Accepted` |
| ADR-09 | OpenSpec change management | `Accepted` |
| ADR-10 | Agent convention tests | `Accepted` |

<!-- Фінальний висновок: архітектура платформи тримається на явних контрактах і централізованій спостережуваності. -->
