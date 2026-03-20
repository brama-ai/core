# Архітектура Devcontainer

## Огляд

Devcontainer надає повністю налаштоване середовище розробки всередині Docker
контейнера. Він використовує спільні інфраструктурні сервіси з основного Docker
Compose стеку — без дублювання.

```
┌─────────────────────────────────────────────────────────────┐
│                 Один Docker Compose Project                  │
│                                                             │
│  compose.yaml                  .devcontainer/               │
│  ┌──────────────┐              docker-compose.yml           │
│  │ postgres     │              ┌──────────────┐             │
│  │ redis        │◄── мережі ──┤ devcontainer │             │
│  │ opensearch   │   dev-edge   │   (Ubuntu)   │             │
│  │ rabbitmq     │   agents-    │              │             │
│  │ traefik      │   internal   │ PHP 8.5      │             │
│  │ litellm      │              │ Node.js 22   │             │
│  └──────────────┘              │ Python 3.12  │             │
│                                │ Go 1.24      │             │
│                                │ Composer     │             │
│                                │ Bun          │             │
│                                └──────────────┘             │
└─────────────────────────────────────────────────────────────┘
```

## Як це працює

`devcontainer.json` мержить два compose файли в **один проєкт**:

```json
"dockerComposeFile": ["../compose.yaml", "docker-compose.yml"]
```

- `compose.yaml` (корінь) — визначає інфраструктуру: postgres, redis, opensearch,
  rabbitmq, traefik, litellm
- `.devcontainer/docker-compose.yml` — визначає лише сервіс devcontainer
  (та опціонально codex)

Devcontainer підключається до тих самих Docker мереж (`dev-edge`, `agents-internal`)
що й інфраструктурні сервіси. Застосунки всередині devcontainer звертаються до
сервісів по hostname: `postgres`, `redis`, `opensearch`, `rabbitmq`.

## Ключові рішення

### Єдине джерело інфраструктури

Інфраструктура визначена **один раз** в `compose.yaml`. Devcontainer НЕ
дублює postgres, redis і т.д. Це означає:

- Один postgres, один redis — без зайвого споживання ресурсів
- Ті ж init scripts (`docker/postgres/init/`) створюють всі бази
- Ті ж Docker volumes зберігають дані між перезапусками
- Docker Desktop показує один compose project, а не два

### Без .env.local оверрайдів

Застосунки використовують свої дефолтні `.env` файли з Docker hostname:

```
DATABASE_URL=postgresql://app:app@postgres:5432/ai_community_platform
REDIS_URL=redis://redis:6379
```

Оскільки devcontainer в тій самій Docker мережі, ці hostname резолвяться
коректно. Ніяких `.env.local` з `127.0.0.1` не потрібно.

### depends_on з health checks

Devcontainer чекає поки postgres та redis будуть healthy перед стартом:

```yaml
depends_on:
  postgres:
    condition: service_healthy
  redis:
    condition: service_healthy
```

## Файли

| Файл | Призначення |
|------|-------------|
| `.devcontainer/devcontainer.json` | VS Code конфіг, мержить compose файли |
| `.devcontainer/docker-compose.yml` | Сервіс devcontainer (без інфри) |
| `.devcontainer/Dockerfile` | Ubuntu база з PHP, Node, Python, Go, Composer, Bun |
| `.devcontainer/post-create.sh` | Після створення: встановлює залежності, міграції, перевірка сервісів |
| `compose.yaml` | Інфраструктурні сервіси (спільні з devcontainer) |
| `docker/postgres/init/` | SQL скрипти для автоматичного створення баз та ролей |

## Рантайми (встановлені в Dockerfile)

| Рантайм | Версія |
|---------|--------|
| PHP | 8.5 (ondrej/php PPA) |
| Node.js | 22 LTS |
| Python | 3.12 (системний) |
| Go | 1.24 |
| Composer | 2.x |
| Bun | 1.x |
| Claude Code | native installer |
| OpenCode | npm global |

## Інфраструктурні сервіси (з compose.yaml)

| Сервіс | Host | Порт | Credentials |
|--------|------|------|-------------|
| PostgreSQL 16 | `postgres` | 5432 | `app` / `app` |
| Redis 7 | `redis` | 6379 | без авторизації |
| OpenSearch 2.11 | `opensearch` | 9200 | без авторизації |
| RabbitMQ 3.13 | `rabbitmq` | 5672 / 15672 | `app` / `app` |
| Traefik 3.1 | `traefik` | 80 / 8080 | — |
| LiteLLM | `litellm` | 4000 | `dev-key` |

## Життєвий цикл post-create

Коли devcontainer стартує вперше, `post-create.sh` виконує:

1. Встановлення OpenCode плагінів
2. Очікування DNS резолвінгу postgres (до 60с)
3. Встановлення PHP залежностей (`composer install`)
4. Запуск міграцій БД (core + knowledge-agent)
5. Встановлення залежностей для E2E тестів (Playwright)
6. Health check всіх інфраструктурних сервісів
7. Виведення версій рантаймів

## Вирішення проблем

### Всі сервіси показують FAIL

Сервіси — частина основного compose стеку. Якщо вони не працюють:

```bash
docker compose up -d postgres redis opensearch rabbitmq
```

### Docker недоступний всередині devcontainer

Перевірте що Docker-in-Docker feature увімкнений в `devcontainer.json` та
Docker socket змонтований в `docker-compose.yml`.

### DNS не резолвить "postgres"

Devcontainer повинен бути в тих самих Docker мережах що й інфраструктура.
Перевірте `.devcontainer/docker-compose.yml`:

```yaml
networks:
  - dev-edge
  - agents-internal
```

### Конфлікти портів

Якщо основний compose стек працював до відкриття devcontainer, можуть бути
конфлікти портів. Спочатку зупиніть standalone стек:

```bash
docker compose down
```

Потім відкрийте в devcontainer — VS Code запустить все як один проєкт.
