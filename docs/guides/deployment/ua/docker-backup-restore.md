# Резервне копіювання та відновлення Docker

Цей гайд описує процедури резервного копіювання та відновлення для Docker самостійного деплою.

Англійська версія: [docker-backup-restore.md](../en/docker-backup-restore.md)

## Що резервувати

| Актив | Розташування | Частота |
|-------|-------------|---------|
| База core-платформи | контейнер `postgres`, БД `ai_community_platform` | Перед кожним оновленням; щодня в продакшн |
| База агента знань | контейнер `postgres`, БД `knowledge_agent` | Перед кожним оновленням |
| База агента новин | контейнер `postgres`, БД `news_maker_agent` | Перед кожним оновленням |
| База агента звітів | контейнер `postgres`, БД `dev_reporter_agent` | Перед кожним оновленням |
| База LiteLLM | контейнер `postgres`, БД `litellm` | Перед кожним оновленням |
| Бази Langfuse | контейнер `langfuse-postgres` | Перед кожним оновленням (якщо Langfuse увімкнено) |
| Секрети та конфіг | `.env.local`, `compose.override.yaml`, `docker/openclaw/.env` | Перед кожним оновленням |
| Runtime-стан OpenClaw | `.local/openclaw/state/` | Перед кожним оновленням (якщо OpenClaw увімкнено) |

## Резервне копіювання

### Бази даних

```bash
# Core-платформа
docker compose exec postgres pg_dump -U app ai_community_platform \
  > backup-core-$(date +%Y%m%d-%H%M).sql

# Агент знань (якщо встановлено)
docker compose exec postgres pg_dump -U knowledge_agent knowledge_agent \
  > backup-knowledge-$(date +%Y%m%d-%H%M).sql

# Агент новин (якщо встановлено)
docker compose exec postgres pg_dump -U news_maker_agent news_maker_agent \
  > backup-news-$(date +%Y%m%d-%H%M).sql

# Агент звітів (якщо встановлено)
docker compose exec postgres pg_dump -U dev_reporter_agent dev_reporter_agent \
  > backup-dev-reporter-$(date +%Y%m%d-%H%M).sql

# LiteLLM
docker compose exec postgres pg_dump -U app litellm \
  > backup-litellm-$(date +%Y%m%d-%H%M).sql
```

### Бази Langfuse (якщо доповнення Langfuse увімкнено)

```bash
docker compose exec langfuse-postgres pg_dump -U postgres postgres \
  > backup-langfuse-$(date +%Y%m%d-%H%M).sql
```

### Файли конфігурації

```bash
cp .env.local .env.local.bak
cp compose.override.yaml compose.override.yaml.bak 2>/dev/null || true
cp docker/openclaw/.env docker/openclaw/.env.bak 2>/dev/null || true
cp -r .local/openclaw/state/ .local/openclaw/state.bak/ 2>/dev/null || true
```

### Повне резервне копіювання томів (альтернатива)

Для повного резервного копіювання на рівні томів, спочатку зупиніть стек:

```bash
make down

# Резервне копіювання іменованих томів
docker run --rm \
  -v ai-community-platform_postgres-data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/postgres-data-$(date +%Y%m%d).tar.gz -C /data .

docker run --rm \
  -v ai-community-platform_redis-data:/data \
  -v $(pwd)/backups:/backup \
  alpine tar czf /backup/redis-data-$(date +%Y%m%d).tar.gz -C /data .

make up
```

---

## Відновлення

### Відновлення бази даних

```bash
# Зупинити задіяний сервіс (опціонально, але рекомендовано)
docker compose stop core

# Відновити core-базу
docker compose exec -T postgres psql -U app ai_community_platform \
  < backup-core-YYYYMMDD-HHMM.sql

# Перезапустити сервіс
docker compose start core
```

Повторіть для інших баз за потреби.

### Відновлення файлів конфігурації

```bash
cp .env.local.bak .env.local
cp compose.override.yaml.bak compose.override.yaml 2>/dev/null || true
cp docker/openclaw/.env.bak docker/openclaw/.env 2>/dev/null || true
cp -r .local/openclaw/state.bak/ .local/openclaw/state/ 2>/dev/null || true
```

Після відновлення конфігу OpenClaw перезапустіть шлюз:

```bash
docker compose restart openclaw-gateway
```

### Відновлення з резервної копії томів

```bash
make down

docker run --rm \
  -v ai-community-platform_postgres-data:/data \
  -v $(pwd)/backups:/backup \
  alpine sh -c "rm -rf /data/* && tar xzf /backup/postgres-data-YYYYMMDD.tar.gz -C /data"

make up
```

---

## Перевірка після відновлення

```bash
make ps

# Перевірити здоров'я core
curl -sf http://localhost/health && echo "core OK"

# Перевірити підключення до бази
docker compose exec postgres psql -U app -d ai_community_platform -c "SELECT 1"
```

---

## Рекомендації щодо зберігання резервних копій

- Зберігайте резервні копії поза хостом деплою (віддалене сховище, S3-сумісний бакет або окремий сервер)
- Зберігайте щонайменше останні 3 щоденні резервні копії та резервну копію перед кожним оновленням
- Регулярно тестуйте процедури відновлення в непродакшн середовищі
