# Деплой на продакшн

## Огляд

Brama Agent Platform працює як Docker Compose стек на одному VPS. Деплой автоматизований через
GitHub Actions при push в `main`. Цей гайд описує початкове налаштування сервера, конфігурацію та
операції.

## Передумови

- Ubuntu 22.04+ (або будь-який Linux з Docker)
- Docker Engine 24+ з Compose plugin v2
- Git
- Make
- Доменне ім'я з DNS на IP сервера (бажано)
- SSH доступ (бажано по ключу)

## Архітектура

```
Internet → Traefik (порт 80) → Сервіси
             ├── core (PHP/Symfony)         — основна платформа
             ├── knowledge-agent (PHP)      — база знань
             ├── hello-agent (PHP)          — привітальний агент
             ├── news-maker-agent (Python)  — агрегація новин
             ├── wiki-agent (Node.js)       — вікі + чат
             ├── dev-agent (PHP)            — асистент розробника
             ├── dev-reporter-agent (PHP)   — звіти пайплайну
             ├── langfuse-web (Next.js)     — LLM спостережуваність
             ├── openclaw-gateway (Node.js) — Telegram бот
             ├── litellm (Python)           — LLM проксі
             └── traefik dashboard
Інфраструктура:
  PostgreSQL 16, Redis 7, OpenSearch 2.11, RabbitMQ 3.13
  Langfuse: ClickHouse, MinIO, окремий Postgres + Redis
```

## Початкове налаштування сервера

### 1. Встановити Docker

```bash
curl -fsSL https://get.docker.com | sh
```

### 2. Клонувати репозиторій

```bash
mkdir -p /root/app
cd /root/app
git clone <workspace-repo>
cd brama-workspace
```

### 3. Створити файл оточення

```bash
cp .env.local.example .env.local
nano .env.local
```

Обов'язкові змінні:

