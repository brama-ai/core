# Вирішення проблем Docker

Поширені проблеми та кроки вирішення для Docker самостійного деплою.

Англійська версія: [docker-troubleshooting.md](../en/docker-troubleshooting.md)

## Діагностика

### Перевірити стан сервісів

```bash
make ps
```

### Перевірити логи конкретного сервісу

```bash
docker compose logs <service-name> --tail 50
```

Поширені назви сервісів: `core`, `core-scheduler`, `postgres`, `redis`, `rabbitmq`, `litellm`,
`openclaw-gateway`, `langfuse-web`, `langfuse-worker`.

### Перевірити всі health-ендпоінти

```bash
for port in 80 8083 8084 8085 8087 8088 8090; do
  echo -n "Port $port: "
  curl -sf http://localhost:$port/health && echo "OK" || echo "FAIL"
done
```

---

## Сервіс не запускається

```bash
docker compose logs <service-name> --tail 50
```

Поширені причини:

- Відсутня або неправильна змінна оточення — перевірте `.env.local` та `compose.override.yaml`
- Конфлікт портів — перевірте чи інший процес не використовує порт: `ss -tlnp | grep <port>`
- Залежність не здорова — перевірте `make ps` на наявність нездорових інфраструктурних сервісів

---

## Проблеми з підключенням до бази даних

```bash
# Перевірити чи postgres здоровий
docker compose exec postgres pg_isready -U app -d ai_community_platform

# Тестовий запит
docker compose exec postgres psql -U app -d ai_community_platform -c "SELECT 1"
```

Якщо postgres не запущений:

```bash
docker compose up -d postgres
```

Зачекайте поки він стане здоровим, потім перезапустіть задіяний сервіс:

```bash
docker compose restart core
```

---

## LiteLLM "Not connected to DB" / "Authentication Error"

Ця помилка на `http://localhost:4000/ui/login` означає що LiteLLM не може використовувати
метадані Postgres DB.

```bash
make litellm-db-init
docker compose restart litellm
docker compose logs --tail=50 litellm
```

---

## OpenClaw не відповідає в Telegram

```bash
docker compose logs openclaw-gateway --tail 30
```

Перевірити чи встановлено webhook:

```bash
docker compose exec openclaw-cli openclaw channels status
```

Якщо токен шлюзу неправильний або відсутній:

```bash
# Перегенерувати та застосувати
make bootstrap
docker compose restart openclaw-gateway
```

---

## Помилки міграцій

Якщо команда міграції не вдалася:

```bash
# Перевірити статус міграцій
docker compose exec core php bin/console doctrine:migrations:status

# Перевірити логи
docker compose logs core --tail 50
```

Якщо схема бази даних не синхронізована після невдалого оновлення, відновіть з резервної копії:

```bash
docker compose exec -T postgres psql -U app ai_community_platform \
  < backup-core-YYYYMMDD.sql
```

Дивіться [Резервне копіювання та відновлення Docker](./docker-backup-restore.md).

---

## Агент не з'являється в платформі

Якщо агент запущений але не видно в платформі:

```bash
# Повторно запустити виявлення агентів
make agent-discover

# Перевірити здоров'я агента
curl -sf http://localhost:8083/health    # knowledge-agent
curl -sf http://localhost:8085/health    # hello-agent
```

---

## Langfuse не завантажується

```bash
docker compose logs langfuse-web --tail 30
docker compose logs langfuse-worker --tail 30
```

Якщо сервіси Langfuse не запущені:

```bash
make up-observability
```

---

## Проблеми з OpenSearch

Перевірити здоров'я OpenSearch:

```bash
curl -s http://localhost:9200/_cluster/health | jq .status
```

Якщо OpenSearch не запускається через ліміти пам'яті:

```bash
# Перевірити поточне значення vm.max_map_count
sysctl vm.max_map_count

# Встановити (потрібно для OpenSearch)
sysctl -w vm.max_map_count=262144

# Зробити постійним
echo "vm.max_map_count=262144" >> /etc/sysctl.conf
```

---

## Проблеми з RabbitMQ

```bash
docker compose exec rabbitmq rabbitmq-diagnostics -q ping
docker compose logs rabbitmq --tail 30
```

---

## Проблеми з маршрутизацією Traefik

Перевірити Traefik dashboard: `http://localhost:8080/dashboard/`

Перевірити логи Traefik:

```bash
make logs-traefik
```

Якщо сервіс недоступний через маршрутизацію, перевірте його Traefik-мітки в compose-файлі та
переконайтеся що сервіс запущений і здоровий.

---

## Стек не запускається після оновлення

Якщо `make up` не вдається після оновлення:

1. Перевірте який сервіс не вдається: `make ps` та `docker compose logs <service>`
2. Перевірте валідність compose-файлів: `docker compose config`
3. Якщо додано новий сервіс, перевірте чи потрібні нові змінні оточення в `.env.local`
4. Якщо проблема не вирішується, виконайте відкат: дивіться [Гайд з оновлення Docker](./docker-upgrade.md#відкат)

---

## Довідник корисних команд

```bash
make ps                    # Стан сервісів
make logs                  # Слідкувати за всіма логами
make logs-core             # Логи core
make logs-openclaw         # Логи шлюзу OpenClaw
make logs-langfuse         # Логи Langfuse
make logs-litellm          # Логи LiteLLM
make logs-traefik          # Логи Traefik
make bootstrap             # Перерозподілити секрети
make agent-discover        # Оновити реєстр агентів
make litellm-db-init       # Виправити базу LiteLLM
```
