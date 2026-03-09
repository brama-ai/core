# Pipeline Handoff

- **Task**: # ADR — Architecture Decision Records (Slidev презентація)

Створити Slidev-презентацію з Architecture Decision Records для курсу навчання з побудови AI-агентів. Презентація повинна бути окремим файлом `slides/pages/adr.md` (або `slides/adr-slides.md`) з можливістю запуску як standalone Slidev deck.

## Контекст

AI Community Platform має десятки архітектурних рішень, які не задокументовані в зручному для навчання форматі. Для курсу потрібна презентація, що пояснює **чому** прийняті ті чи інші рішення, які альтернативи розглядались, та які trade-off.

## Формат кожного ADR-слайду

Кожне рішення — 2-3 слайди:
1. **Контекст + проблема** (що вирішуємо)
2. **Рішення + обґрунтування** (що обрали і чому)
3. **Альтернативи + trade-off** (що відкинули і що втратили)

## Обов'язкові ADR (ключові архітектурні вузли)

### ADR-01: A2A Gateway як центральний хаб
- Чому всі виклики між агентами йдуть через core, а не напряму?
- Переваги: єдина точка трейсингу, авторизації, rate limiting
- Альтернатива: mesh-мережа між агентами
- Trade-off: single point of failure, додаткова латентність

### ADR-02: Мультимовний стек (PHP core + Python agents)
- Чому PHP/Symfony для core і knowledge-agent, а Python/FastAPI для news-maker?
- Переваги: best tool for the job, спільний A2A протокол
- Альтернатива: monostack (все на Python або все на PHP)
- Trade-off: складніший DevOps, різні тулчейни

### ADR-03: Doctrine DBAL без ORM
- Чому відмовились від Doctrine ORM на користь чистого DBAL?
- Переваги: контроль над SQL, відсутність N+1, менше магії
- Альтернатива: повний ORM з entities/repositories
- Trade-off: більше boilerplate, ручні міграції

### ADR-04: OpenSearch замість Elasticsearch
- Чому OpenSearch 2.11, а не Elasticsearch?
- Переваги: відкрита ліцензія, сумісний API, community-driven
- Альтернатива: Elasticsearch, pgvector, Meilisearch

### ADR-05: LiteLLM як єдиний LLM gateway
- Чому проксі через LiteLLM замість прямих API-викликів?
- Переваги: єдиний інтерфейс, ротація моделей, cost tracking
- Альтернатива: прямі SDK-виклики (openai, anthropic)
- Trade-off: додатковий hop, залежність від LiteLLM

### ADR-06: Agent Card + Manifest для discovery
- Чому manifest-first підхід замість service registry?
- Переваги: self-describing agents, runtime discovery, version-aware
- Альтернатива: Consul/etcd, hardcoded routing table
- Trade-off: cold start при першому sync

### ADR-07: RabbitMQ для async workflows
- Чому RabbitMQ, а не Redis Streams або Kafka?
- Переваги: routing, dead letter queues, low-ops
- Альтернатива: Redis Streams (вже є Redis), Kafka (overkill)
- Trade-off: ще один сервіс у compose

### ADR-08: Traefik як reverse proxy + edge router
- Чому Traefik замість Nginx/HAProxy?
- Переваги: Docker-native label routing, автоматичний discovery, middleware chain
- Альтернатива: Nginx з ручним конфігом
- Trade-off: менший контроль, YAML-first конфігурація

### ADR-09: OpenSpec для управління змінами
- Чому формальний spec-first процес замість вільного кодування?
- Переваги: аудитабельність, multi-agent review, version history
- Альтернатива: GitHub Issues + PRs, ADR-only
- Trade-off: overhead на малих змінах

### ADR-10: Convention tests для агентів
- Чому окрема тест-суїта для перевірки конвенцій?
- Переваги: гарантія сумісності нових агентів, автоматичний compliance
- Альтернатива: ручний review, integration tests only
- Trade-off: додаткова підтримка тестів

## Вимоги до презентації

1. Slidev формат (markdown з frontmatter `---` між слайдами)
2. Використовувати theme: seriph (як основна презентація)
3. Мова: українська
4. Кожен ADR має code snippets або діаграми де доречно (mermaid diagrams)
5. Speaker notes (`<!-- ... -->`) з поясненнями для доповідача
6. Перший слайд — титульний "Architecture Decision Records — AI Community Platform"
7. Останній слайд — summary таблиця всіх ADR з verdict
8. Реальні приклади коду з кодової бази (не вигадані!)

## Джерела даних

- `docs/decisions/adr_0002_openclaw_role.md` — існуючий ADR
- `compose.yaml`, `compose.*.yaml` — інфраструктурні рішення
- `apps/core/src/A2AGateway/` — A2A gateway патерн
- `apps/core/src/LLM/LiteLlmClient.php` — LLM інтеграція
- `docker/litellm/config.yaml` — LiteLLM конфігурація
- `openspec/project.md` — конвенції проекту
- `tests/agent-conventions/` — convention tests
- Agent manifests в кожному агенті

## Валідація

- Презентація має бути синтаксично валідним Slidev markdown
- Всі code snippets мають відповідати реальному коду
- Mermaid діаграми повинні рендеритись
- ~30-40 слайдів загалом
- **Started**: 2026-03-09 14:41:44
- **Branch**: pipeline/adr-architecture-decision-records-slidev
- **Pipeline ID**: 20260309_144142

---

## Architect

- **Status**: pending
- **Change ID**: —
- **Apps affected**: —
- **DB changes**: —
- **API changes**: —

## Coder

- **Status**: pending
- **Files modified**: —
- **Migrations created**: —
- **Deviations**: —

## Validator

- **Status**: pending
- **PHPStan**: —
- **CS-check**: —
- **Files fixed**: —

## Tester

- **Status**: pending
- **Test results**: —
- **New tests written**: —

## Documenter

- **Status**: pending
- **Docs created/updated**: —

---

