<!-- priority: 6 -->
# HLD — High-Level Design (Slidev презентація)

Створити Slidev-презентацію з High-Level Design для курсу навчання з побудови AI-агентів. Файл: `slides/pages/hld.md` (або `slides/hld-slides.md`) — standalone Slidev deck.

## Контекст

Студентам курсу потрібна візуальна презентація, що показує як AI Community Platform побудована "зверху вниз" — від загальної архітектури до конкретних компонентів, потоків даних і взаємодій.

## Обов'язкові розділи (ключові вузли HLD)

### Розділ 1: System Overview (3-4 слайди)
- Що таке AI Community Platform і яку проблему вирішує
- Високорівнева діаграма: користувач → Telegram → OpenClaw → Core → Agents
- Таблиця агентів: назва, мова, призначення, ключові skills
- Технологічний стек: PHP 8.5, Python 3.11, Postgres 16, Redis 7, OpenSearch 2.11, RabbitMQ 3.13, Traefik v3.1, LiteLLM

### Розділ 2: Platform Core (4-5 слайдів)
- Роль core: gateway, registry, installer, admin UI, auth
- A2A Gateway — як core маршрутизує запити до агентів
  - Mermaid sequence diagram: User → OpenClaw → Core A2AClient → Agent → Response
- Agent Registry — lifecycle агента: discovery → sync → install → ready
  - AgentCardFetcher → ManifestValidator → AgentInstaller (Postgres/OpenSearch/Redis strategies)
- Agent Installer — як core створює БД, юзерів, індекси для нового агента
  - Strategy pattern: PostgresInstallStrategy, OpenSearchInstallStrategy, RedisInstallStrategy
- Admin UI — dashboard, agent management, chat history, settings

### Розділ 3: A2A Protocol (3-4 слайди)
- Формат запиту/відповіді (JSON envelope з intent, payload, trace_id, request_id, hop)
- Agent Card структура: name, version, skills[], events[], storage{}
- Skill discovery flow: manifest → SkillCatalogBuilder → cached routing table
- Tracing: trace_id propagation через всі hops, Langfuse integration
- Code snippet: реальний A2A request/response з `A2AClient.php`

### Розділ 4: Knowledge Agent (3-4 слайди)
- Архітектура: Symfony + Postgres + OpenSearch + RabbitMQ
- Extraction pipeline (mermaid flowchart):
  - Message received → RabbitMQ → Extract workflow → LLM analysis → OpenSearch index
- Skills: knowledge.search, knowledge.upload, knowledge.store_message
- Repository pattern: SourceMessageRepository, KnowledgeRepository
- Чому RabbitMQ для async extraction (не синхронний LLM виклик)

### Розділ 5: News-Maker Agent (3-4 слайди)
- Архітектура: FastAPI + SQLAlchemy + Postgres + OpenSearch
- News pipeline (mermaid flowchart):
  - Cron trigger → Crawler (BFS depth=2) → Raw articles → Ranker (LLM) → Rewriter (LLM) → Curated news
- Admin UI: sources management, settings, news preview
- Deep crawling: BFS з deque, domain scoping, deduplication
- Scheduler: APScheduler з cron expressions

### Розділ 6: Infrastructure Layer (3-4 слайди)
- Docker Compose topology (mermaid diagram):
  - Traefik → [core, agents, openclaw]
  - Shared services: postgres, redis, opensearch, rabbitmq, litellm
  - Observability: langfuse-web, langfuse-worker
- Networking: dev-edge bridge, port mapping, entrypoints
- Per-agent compose overrides: `compose.agent-*.yaml`
- E2E isolation: `-e2e` containers з окремими БД та портами

### Розділ 7: LLM Integration (2-3 слайди)
- LiteLLM як proxy layer: один endpoint для всіх моделей
- Model routing: minimax/minimax-m2.5 для extraction, gpt-4o-mini для general
- Langfuse tracing: як кожен LLM виклик логується з context (agent, feature, trace_id)
- Cost tracking і rate limit management
- Code snippet: LiteLlmClient.php → request/response flow

### Розділ 8: Security & Auth (2-3 слайди)
- Edge Auth: JWT tokens, cookie-based session
- Internal Platform Token: X-Platform-Internal-Token для core↔agent
- Admin auth flow: login → JWT → cookie → middleware verify
- Agent isolation: кожен агент має свою БД, свого юзера

### Розділ 9: Testing Strategy (2-3 слайди)
- Testing pyramid diagram:
  - Unit (Codeception/pytest) → Functional (Symfony module) → Convention (CodeceptJS) → E2E (Playwright)
- Convention tests: що перевіряють (manifest, health, A2A contract)
- Quality gates: PHPStan level 8, PHP CS Fixer, Ruff
- E2E isolation: окремі контейнери, окремі БД

### Розділ 10: Development Workflow (2-3 слайди)
- OpenSpec 3-stage process: Create → Implement → Archive
- Multi-agent pipeline: architect → coder → validator → tester → documenter
- Dev Agent: task creation, pipeline orchestration via A2A
- Dev Reporter Agent: pipeline observability, Telegram notifications

## Вимоги до презентації

1. Slidev формат (markdown з frontmatter `---` між слайдами)
2. Theme: seriph (як основна презентація)
3. Мова: українська
4. Щонайменше 8 Mermaid діаграм (architecture, sequence, flowchart)
5. Code snippets з реального коду (не вигадані!)
6. Speaker notes (`<!-- ... -->`) для доповідача
7. Титульний слайд: "High-Level Design — AI Community Platform"
8. Останній слайд: повна architecture diagram (all components)
9. ~35-45 слайдів загалом

## Ключові діаграми (must-have)

1. **System overview** — всі компоненти на одній діаграмі
2. **A2A sequence** — повний lifecycle запиту через gateway
3. **Agent discovery** — від Traefik label до ready state
4. **Knowledge extraction pipeline** — message → RabbitMQ → LLM → OpenSearch
5. **News curation pipeline** — crawl → rank → rewrite → publish
6. **Docker compose topology** — сервіси та зв'язки
7. **Testing pyramid** — рівні тестування
8. **Auth flow** — JWT + internal token

## Джерела даних

- `compose.yaml`, `compose.*.yaml` — повна інфраструктура
- `apps/core/src/A2AGateway/A2AClient.php` — gateway implementation
- `apps/core/src/AgentRegistry/` — agent lifecycle
- `apps/core/src/AgentInstaller/` — provisioning strategies
- `apps/knowledge-agent/src/Workflow/` — extraction pipeline
- `apps/news-maker-agent/app/services/` — news pipeline
- `apps/core/src/LLM/LiteLlmClient.php` — LLM integration
- `apps/core/src/Security/EdgeJwtService.php` — auth
- `docker/litellm/config.yaml` — model routing
- `openspec/project.md` — testing & conventions
- `tests/agent-conventions/` — convention tests
- Agent manifest controllers в кожному агенті

## Валідація

- Slidev-валідний markdown
- Всі Mermaid діаграми рендеряться
- Code snippets відповідають реальному коду
- Speaker notes на кожному слайді
