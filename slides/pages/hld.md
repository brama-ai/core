---
theme: seriph
title: High-Level Design - AI Community Platform
mdc: true
---

# High-Level Design
## AI Community Platform

Top-down огляд: від edge до агентів та інфраструктури

<!-- Вступ: мета HLD — пояснити архітектуру та головні потоки даних. -->

---

# План

1. System Overview
2. Platform Core
3. A2A Protocol
4. Knowledge Agent
5. News-Maker Agent
6. Infrastructure Layer
7. LLM Integration
8. Security & Auth
9. Testing Strategy
10. Development Workflow

<!-- Коротко пройтись по секціях і очікуваному результату. -->

---

# 1) System Overview
## Що таке платформа

- Платформа для мульти-агентної роботи поверх chat-каналів
- Core надає registry, routing, admin, auth, installer
- Агенти реалізують вузькі capability через A2A-контракт

<!-- Пояснити “platform first”: агенти змінні, контракти стабільні. -->

---

# 1) System Overview
## End-to-end architecture

```mermaid
flowchart LR
  User[User] --> TG[Telegram]
  TG --> OC[OpenClaw Gateway]
  OC --> Core[Core A2A Gateway]
  Core --> KA[knowledge-agent]
  Core --> NM[news-maker-agent]
  Core --> DA[dev-agent]
  Core --> DRA[dev-reporter-agent]
  KA --> LiteLLM
  NM --> LiteLLM
  LiteLLM --> Langfuse
```

<!-- Це канонічний high-level потік запиту з точки зору користувача. -->

---

# 1) System Overview
## Таблиця агентів

| Agent | Runtime | Призначення | Приклади skills |
|---|---|---|---|
| `core` | PHP 8.5 + Symfony 7 | Gateway, registry, installer, admin | `a2a/discovery`, `a2a/send-message` |
| `knowledge-agent` | PHP 8.5 + Symfony 7 | Search + ingestion knowledge | `knowledge.search`, `knowledge.upload`, `knowledge.store_message` |
| `news-maker-agent` | Python (FastAPI) | Crawl -> rank -> rewrite news | rank/rewrite pipeline |
| `dev-agent` | PHP | Pipeline task orchestration | task submit/run |
| `dev-reporter-agent` | PHP | Pipeline observability/reporting | `devreporter.ingest` |

<!-- Пояснити, що skill-контракт важливіший за технологічний стек. -->

---

# 1) System Overview
## Технологічний стек (реальний стан репо)

```yaml
# compose.yaml (уривок)
postgres: { image: postgres:16-alpine }
redis: { image: redis:7-alpine }
opensearch: { image: opensearchproject/opensearch:2.11.1 }
rabbitmq: { image: rabbitmq:3.13-management-alpine }
traefik: { image: traefik:v3.1 }
litellm: { image: ghcr.io/berriai/litellm:main-latest }
```

```dockerfile
# docker/core/Dockerfile
FROM php:8.5-apache
```

```dockerfile
# docker/news-maker-agent/Dockerfile
FROM python:3.12-slim
```

<!-- Зауважити: у практичному стеку зараз Python 3.12 у Dockerfile news-maker. -->

---

# 2) Platform Core
## Роль core

- `A2A Gateway`: прийом інвоку + маршрутизація по skills
- `Agent Registry`: реєстрація, enable/disable, health, audit
- `Agent Installer`: provisioning Postgres/Redis/OpenSearch
- `Admin UI`: dashboard, agents, chats, logs, settings

<!-- Показати, що core є control-plane платформи. -->

---

# 2) Platform Core
## Admin surface

```php
// apps/core/src/Controller/Admin/DashboardController.php
$all = $this->registry->findAll();
$enabled = array_filter($all, static fn (array $a): bool => (bool) $a['enabled']);

return $this->render('admin/dashboard.html.twig', [
  'agents_total' => count($all),
  'agents_enabled' => count($enabled),
]);
```

```twig
{# apps/core/templates/admin/layout.html.twig #}
<a href="{{ path('admin_dashboard') }}">Статистика</a>
<a href="{{ path('admin_chats') }}">Управління чатами</a>
<a href="{{ path('admin_agents') }}">Управління агентами</a>
```

