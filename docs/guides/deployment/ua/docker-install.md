# Гайд з встановлення Docker (самостійний хостинг)

Цей гайд описує встановлення AI Community Platform на одному хості за допомогою Docker Compose.
Це підтримуваний шлях для хобі-продакшн та простих самостійних інсталяцій.

Англійська версія: [docker-install.md](../en/docker-install.md)

Повний контракт деплою (топологія сервісів, змінні оточення, секрети, здоров'я, міграції):
[docker-deployment-contract.md](./docker-deployment-contract.md)

## Підтримувана топологія

```
Internet → Traefik (порт 80) → Сервіси застосунку
             ├── core (PHP/Symfony)         — основна платформа
             ├── core-scheduler             — фонові задачі
             ├── litellm (Python)           — LLM-проксі
             └── [опціональні агенти та доповнення]

Інфраструктура (вбудована):
  PostgreSQL 16, Redis 7, OpenSearch 2.11, RabbitMQ 3.13

Опціональні доповнення (окремі compose-фрагменти):
  Langfuse (LLM-спостережуваність), OpenClaw (Telegram-бот), Slides
```

## Передумови

- Ubuntu 22.04+ або будь-який Linux з підтримкою Docker
- Docker Engine 24+ з Compose plugin v2
- Git
- Make
- Доменне ім'я з DNS на IP сервера (бажано для продакшн)
- SSH-доступ (бажано по ключу)

## Крок 1: Встановити Docker

```bash
curl -fsSL https://get.docker.com | sh
```

Перевірка:

```bash
docker --version
docker compose version
```

## Крок 2: Клонувати репозиторій

```bash
mkdir -p /root/app
cd /root/app
git clone https://github.com/nmdimas/ai-community-platform.git
cd ai-community-platform
```

## Крок 3: Налаштувати секрети

```bash
cp .env.local.example .env.local
nano .env.local
```

Обов'язкові змінні:

| Змінна | Обов'язкова | Опис |
|--------|-------------|------|
| `OPENROUTER_API_KEY` | Так (один LLM-ключ) | API-ключ з [openrouter.ai](https://openrouter.ai/) |
| `TELEGRAM_BOT_TOKEN` | Опціонально | Токен від Telegram @BotFather (потрібен для OpenClaw) |
| `OPENCLAW_GATEWAY_TOKEN` | Авто-генерація | Залишити порожнім — `make bootstrap` згенерує |
| `LANGFUSE_PUBLIC_URL` | Для продакшн | `https://langfuse.yourdomain.org` |

## Крок 4: Налаштувати продакшн-домени

Створіть `compose.override.yaml` (у gitignore) з налаштуваннями домену:

```bash
cat > compose.override.yaml << 'EOF'
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
EOF
```

Замініть `yourdomain.org` на ваш реальний домен. Пропустіть цей крок для локальних інсталяцій.

## Крок 5: Bootstrap секретів

```bash
make bootstrap
```

Ця команда читає `.env.local` і:
- Генерує токен шлюзу OpenClaw (якщо не встановлено)
- Записує `docker/openclaw/.env`
- Створює `.local/openclaw/state/openclaw.json`

Запускайте `make bootstrap` один раз перед першим запуском. Повторно запускайте тільки коли
реліз явно вимагає перерозподілу секретів.

## Крок 6: Зібрати та запустити стек

```bash
make setup    # Завантажити/зібрати всі образи та встановити залежності
make up       # Запустити повний стек у фоні
```

`make up` запускає `docker compose ... up --build -d` для повного підтримуваного бандлу.

## Крок 7: Ініціалізувати бази даних

```bash
make litellm-db-init    # Створити базу LiteLLM (ідемпотентно)
```

## Крок 8: Запустити міграції

Запускайте міграції для встановлених сервісів:

```bash
make migrate               # Core-платформа
make knowledge-migrate     # Агент знань (якщо встановлено)
make dev-reporter-migrate  # Агент звітів (якщо встановлено)
make dev-agent-migrate     # Dev-агент (якщо встановлено)
make news-migrate          # Агент новин (якщо встановлено)
```

## Крок 9: Перевірка

Перевірити що всі сервіси запущені:

```bash
make ps
```

Перевірити health-ендпоінти:

```bash
curl -sf http://localhost/health && echo "core OK"
curl -sf http://localhost:8083/health && echo "knowledge-agent OK"
curl -sf http://localhost:8085/health && echo "hello-agent OK"
```

Або перевірити всі одразу:

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo "OK" || echo "FAIL"
done
```

## DNS та маршрутизація

Створіть A-записи для домену та піддоменів на IP сервера:

| Піддомен | Сервіс |
|----------|--------|
| `yourdomain.org` | Core-платформа |
| `langfuse.yourdomain.org` | Langfuse (якщо увімкнено) |
| `openclaw.yourdomain.org` | OpenClaw (якщо увімкнено) |
| `litellm.yourdomain.org` | LiteLLM |
| `slides.yourdomain.org` | Slides (якщо увімкнено) |
| `traefik.yourdomain.org` | Traefik dashboard |

## TLS

TLS не налаштований за замовчуванням. Варіанти:

- **Вбудований Let's Encrypt в Traefik (ACME)**: додайте ACME-конфігурацію до `docker/traefik/traefik.yml`
- **Cloudflare proxy**: увімкніть Cloudflare orange-cloud для автоматичного TLS
- **Зовнішній nginx**: завершуйте TLS upstream і проксіюйте на порт 80

## Чеклист безпеки

Перед виходом у продакшн змініть ці dev-значення за замовчуванням:

- [ ] `APP_SECRET` в `apps/core/.env` та кожному агенті `.env`
- [ ] `EDGE_AUTH_JWT_SECRET` в `apps/core/.env`
- [ ] `EDGE_AUTH_COOKIE_DOMAIN` — встановити `.yourdomain.org`
- [ ] Пароль адміна: `docker compose exec core php bin/console security:hash-password`
- [ ] Пароль Langfuse — змінити в налаштуваннях акаунту Langfuse UI
- [ ] Налаштувати TLS
- [ ] Обмежити порти OpenSearch, RabbitMQ, Redis тільки на localhost (firewall)
- [ ] Обмежити доступ до Traefik dashboard

## Облікові дані за замовчуванням (змінити перед продакшн)

| Поверхня | Облікові дані за замовчуванням |
|----------|-------------------------------|
| Core admin / edge login | `admin` / `test-password` |
| Langfuse app login | `admin@local.dev` / `test-password` |
| LiteLLM UI | `admin` / `dev-key` |

## Наступні кроки

- [Гайд з оновлення Docker](./docker-upgrade.md)
- [Резервне копіювання та відновлення Docker](./docker-backup-restore.md)
- [Вирішення проблем Docker](./docker-troubleshooting.md)
- [Контракт деплою](./docker-deployment-contract.md)