| Змінна | Обов'язкова | Опис |
|--------|-------------|------|
| `OPENROUTER_API_KEY` | Так (один LLM ключ) | API ключ з [openrouter.ai](https://openrouter.ai/) |
| `TELEGRAM_BOT_TOKEN` | Опціонально | Токен від Telegram @BotFather |
| `OPENCLAW_GATEWAY_TOKEN` | Авто-генерація | Залишити порожнім для авто-генерації |
| `LANGFUSE_PUBLIC_URL` | Для продакшн | `https://langfuse.brama.dev` |

### 4. Створити Compose Override (конфіг домену)

Продакшн домени налаштовуються через `compose.override.yaml` (не в git):

```bash
cat > compose.override.yaml << 'EOF'
services:
  core:
    environment:
      EDGE_AUTH_LOGIN_BASE_URL: https://brama.dev

  langfuse-web:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.brama.dev
      NEXTAUTH_URL: https://langfuse.brama.dev

  langfuse-worker:
    environment:
      LANGFUSE_PUBLIC_URL: https://langfuse.brama.dev
      NEXTAUTH_URL: https://langfuse.brama.dev
EOF
```

За потреби замініть ці приклади на інший продакшн-домен, якщо використовуєте не `brama.dev`.

### 5. Bootstrap та запуск

```bash
make bootstrap    # Розповсюджує секрети по всіх сервісах
make setup        # Збирає всі Docker образи
make up           # Запускає повний стек
make litellm-db-init  # Ініціалізує базу LiteLLM
make migrate      # Запускає міграції баз даних
```

### 6. Перевірка

```bash
# Перевірити що всі сервіси працюють
docker compose -f compose.yaml -f compose.core.yaml \
  $(for f in compose.agent-*.yaml compose.langfuse.yaml compose.openclaw.yaml; do [ -f "$f" ] && echo -n "-f $f "; done) ps

# Тестові health endpoint-и
curl -s http://localhost/health
curl -s http://localhost:8083/health    # knowledge-agent
curl -s http://localhost:8085/health    # hello-agent
```

## Домени та маршрутизація

Traefik маршрутизує трафік по hostname. Кожен compose файл має подвійні `Host()` правила — працюють і локально, і на продакшн:

| Сервіс | Продакшн URL | Локальний URL |
|--------|-------------|---------------|
| Core платформа Brama | `https://brama.dev` | `http://localhost` |
| Langfuse | `https://langfuse.brama.dev` | `http://langfuse.localhost` |
| OpenClaw | `https://openclaw.brama.dev` | `http://openclaw.localhost` |
| LiteLLM | `https://litellm.brama.dev` | `http://litellm.localhost` |
| Traefik | `https://traefik.brama.dev` | `http://traefik.localhost` |

**DNS**: Створіть A-записи для `brama.dev` та піддоменів `langfuse.`, `openclaw.`, `litellm.`,
`traefik.` на IP сервера.

**TLS**: Поки не налаштований. Варіанти:
- Вбудований Let's Encrypt в Traefik (ACME)
- Зовнішній реверс-проксі (Cloudflare, nginx)

## GitHub Actions авто-деплой

Push в `main` автоматично запускає деплой через `.github/workflows/deploy.yml`.

### Налаштування GitHub Secrets

В Settings репозиторію → Environments → `production`:

| Secret | Значення |
|--------|----------|
| `SSH_HOST` | IP сервера (напр., `46.62.135.86`) |
| `SSH_PORT` | SSH порт (за замовчуванням: `22`) |
| `SSH_USER` | `root` |
| `SSH_PRIVATE_KEY` | Вміст Ed25519 приватного ключа |

### Як це працює

1. Визначає які сервіси змінилися (аналізує змінені файли)
2. Підключається по SSH до сервера
3. Виконує `git fetch && git checkout origin/main`
4. Збирає та перезапускає тільки змінені сервіси: `docker compose up -d --build --no-deps <сервіси>`

### Ручний деплой

Через GitHub Actions UI → "Run workflow" → опціонально вказати сервіси (через кому, або `all`).

Або вручну по SSH:

```bash
ssh root@your-server
cd /root/app/brama-workspace
git pull origin main
docker compose -f compose.yaml -f compose.core.yaml \
  $(for f in compose.agent-*.yaml compose.langfuse.yaml compose.openclaw.yaml; do [ -f "$f" ] && echo -n "-f $f "; done) \
  up -d --build
```

## Файли конфігурації

### Файли в Git (з dev значеннями)

| Файл | Що змінити для продакшн |
|------|------------------------|
| `apps/brama-core/.env` | `APP_SECRET`, `EDGE_AUTH_JWT_SECRET` |
| `apps/*/env` | `APP_SECRET` для кожного агента |
| `docker/litellm/config.yaml` | Визначення моделей (використовує `OPENROUTER_API_KEY` з env) |

### Файли НЕ в Git (тільки на сервері)

| Файл | Призначення | Створюється |
|------|-------------|-------------|
| `.env.local` | API ключі, токени | Вручну |
| `compose.override.yaml` | Конфіг домену | Вручну |
| `docker/openclaw/.env` | Токени gateway + Telegram | `make bootstrap` |
| `.local/openclaw/state/openclaw.json` | Конфіг OpenClaw runtime | `make bootstrap` |

## Міграції баз даних

```bash
# Core платформа
make migrate

# Для окремих агентів
make knowledge-migrate
make dev-reporter-migrate
make news-migrate
```

## Моніторинг та здоров'я

### Health endpoints

Кожен агент має `GET /health`:

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo " OK" || echo " FAIL"
done
```

### Langfuse (спостережуваність LLM)

- URL: `https://langfuse.brama.dev`
- Вхід: edge auth → Langfuse app login
- За замовчуванням: `admin@local.dev` / `test-password`

### Логи

```bash
# Всі сервіси
docker compose logs -f --tail 100

# Окремий сервіс
docker compose logs -f core
docker compose logs -f openclaw-gateway
docker compose logs -f litellm
```

### OpenSearch (структуровані логи)

```bash
curl -s 'http://localhost:9200/platform_logs_*/_search?size=5&sort=@timestamp:desc' | jq '.hits.hits[]._source'
```

## Чеклист безпеки

Для продакшн змініть ці dev значення:

- [ ] `APP_SECRET` в `apps/brama-core/.env` та кожному агенті `.env`
- [ ] `EDGE_AUTH_JWT_SECRET` в `apps/brama-core/.env`
- [ ] `EDGE_AUTH_COOKIE_DOMAIN` — встановити `.brama.dev` для cross-subdomain cookies
- [ ] Пароль адміна — змінити через `docker compose exec core php bin/console security:hash-password`
- [ ] Пароль Langfuse — змінити в налаштуваннях акаунту Langfuse UI
- [ ] Налаштувати TLS (Let's Encrypt або Cloudflare)
- [ ] Обмежити порти OpenSearch, RabbitMQ, Redis тільки на localhost (firewall)
- [ ] Обмежити доступ до Traefik dashboard

## Зовнішні агенти

Щоб додати зовнішній агент до деплою:

```bash
# Клонувати репозиторій агента в projects/
make external-agent-clone repo=https://github.com/your-org/my-agent name=my-agent

# Переглянути та налаштувати compose-фрагмент
nano compose.fragments/my-agent.yaml

# Налаштувати секрети агента
cp projects/my-agent/.env.local.example projects/my-agent/.env.local
nano projects/my-agent/.env.local

# Запустити агента
make external-agent-up name=my-agent

# Запустити виявлення
make agent-discover
```

Checkout зовнішніх агентів (`projects/`) та compose-фрагменти (`compose.fragments/*.yaml`)
додані до gitignore та є локальними для оператора. Вони не комітяться до репозиторію платформи.

Дивіться `docs/guides/external-agents/ua/onboarding.md` для повного гайду.

## Вирішення проблем

### Сервіс не запускається

```bash
docker compose logs <service-name> --tail 50
```

### Проблеми з базою даних

```bash
docker compose exec postgres pg_isready
docker compose exec postgres psql -U app -d ai_community_platform -c "SELECT 1"
```

### OpenClaw не відповідає в Telegram

```bash
docker compose logs openclaw-gateway --tail 30
# Перевірити webhook:
docker compose exec openclaw-cli openclaw channels status
```

### LiteLLM "Not connected to DB"

```bash
make litellm-db-init
docker compose restart litellm
```