<!-- Пояснити, що UI оперує registry як source of truth. -->

---

# 2) Platform Core
## A2A sequence (gateway path)

```mermaid
sequenceDiagram
  participant U as User
  participant O as OpenClaw
  participant C as Core SendMessageController
  participant G as A2AClient
  participant A as Target Agent

  U->>O: запит у чаті
  O->>C: POST /api/v1/a2a/send-message
  C->>G: invoke(tool,input,trace_id,request_id)
  G->>A: POST agent A2A endpoint
  A-->>G: status/result
  G-->>C: merged response + audit
  C-->>O: JSON response
```

<!-- Наголосити на trace/request propagation по всьому ланцюжку. -->

---

# 2) Platform Core
## Agent discovery lifecycle

```mermaid
flowchart LR
  T[Traefik API services] --> D[AgentDiscoveryService]
  D --> F[AgentCardFetcher]
  F --> V[ManifestValidator]
  V --> R[AgentRegistryRepository.upsertFromDiscovery]
  R --> I[AgentInstallerService.install]
  I --> Ready[agent ready for enable]
```

<!-- Пояснити, що discover/install/enable розділені навмисно. -->

---

# 2) Platform Core
## Installer Strategy Pattern

```php
// apps/core/src/AgentInstaller/AgentInstallerService.php
if (isset($storage['postgres']) && is_array($storage['postgres'])) {
    $actions = array_merge($actions, $this->postgres->provision($storage['postgres'], $agentName));
}
if (isset($storage['redis']) && is_array($storage['redis'])) {
    $actions = array_merge($actions, $this->redis->provision($storage['redis'], $agentName));
}
if (isset($storage['opensearch']) && is_array($storage['opensearch'])) {
    $actions = array_merge($actions, $this->opensearch->provision($storage['opensearch'], $agentName));
}
```

<!-- Пояснити, що storage-декларація у manifest керує provisioning. -->

---

# 3) A2A Protocol
## Envelope

- intent-based контракт
- `payload`, `trace_id`, `request_id`, `hop`
- статичний API endpoint core: `/api/v1/a2a/send-message`

<!-- Дати ментальну модель: A2A = транспорт + кореляція + статус. -->

---

# 3) A2A Protocol
## Request/response fields в коді

```php
// apps/core/src/A2AGateway/A2AClient.php
$payload = [
  'intent' => $tool,
  'payload' => $input,
  'request_id' => $requestId,
  'trace_id' => $traceId,
];
$payload['agent_run_id'] = $agentRunId;
$payload['hop'] = 1;
```

```php
// apps/core/src/Controller/Api/A2AGateway/SendMessageController.php
$traceId = (string) ($body['trace_id'] ?? uniqid('trace_', true));
$requestId = (string) ($body['request_id'] ?? uniqid('req_', true));
```

<!-- Пояснити, як core гарантує кореляцію навіть при неповному запиті. -->

---

# 3) A2A Protocol
## Agent Card структура

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
  'storage' => [
    'postgres' => [...],
    'redis' => [...],
    'opensearch' => [...],
  ],
]);
```

<!-- Показати, що manifest одночасно описує capabilities і infra needs. -->

---

# 3) A2A Protocol
## Skill discovery + tracing

```mermaid
flowchart LR
  E[enabled agents] --> B[SkillCatalogBuilder]
  B --> C[/api/v1/a2a/discovery]
  C --> O[OpenClaw tool catalog]
  O --> S[/api/v1/a2a/send-message]
  S --> L[TraceEvent + Langfuse]
```

<!-- Пояснити, що discovery і invoke пов'язані одним trace-контуром. -->

---

# 4) Knowledge Agent
## Архітектура компонента

- Symfony app + Postgres + OpenSearch
- асинхронна обробка через RabbitMQ
- workflow ноди: analyze -> extract -> enrich

<!-- Пояснити місце knowledge-agent у загальному data plane. -->

---

# 4) Knowledge Agent
## Extraction pipeline

```mermaid
flowchart LR
  Msg[Message batch] --> Q[knowledge.chunks queue]
  Q --> W[knowledge worker]
  W --> A[AnalyzeMessages]
  A --> E[ExtractKnowledge]
  E --> M[EnrichMetadata]
  M --> OS[OpenSearch index]
