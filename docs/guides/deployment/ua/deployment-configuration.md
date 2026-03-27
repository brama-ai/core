# Конфігурація деплою

## Огляд

Цей документ пояснює, як конфігурувати AI Community Platform для різних режимів деплою:
Docker Compose, Kubernetes і зовнішні managed services.

## Файли конфігурації

### `.env.deployment`

Головний файл конфігурації деплою задає змінні оточення для підключення до сервісів. Скопіюй
`.env.deployment.example` у `.env.deployment` і адаптуй під своє середовище:

```bash
cp .env.deployment.example .env.deployment
```

### Структура змінних оточення

Конфігурація використовує пошарову модель:

1. Базова конфігурація сервісу: host, port, credentials
2. Похідні URL: будуються з базових значень
3. Повне перевизначення: будь-яку змінну можна override-нути вручну

Приклад:

```bash
# Базова конфігурація
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_USER=app
POSTGRES_PASSWORD=app

# Похідний URL (можна повністю перевизначити)
DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@${POSTGRES_HOST}:${POSTGRES_PORT}/ai_community_platform
```

## Режими деплою

### Docker Compose

Для Docker Compose дефолтні значення з `.env.deployment.example` зазвичай підходять без змін:

```bash
POSTGRES_HOST=postgres
REDIS_HOST=redis
OPENSEARCH_HOST=opensearch
```

### Kubernetes

Для Kubernetes перевизнач хости на service DNS або зовнішні endpoints:

```bash
POSTGRES_HOST=postgresql-service
REDIS_HOST=redis-service
OPENSEARCH_HOST=opensearch-service.logging.svc.cluster.local

DATABASE_URL=postgresql://user:pass@postgres.example.com:5432/ai_community_platform
REDIS_URL=redis://redis.example.com:6379
```

### Зовнішні managed services

Для продакшну з керованою інфраструктурою:

```bash
DATABASE_URL=postgresql://user:pass@postgres.amazonaws.com:5432/ai_community_platform
REDIS_URL=redis://redis.amazonaws.com:6379
OPENSEARCH_URL=https://search-domain.us-east-1.es.amazonaws.com
```

## Категорії сервісів

### Core Infrastructure

- PostgreSQL: основна база даних
- Redis: кеш і сесії
- OpenSearch: пошук і логування
- RabbitMQ: черги повідомлень для knowledge workflows

### Platform Services

- Core platform: головний application service
- LiteLLM: LLM proxy service
- Langfuse: LLM observability (optional)

### Agent Services

Кожен агент може мати власну базу даних і конфігурацію:

- Knowledge Agent: окрема PostgreSQL база
- News Maker Agent: окрема PostgreSQL база
- Hello Agent: stateless, використовує shared core services

## Health Checks

Усі сервіси мають розширені health endpoints:

- `/health` — базова liveness перевірка
- `/health/live` — Kubernetes liveness probe
- `/health/ready` — Kubernetes readiness probe з перевіркою залежностей

## Міграція з hardcoded конфігурації

Якщо в тебе старий деплой із жорстко прописаними сервісами:

1. Створи `.env.deployment` з поточними значеннями
2. Переконайся, що сервіси все ще працюють
3. Поступово переведи конфіг на зовнішні сервіси через env vars
4. Змін у коді не має знадобитися

## Troubleshooting

### Проблеми з підключенням до сервісів

Перевір, що runtime змінні виставлені правильно:

```bash
env | grep -E "(DATABASE_URL|REDIS_URL|OPENSEARCH_URL)"
```

### Падіння health checks

Використовуй readiness endpoints для діагностики залежностей:

```bash
curl http://brama-core/health/ready
curl http://knowledge-agent/health/ready
```

### Валідація конфігурації

Платформа валідує конфіг при старті й логує проблеми як warnings або boot errors.
