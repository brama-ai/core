# Гайд з оновлення Docker

Цей гайд описує оновлення AI Community Platform на Docker самостійному деплої.

Англійська версія: [docker-upgrade.md](../en/docker-upgrade.md)

## Коли використовувати цей гайд

Використовуйте цей гайд, коли платформа задеплоєна на одному VM або хості з клонованим
репозиторієм і запущена через підтримуваний compose-стек (`make up`).

## Огляд процесу оновлення

1. Чеклист перед оновленням (резервна копія, огляд release notes)
2. Отримати цільову ревізію
3. Переглянути зміни конфігурації
4. Перезібрати та запустити оновлений стек
5. Запустити кроки міграцій
6. Оновити стан runtime-виявлення
7. Перевірити здоров'я сервісів
8. Запустити smoke-перевірку

Якщо перевірка не пройшла на будь-якому кроці, виконайте [Відкат](#відкат).

---

## Чеклист перед оновленням

### 1. Записати поточну ревізію

```bash
git rev-parse HEAD
git status --short
```

Переконайтеся, що немає незакомічених продакшн-правок, які будуть перезаписані.

### 2. Зробити резервну копію перед оновленням

**Бази даних:**

```bash
# Core-платформа
docker compose exec postgres pg_dump -U app ai_community_platform > backup-core-$(date +%Y%m%d).sql

# Агент знань (якщо встановлено)
docker compose exec postgres pg_dump -U knowledge_agent knowledge_agent > backup-knowledge-$(date +%Y%m%d).sql

# Агент новин (якщо встановлено)
docker compose exec postgres pg_dump -U news_maker_agent news_maker_agent > backup-news-$(date +%Y%m%d).sql

# Агент звітів (якщо встановлено)
docker compose exec postgres pg_dump -U dev_reporter_agent dev_reporter_agent > backup-dev-reporter-$(date +%Y%m%d).sql

# LiteLLM
docker compose exec postgres pg_dump -U app litellm > backup-litellm-$(date +%Y%m%d).sql
```

**Файли конфігурації:**

```bash
cp .env.local .env.local.bak
cp compose.override.yaml compose.override.yaml.bak 2>/dev/null || true
cp docker/openclaw/.env docker/openclaw/.env.bak 2>/dev/null || true
cp -r .local/openclaw/state/ .local/openclaw/state.bak/ 2>/dev/null || true
```

### 3. Переглянути release notes

Перевірте release notes на наявність:

- Нових обов'язкових змінних оточення
- Вимог до міграцій
- Змінених compose-фрагментів
- Видалених або перейменованих сервісів
- Нових цілей міграцій

### 4. Зафіксувати поточний стан сервісів

```bash
make ps
```

---

## Стандартний процес оновлення

### Крок 1: Отримати цільову ревізію

```bash
git fetch origin
git checkout <target-ref>
```

Якщо деплоїте з `main` напряму:

```bash
git pull origin main
```

### Крок 2: Переглянути зміни конфігурації

Перевірте, чи новий реліз змінює:

- `.env.local.example` (нові обов'язкові змінні)
- Compose-файли (нові сервіси, змінені порти)
- Обов'язкові секрети
- Налаштування домену в `compose.override.yaml`

Якщо секрети або згенерований конфіг OpenClaw змінилися, перезапустіть bootstrap:

```bash
make bootstrap
```

Використовуйте це тільки коли реліз явно вимагає перерозподілу секретів.

### Крок 3: Перезібрати та запустити оновлений стек

Для повного оновлення стеку:

```bash
make up
```

Для цільового оновлення одного сервісу (тільки коли release notes підтверджують ізольованість змін):

```bash
docker compose -f compose.yaml -f compose.core.yaml \
  $(for f in compose.agent-*.yaml compose.langfuse.yaml compose.openclaw.yaml compose.slides.yaml; do [ -f "$f" ] && echo -n "-f $f "; done) \
  up -d --build --no-deps <service-name>
```

### Крок 4: Запустити кроки міграцій

Запускайте міграції для встановлених сервісів:

```bash
make litellm-db-init       # Ідемпотентно — безпечно повторювати
make migrate               # Core-платформа
make knowledge-migrate     # Агент знань (якщо встановлено)
make dev-reporter-migrate  # Агент звітів (якщо встановлено)
make dev-agent-migrate     # Dev-агент (якщо встановлено)
make news-migrate          # Агент новин (якщо встановлено)
```

Примітки:

- Запускайте тільки міграції для встановлених сервісів.
- `wiki-agent` не має окремої цілі міграції.
- Якщо реліз вводить нову ціль міграції, вона вказана в release notes.

### Крок 5: Оновити стан runtime-виявлення

Якщо реліз змінює маніфести агентів, мітки, URL або додає/видаляє агентів:

```bash
make agent-discover
```

### Крок 6: Перевірити здоров'я сервісів

Перевірити стан контейнерів:

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

Перевірити логи нещодавно перезапущених сервісів:

```bash
make logs-core
make logs-openclaw
make logs-litellm
```

Натисніть `Ctrl+C` після підтвердження стабільності логів.

### Крок 7: Запустити smoke-перевірку

Мінімальні рекомендовані smoke-перевірки:

- Відкрити основну URL платформи
- Відкрити admin login
- Відкрити Langfuse або OpenClaw якщо увімкнено
- Запустити один відомо-безпечний агентний флоу
- Перевірити планувальник або воркер якщо реліз їх торкнувся

Якщо середовище підтримує, запустіть smoke-набір проекту:

```bash
make e2e-smoke
```

---

## Відкат

Відкат безпечний тільки якщо оновлення не ввело незворотних схемних або дата-міграцій.
Якщо міграція несумісна з попередньою версією, відновіть з резервної копії замість відкату.

### Крок 1: Повернутися до попередньої ревізії

```bash
git checkout <previous-ref>
```

### Крок 2: Перезібрати та перезапустити попередні сервіси

```bash
make up
```

### Крок 3: Відновити дані якщо потрібно

Якщо невдале оновлення змінило схему несумісно:

```bash
# Відновити core-базу
docker compose exec -T postgres psql -U app ai_community_platform < backup-core-YYYYMMDD.sql
```

Якщо стан OpenClaw або згенерований конфіг змінився несумісно:

```bash
cp docker/openclaw/.env.bak docker/openclaw/.env
cp -r .local/openclaw/state.bak/ .local/openclaw/state/
docker compose restart openclaw-gateway
```

### Крок 4: Повторно перевірити здоров'я

```bash
make ps
curl -sf http://localhost/health && echo "core OK"
```

### Крок 5: Задокументувати невдале оновлення

Запишіть:

- Цільова ревізія
- Крок де сталася помилка
- Стан міграцій на момент помилки
- Задіяні сервіси
- Виконані дії відкату

---

## Ворота верифікації

Наступні перевірки є стандартними воротами релізу для підтримуваного Docker-оновлення:

| Ворота | Команда |
|--------|---------|
| Успіх міграцій | Немає помилок від `make migrate` та per-agent migrate |
| Здоров'я core | `curl -sf http://localhost/health` повертає 200 |
| Здоров'я критичного воркера | `make ps` показує `core-scheduler` запущеним |
| Здоров'я публічного ентрипоінту | URL платформи відкривається в браузері |
| Опціональний smoke-набір | `make e2e-smoke` (якщо E2E-стек доступний) |