```

<!-- Акцент: expensive LLM крок винесений у worker, не в sync endpoint. -->

---

# 4) Knowledge Agent
## Skills і A2A handler

```php
// apps/knowledge-agent/src/A2A/KnowledgeA2AHandler.php
return match ($intent) {
  'search_knowledge', 'knowledge.search' => $this->handleSearch($payload, $requestId),
  'extract_from_messages', 'knowledge.upload' => $this->handleExtract($payload, $requestId),
  'knowledge.store_message', 'store_message' => $this->handleStoreMessage($payload, $requestId, $traceId),
  default => ['status' => 'failed', 'request_id' => $requestId],
};
```

<!-- Пояснити, що один handler тримає skill-routing всередині агента. -->

---

# 4) Knowledge Agent
## Repository pattern

```php
// apps/knowledge-agent/src/Repository/SourceMessageRepository.php
final class SourceMessageRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function upsert(array $payload, string $requestId, ?string $traceId = null): string
    {
        $id = $this->connection->fetchOne('INSERT ... ON CONFLICT ... RETURNING id', [...]);
        return (string) $id;
    }
}
```

```php
// apps/knowledge-agent/src/OpenSearch/KnowledgeRepository.php
$response = $this->client->search([
  'index' => $this->indexName,
  'body' => array_merge($searchQuery, ['size' => $size]),
]);
```

<!-- Показати розділення відповідальностей: SQL source-of-truth + search projection. -->

---

# 5) News-Maker Agent
## Архітектура компонента

- FastAPI + SQLAlchemy (Postgres)
- ingestion: crawl + dedup raw items
- curation: ranker + rewriter через LiteLLM

<!-- Сформувати картину двоетапного editorial pipeline. -->

---

# 5) News-Maker Agent
## News curation pipeline

```mermaid
flowchart LR
  Cron[Cron trigger] --> Crawl[Crawler BFS depth<=2]
  Crawl --> Raw[raw_news_items]
  Raw --> Rank[Ranker LLM]
  Rank --> Rewrite[Rewriter LLM]
  Rewrite --> Curated[curated_news_items ready]
```

<!-- Пояснити, що rank/rewrite окремі для якості і контролю витрат. -->

---

# 5) News-Maker Agent
## Deep crawling: BFS + domain scoping

```python
# apps/news-maker-agent/app/services/crawler.py
MAX_DEPTH = settings.crawl_max_depth
queue: deque[tuple[str, int, str]] = deque()

for link in links:
    seen_links.add(link)
    queue.append((link, 1, source.base_url))

while queue:
    link, depth, discovered_from_url = queue.popleft()
    if depth > max_depth:
        continue
```

<!-- Пояснити захисні механізми: timebox, depth limit, dedup. -->

---

# 5) News-Maker Agent
## Scheduler і admin control

```python
# apps/news-maker-agent/app/services/scheduler.py
_scheduler.add_job(_run_crawl_pipeline, CronTrigger.from_crontab(crawl_cron), id="crawl_pipeline")
_scheduler.add_job(_run_cleanup, CronTrigger.from_crontab(cleanup_cron), id="cleanup")
```

```yaml
# compose.agent-news-maker.yaml
environment:
  RANKER_MODEL: minimax/minimax-m2.5
  REWRITER_MODEL: minimax/minimax-m2.5
  ADMIN_PUBLIC_URL: http://localhost:8084/admin/sources
```

<!-- Наголосити, що cron керується налаштуваннями агента, не hardcoded. -->

---

# 6) Infrastructure Layer
## Compose model

- Базові shared сервіси в `compose.yaml`
- Runtime-компоненти в `compose.core.yaml`, `compose.agent-*.yaml`
- OpenClaw edge у `compose.openclaw.yaml`

<!-- Пояснити шарування compose файлів і для чого це зроблено. -->

---

# 6) Infrastructure Layer
## Docker Compose topology

```mermaid
flowchart TB
  Traefik --> Core
  Traefik --> OpenClaw
  Traefik --> Knowledge
  Traefik --> News
  Core --> Postgres
  Core --> Redis
  Core --> OpenSearch
  Knowledge --> RabbitMQ
  Knowledge --> OpenSearch
  News --> Postgres
  News --> OpenSearch
  Core --> LiteLLM
  Knowledge --> LiteLLM
  News --> LiteLLM
  LiteLLM --> Langfuse
