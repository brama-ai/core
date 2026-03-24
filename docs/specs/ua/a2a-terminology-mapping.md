# Маппінг Термінології A2A

Цей документ зіставляє платформну термінологію з офіційною специфікацією [A2A Protocol](https://a2a-protocol.org).

## Таблиця Термінології

| Платформний термін | Офіц. A2A термін | Місце в коді | Примітки |
|---|---|---|---|
| Agent Card | Agent Card | Відповідь `GET /api/v1/manifest` | JSON-документ з метаданими агента: ідентичність, skills, endpoint |
| `url` | `url` | Поле Agent Card | URL ендпоінту A2A Server (замінює застарілий `a2a_endpoint`) |
| `provider` | AgentProvider | Поле Agent Card | `{ organization, url }` — інформація про провайдера |
| `capabilities` | AgentCapabilities | Поле Agent Card | `{ streaming, pushNotifications, stateTransitionHistory }` — можливості A2A протоколу |
| Skills | AgentSkill | Поле `skills` в Agent Card | Структуровані об'єкти: `{ id, name, description, tags, examples }` |
| `defaultInputModes` | `defaultInputModes` | Поле Agent Card | MIME-типи для вхідних даних (за замовчуванням: `["text"]`) |
| `defaultOutputModes` | `defaultOutputModes` | Поле Agent Card | MIME-типи для вихідних даних (за замовчуванням: `["text"]`) |
| Skill Schemas | — | Поле `skill_schemas` в Agent Card | Застаріле: JSON Schema для валідації вхідних даних кожного skill |
| Skill Catalog | — | `GET /api/v1/a2a/discovery` | Агрегований список усіх skills з увімкнених агентів |
| A2A Gateway | — | Namespace `App\A2AGateway` | Подвійна роль Core: A2A Server + A2A Client |
| A2A Client | A2A Client | `App\A2AGateway\A2AClient` | Компонент Core, що викликає A2A ендпоінти агентів |
| A2A Server | A2A Server | Обробник `POST /api/v1/a2a` кожного агента | Обробник вхідних A2A запитів на стороні агента |
| Send Message | tasks/send | `POST /api/v1/a2a/send-message` | Вхідний A2A ендпоінт Core (від OpenClaw) |
| Agent Card Fetcher | — | `App\A2AGateway\AgentCardFetcher` | Отримує Agent Card з manifest-ендпоінту агента |
| A2A Message Audit | — | Таблиця `a2a_message_audit` | Журнал аудиту всіх A2A взаємодій |
| Well-Known Discovery | `/.well-known/agent-card.json` | Ендпоінт Core | Платформний AgentCard з агрегованими skills |

## Ролі в Архітектурі

```
OpenClaw (A2A Client)
    ↓ POST /api/v1/a2a/send-message
Core (A2A Gateway)
    ↓ POST /api/v1/a2a (per agent)
Agents (A2A Servers)
```

- **OpenClaw** надсилає A2A запити до Core
- **Core** виступає як A2A Gateway — валідує, маршрутизує, аудитує та спостерігає
- **Agents** є A2A Servers, що обробляють skills і повертають структуровані відповіді

## Ендпоінти Discovery

| Ендпоінт | Область | Авторизація | Призначення |
|---|---|---|---|
| `GET /.well-known/agent-card.json` | Платформа | Публічний | Офіційний A2A discovery — AgentCard Core з агрегованими skills |
| `GET /api/v1/a2a/discovery` | Платформа | Bearer token | Детальний skill catalog для інтеграції з OpenClaw |
| `GET /api/v1/manifest` | Кожен агент | Без авторизації | Agent Card рівня агента (внутрішній, Core отримує при discovery) |

## Ключові Дизайн-Рішення

1. **Ендпоінт маніфесту стабільний**: Агенти віддають Agent Card через `GET /api/v1/manifest` (без перейменування)
2. **`url` замінює `a2a_endpoint`**: Поле `url` відповідає офіційному `AgentCard.url`. Застарілий `a2a_endpoint` приймається для зворотної сумісності
3. **Структуровані skills**: Skills тепер є об'єктами `AgentSkill` `{ id, name, description, tags }`. Застарілі рядкові skills приймаються та нормалізуються автоматично
4. **`capabilities` — це A2A AgentCapabilities**: Не плутати зі старим полем `capabilities` (яке було перейменовано на `skills` під час рефакторингу термінології)
5. **`intent` збережено в payload**: Тіло A2A запиту використовує `intent` поряд з `tool` для зворотної сумісності
6. **Схема Agent Card**: Визначена в `apps/brama-core/config/agent-card.schema.json`
