# Контракт Docker-деплою

Цей документ визначає офіційний контракт деплою для Docker-шляху самостійного хостингу.
Він охоплює топологію сервісів, конфігураційні входи, секрети, очікування щодо здоров'я,
поведінку міграцій та політику версіонування образів.

Docker і Kubernetes пакування поділяють цей контракт. Відмінності в пакуванні не перевизначають
поведінку сервісів.

Англійська версія: [docker-deployment-contract.md](../en/docker-deployment-contract.md)

## Топологія сервісів

### Обов'язкові сервіси застосунку

Ці сервіси повинні працювати в кожному підтримуваному Docker-деплої:

| Сервіс | Роль | Compose-файл |
|--------|------|--------------|
| `traefik` | Edge-проксі та маршрутизація | `compose.yaml` |
| `core` | Основна платформа (Symfony) | `compose.core.yaml` |
| `core-scheduler` | Планувальник фонових задач | `compose.core.yaml` |
| `litellm` | LLM-проксі | `compose.yaml` |

### Stateful-інфраструктура (вбудована за замовчуванням)

Ці сервіси вбудовані в Docker-шлях. У Kubernetes їх можна замінити зовнішніми керованими сервісами.

| Сервіс | Роль | Compose-файл |
|--------|------|--------------|
| `postgres` | Основна реляційна база даних | `compose.yaml` |
| `redis` | Кеш і черга | `compose.yaml` |
| `opensearch` | Зберігання структурованих логів | `compose.yaml` |
| `rabbitmq` | Брокер повідомлень | `compose.yaml` |

### Опціональні платформні доповнення

Ці сервіси підключаються через окремі compose-фрагменти і не є обов'язковими для мінімального деплою:

| Сервіс | Роль | Compose-фрагмент |
|--------|------|-----------------|
| `langfuse-web`, `langfuse-worker` | LLM-спостережуваність | `compose.langfuse.yaml` |
| `openclaw-gateway`, `openclaw-cli` | Telegram-бот шлюз | `compose.openclaw.yaml` |
| `slides` | Презентаційні слайди | `compose.slides.yaml` |

### Опціональні агенти

Кожен агент підключається через власний compose-фрагмент:

| Фрагмент | Агент |
|----------|-------|
| `compose.agent-knowledge.yaml` | Агент бази знань |
| `compose.agent-hello.yaml` | Привітальний агент |
| `compose.agent-news-maker.yaml` | Агент агрегації новин |
| `compose.agent-wiki.yaml` | Вікі-агент + чат |
| `compose.agent-dev.yaml` | Агент-асистент розробника |
| `compose.agent-dev-reporter.yaml` | Агент звітів пайплайну |

## Конфігураційні входи

### Змінні оточення

Усі сервіси читають конфігурацію зі змінних оточення. Основний вхідний файл — `.env.local`
(у gitignore, керується оператором).

**Обов'язкові для будь-якого деплою:**

| Змінна | Опис |
|--------|------|
| `OPENROUTER_API_KEY` | Ключ LLM-провайдера (або `OPENAI_API_KEY` / `ANTHROPIC_API_KEY`) |

**Обов'язкові для продакшн:**

| Змінна | Опис |
|--------|------|
| `EDGE_AUTH_JWT_SECRET` | Секрет підпису JWT для edge-аутентифікації |
| `EDGE_AUTH_LOGIN_BASE_URL` | Публічна базова URL платформи (напр. `https://yourdomain.org`) |
| `EDGE_AUTH_COOKIE_DOMAIN` | Домен cookie для cross-subdomain auth (напр. `.yourdomain.org`) |
| `LANGFUSE_PUBLIC_URL` | Публічна URL Langfuse (якщо доповнення Langfuse увімкнено) |

**Опціональні:**

| Змінна | Опис |
|--------|------|
| `TELEGRAM_BOT_TOKEN` | Токен Telegram-бота (потрібен тільки якщо увімкнено OpenClaw) |
| `OPENCLAW_GATEWAY_TOKEN` | Токен аутентифікації шлюзу (авто-генерується `make bootstrap`) |

### Секрети

Секрети розповсюджуються командою `make bootstrap`, яка читає `.env.local` і записує:

| Файл | Призначення | Створюється |
|------|-------------|-------------|
| `.env.local` | API-ключі та токени | Оператором (вручну) |
| `docker/openclaw/.env` | Токен шлюзу OpenClaw і конфіг Telegram | `make bootstrap` |
| `.local/openclaw/state/openclaw.json` | Runtime-конфіг OpenClaw | `make bootstrap` |