```

<!-- Це головна інфраструктурна схема для локального dev/runtime. -->

---

# 6) Infrastructure Layer
## Networking + entrypoints

```yaml
# compose.yaml
networks:
  dev-edge:
    driver: bridge
```

```yaml
# compose.core.yaml
labels:
  - traefik.http.routers.core.entrypoints=web
```

```yaml
# compose.agent-knowledge.yaml
labels:
  - traefik.http.routers.knowledge-agent.entrypoints=knowledge
```

<!-- Пояснити логіку: окремі entrypoints під різні поверхні доступу. -->

---

# 6) Infrastructure Layer
## E2E isolation

```yaml
# compose.core.yaml
core-e2e:
  profiles: [e2e]
  environment:
    DATABASE_URL: postgresql://app:app@postgres:5432/ai_community_platform_test
  ports:
    - "18080:80"
```

```yaml
# compose.agent-knowledge.yaml
knowledge-agent-e2e:
  profiles: [e2e]
  environment:
    DATABASE_URL: postgresql://knowledge_agent:knowledge_agent@postgres:5432/knowledge_agent_test
    REDIS_URL: redis://redis:6379/3
    RABBITMQ_URL: amqp://app:app@rabbitmq:5672/test
```

<!-- Пояснити, що e2e має власні БД/порти/черги для відтворюваності тестів. -->

---

# 7) LLM Integration
## LiteLLM як proxy layer

- Один endpoint для різних моделей
- Спільний формат telemetry headers/tags
- Callbacks у Langfuse для success/failure

<!-- Пояснити, що це LLM control-plane для всіх агентів. -->

---

# 7) LLM Integration
## Request flow у core

```php
// apps/core/src/LLM/LiteLlmClient.php
$body = [
  'model' => $this->model,
  'messages' => $messages,
  'max_tokens' => 4096,
  'tags' => $context->tags(),
  'metadata' => $context->metadata(),
];
$endpoint = rtrim($this->baseUrl, '/').'/v1/chat/completions';
$result = file_get_contents($endpoint, false, $context);
```

<!-- Показати, що trace/request метадані проходять до LLM gateway. -->

---

# 7) LLM Integration
## Model routing + observability

```yaml
# docker/litellm/config.yaml
model_list:
  - model_name: minimax/minimax-m2.5
  - model_name: gpt-4o-mini
litellm_settings:
  success_callback: ["langfuse"]
  failure_callback: ["langfuse"]
```

```python
# apps/news-maker-agent/app/services/ranker.py
response = client.chat.completions.create(
  model=model,
  extra_headers=llm_headers,
  extra_body={"tags": [f"agent:{SERVICE_NAME}", f"method:{FEATURE_NAME}"]},
)
```

<!-- Наголосити на уніфікації tracing між PHP та Python агентами. -->

---

# 8) Security & Auth
## Контури авторизації

- Edge JWT cookie для UI та web entrypoints
- Internal token для core <-> agent API
- OpenClaw до core: Bearer gateway token

<!-- Розділити зовнішню та внутрішню авторизацію на два шари. -->

---

# 8) Security & Auth
## Auth flow (JWT + forward auth)

```mermaid
sequenceDiagram
  participant B as Browser
  participant T as Traefik
  participant V as /edge/auth/verify
  participant L as /edge/auth/login

  B->>T: GET /admin/dashboard
  T->>V: forward auth check
  V-->>T: 302 /edge/auth/login?rd=...
  B->>L: POST credentials
  L-->>B: Set-Cookie ACP_EDGE_TOKEN
  B->>T: GET /admin/dashboard (with cookie)
  T->>V: verify token
  V-->>T: 204 allow