**Ніколи не комітьте ці файли.** Вони у gitignore.

### Конфігурація публічних URL

Продакшн-домени налаштовуються через `compose.override.yaml` (у gitignore):

```yaml
services:
  core:
    environment:
      EDGE_AUTH_LOGIN_BASE_URL: https://yourdomain.org
      EDGE_AUTH_COOKIE_DOMAIN: .yourdomain.org

  langfuse-web:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.yourdomain.org
      NEXTAUTH_URL: https://langfuse.yourdomain.org

  langfuse-worker:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.yourdomain.org
      NEXTAUTH_URL: https://langfuse.yourdomain.org
```

### Точки перевизначення Compose

Підтримуваний механізм перевизначення — `compose.override.yaml`. Цей файл автоматично
завантажується змінною `COMPOSE` у Makefile, якщо присутній. Використовуйте його для:

- Встановлення продакшн-доменів
- Перевизначення лімітів ресурсів
- Додавання змінних, специфічних для середовища
- Вимкнення або переналаштування опціональних сервісів

Не змінюйте базові compose-файли для налаштувань, специфічних для середовища.

## Здоров'я та готовність

### Health-ендпоінти

Кожен HTTP-сервіс надає `GET /health`. Очікувана відповідь — HTTP 200.

| Сервіс | Порт | Health URL |
|--------|------|-----------|
| `core` | 80 | `http://localhost/health` |
| `knowledge-agent` | 8083 | `http://localhost:8083/health` |
| `news-maker-agent` | 8084 | `http://localhost:8084/health` |
| `hello-agent` | 8085 | `http://localhost:8085/health` |
| `dev-reporter-agent` | 8087 | `http://localhost:8087/health` |
| `dev-agent` | 8088 | `http://localhost:8088/health` |
| `wiki-agent` | 8090 | `http://localhost:8090/health` |

### Здоров'я інфраструктури

Stateful-сервіси надають Docker healthcheck-и:

| Сервіс | Перевірка |
|--------|-----------|
| `postgres` | `pg_isready -U app -d ai_community_platform` |
| `redis` | `redis-cli ping` |
| `rabbitmq` | `rabbitmq-diagnostics -q ping` |

## Міграції

Міграції — це явні команди, а не неявні побічні ефекти запуску контейнера.

### Команди міграцій

Запускайте тільки міграції для сервісів, встановлених у середовищі:

```bash
make litellm-db-init       # Ініціалізація бази LiteLLM (ідемпотентно)
make migrate               # Core-платформа (Doctrine)
make knowledge-migrate     # Агент знань (Doctrine)
make dev-reporter-migrate  # Агент звітів (Doctrine)
make dev-agent-migrate     # Dev-агент (Doctrine)
make news-migrate          # Агент новин (Alembic)
```

### Безпека міграцій

- Міграції ідемпотентні та безпечні для повторного запуску.
- Перед оновленням зробіть резервну копію баз даних.
- Якщо міграція несумісна з попередньою версією, відновіть з резервної копії замість відкату застосунку.

## Версіонування образів та політика оновлень

### Поточна модель (на основі джерельного коду)

Поточна модель деплою збирає образи з джерельного коду на цільовому хості:

- `make up` запускає `docker compose ... up --build -d`
- Задеплоєна ревізія — це поточний Git-коміт
- Відкат — `git checkout <previous-ref>` + `make up`

### Майбутня модель (опубліковані образи)

Коли платформа почне публікувати версіоновані образи, процес оновлення зміниться:

1. Оновити теги образів у `compose.override.yaml`
2. Запустити `docker compose pull`
3. Запустити явні команди міграцій
4. Перезапустити сервіси у підтримуваному порядку
5. Перевірити здоров'я та відкотитися до попередніх тегів у разі потреби

## Підтримуваний Compose-бандл

Повний підтримуваний стек збирається змінною `COMPOSE` у Makefile:

```
compose.yaml                    # Інфраструктура + LiteLLM
compose.core.yaml               # Core-платформа + планувальник
compose.agent-*.yaml            # Опціональні агенти
compose.langfuse.yaml           # Опціонально: LLM-спостережуваність
compose.openclaw.yaml           # Опціонально: Telegram-бот шлюз
compose.slides.yaml             # Опціонально: презентації
compose.override.yaml           # Перевизначення оператора (якщо присутній)
```

Використовуйте `make ps` для перегляду запущених сервісів поточного compose-бандлу.