```

<!-- Пояснити, що Traefik довіряє рішення verify endpoint у core. -->

---

# 8) Security & Auth
## Internal platform token

```php
// apps/core/src/A2AGateway/A2AClient.php
if ('' !== $this->internalToken) {
    $headers['X-Platform-Internal-Token'] = $this->internalToken;
}
```

```php
// apps/knowledge-agent/src/Controller/Api/A2AController.php
$token = $request->headers->get('X-Platform-Internal-Token');
if ($token !== $this->internalToken) {
    return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
}
```

<!-- Наголосити: machine-to-machine auth відокремлена від user JWT. -->

---

# 9) Testing Strategy
## Піраміда тестування

```mermaid
flowchart TB
  U[Unit: phpunit/pytest] --> F[Functional: Symfony/HTTP]
  F --> C[Convention: CodeceptJS]
  C --> E[E2E: Playwright + docker profiles]
```

<!-- Пояснити, що контрактні та e2e шари ловлять інтеграційні злами. -->

---

# 9) Testing Strategy
## Convention tests

```js
// tests/agent-conventions/tests/health_test.js
const res = await I.sendGetRequest('/health');
assert.strictEqual(res.status, 200);
assert.strictEqual(res.data.status, 'ok');
```

```js
// tests/agent-conventions/tests/manifest_test.js
const res = await I.sendGetRequest('/api/v1/manifest');
assert.ok(/^[a-z][a-z0-9-]*$/.test(res.data.name));
assert.ok(/^\d+\.\d+\.\d+$/.test(res.data.version));
```

<!-- Пояснити, що це baseline gate для будь-якого нового агента. -->

---

# 9) Testing Strategy
## Quality gates

- `PHPStan` (core/agents)
- `PHP CS Fixer` для code style
- `pytest`/`Codeception` для unit+functional
- `CodeceptJS` для convention tests
- `E2E` у профілях ізольованих контейнерів

<!-- Зафіксувати, що quality gates інтегровані у загальний pipeline. -->

---

# 10) Development Workflow
## OpenSpec process

```md
# openspec/AGENTS.md
Stage 1: Creating Changes
Stage 2: Implementing Changes
Stage 3: Archiving Changes

openspec validate <change-id> --strict
```

<!-- Пояснити, що архітектурні зміни проходять через spec-first. -->

---

# 10) Development Workflow
## Multi-agent pipeline

```bash
# scripts/pipeline.sh
AGENTS=(architect coder validator tester documenter)
```

```bash
# scripts/pipeline.sh (resume examples)
./scripts/pipeline.sh --from coder "..."
./scripts/pipeline.sh --only tester "..."
```

- Оркестрація задач через послідовні stage-и
- Dev Reporter збирає та публікує pipeline outcomes

<!-- Наголосити, що pipeline формалізує delivery і зменшує ручний хаос. -->

---

# 10) Development Workflow
## Dev + Dev Reporter ролі

- `dev-agent`: ставить/запускає pipeline tasks
- `dev-reporter-agent`: зберігає run status + сповіщає
- Повний цикл прозорий для admin та команди

<!-- Завершити секцію зв'язком процесу і технічної архітектури. -->

---

# Full Architecture Diagram

```mermaid
flowchart TB
  subgraph Edge
    Traefik
    OpenClaw
  end

  subgraph Core
    Send[SendMessageController]
    Disc[DiscoveryController]
    Reg[AgentRegistryRepository]
    Inst[AgentInstallerService]
    UI[Admin UI]
  end

  subgraph Agents
    KA[knowledge-agent]
    KW[knowledge-worker]
    NM[news-maker-agent]
    DA[dev-agent]
    DR[dev-reporter-agent]
  end

  subgraph SharedInfra
    PG[(Postgres)]
    RD[(Redis)]
    OS[(OpenSearch)]
    MQ[(RabbitMQ)]
    LLM[LiteLLM]
    LF[Langfuse]
  end

  OpenClaw --> Send
  Send --> KA
  Send --> NM
  Send --> DA
  Send --> DR
  Disc --> OpenClaw
  Reg --> UI
  Inst --> PG
  Inst --> RD
  Inst --> OS
  KA --> MQ
  KW --> MQ
  KA --> OS
  NM --> PG
  KA --> LLM
  NM --> LLM
  DA --> LLM
  LLM --> LF
  Traefik --> UI
  Traefik --> OpenClaw
```

<!-- Фінал: зібрати все в одну картину і вказати ключові контрольні точки архітектури. -->
