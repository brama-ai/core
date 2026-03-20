# Підключення зовнішнього агента

Цей гайд пояснює, як підключити зовнішній репозиторій агента до робочого простору AI Community
Platform без редагування базових compose-файлів платформи.

## Огляд

Платформа підтримує два джерела агентів:

| Джерело | Розташування | Використання |
|---------|-------------|--------------|
| **Вбудований** | `apps/<agent-name>/` | Референсні агенти, що входять до платформи |
| **Зовнішній** | `projects/<agent-name>/` | Незалежно підтримувані репозиторії агентів |

Обидва джерела використовують однаковий runtime-контракт: ті самі Docker-мітки, manifest-ендпоінт,
health-ендпоінт та A2A-інтерфейс. Джерело не впливає на виявлення або управління lifecycle.

## Структура робочого простору

```
ai-community-platform/          ← репозиторій платформи (git clone)
  compose.yaml                  ← спільна інфраструктура
  compose.core.yaml             ← сервіс ядра платформи
  compose.agent-*.yaml          ← вбудовані фрагменти агентів
  compose.fragments/            ← фрагменти зовнішніх агентів (gitignored, локальні)
    my-agent.yaml               ← скопійовано з projects/my-agent/compose.fragment.yaml
  projects/                     ← checkout зовнішніх агентів (gitignored, локальні)
    my-agent/                   ← git clone репозиторію агента
      Dockerfile
      compose.fragment.yaml     ← шаблон compose-фрагмента від агента
      .env.local                ← секрети агента (ніколи не комітяться)
      ...
```

## Покрокове підключення

### 1. Клонувати репозиторій агента

```bash
# Варіант А: через Makefile (рекомендовано)
make external-agent-clone repo=https://github.com/your-org/my-agent name=my-agent

# Варіант Б: вручну
mkdir -p projects
git clone https://github.com/your-org/my-agent projects/my-agent
cp projects/my-agent/compose.fragment.yaml compose.fragments/my-agent.yaml
```

Команда `external-agent-clone`:
- Клонує репозиторій у `projects/<name>/`
- Копіює `compose.fragment.yaml` у `compose.fragments/<name>.yaml` (якщо є)
- Виводить наступні кроки

### 2. Перевірити compose-фрагмент

Відкрийте `compose.fragments/hello-agent.yaml` і перевірте:

- Ім'я сервісу закінчується на `-agent` (наприклад, `hello-agent`)
- Мітка `com.a2a.agent=true` присутня
- Мережа — `a2a-network`
- Healthcheck налаштований
- Використовується GHCR Image-First Workflow (див. нижче)

#### GHCR Image-First Workflow

Ми наполегливо рекомендуємо **GHCR Image-First Workflow** для зовнішніх агентів. Замість того, щоб збирати агента з вихідного коду при кожному розгортанні, платформа завантажує готовий образ з GitHub Container Registry (GHCR), використовуючи локальну збірку лише як запасний варіант для розробки.

**Приклад `compose.fragment.yaml` (з hello-agent):**
```yaml
services:
  hello-agent:
    image: ghcr.io/nmdimas/a2a-hello-agent:main
    build:
      context: ./projects/hello-agent
...
```
- **Коли завантажувати (Pull):** У продакшені або тестуванні, якщо образ існує на GHCR, Docker автоматично завантажить і використає його. Це значно швидше.
- **Коли збирати (Build):** Якщо ви активно розробляєте агента, ви можете виконати `docker compose build hello-agent`, щоб примусово запустити секцію `build:` для збірки з локального коду.

### 3. Налаштувати секрети агента

```bash
# Створити локальний env-файл агента (gitignored)
cp projects/my-agent/.env.local.example projects/my-agent/.env.local
nano projects/my-agent/.env.local
```

Compose-фрагмент посилається на цей файл через:
```yaml
env_file:
  - path: ./projects/my-agent/.env.local
    required: false
```

### 4. Запустити агента

```bash
make external-agent-up name=hello-agent
```

Це збирає образ агента з `projects/my-agent/` і запускає сервіс у мережі платформи.
Зміни в базових файлах платформи не потрібні.

### 5. Перевірити health та discovery

```bash
# Перевірити що агент запущений
docker compose logs -f my-agent

# Перевірити health-ендпоінт
curl -s http://localhost:<port>/health

# Запустити виявлення платформи
make agent-discover
```

Після виявлення агент з'являється в адмін-панелі платформи у розділі **Агенти → Маркетплейс**.

### 6. Встановити та увімкнути в адмін-панелі

1. Відкрийте адмін-панель платформи
2. Перейдіть до **Агенти → Маркетплейс**
3. Натисніть **Встановити** на картці агента (провізіонує сховище та запускає міграції)
4. Натисніть **Увімкнути** для активації агента

---

## Оновлення зовнішнього агента

```bash
# Отримати останній код
git -C projects/my-agent pull

# Перезібрати та перезапустити
make external-agent-up name=my-agent

# Якщо compose-фрагмент змінився — оновити його
cp projects/my-agent/compose.fragment.yaml compose.fragments/my-agent.yaml
make external-agent-up name=my-agent

# Запустити нові міграції (якщо агент використовує startup-міграції, достатньо перезапуску)
# Для ручної міграції:
docker compose exec my-agent <migration-command>

# Перевірити сумісність
make agent-discover
```

Якщо оновлений агент не відповідає конвенціям платформи, цикл виявлення відобразить порушення
в адмін-панелі. Дивіться розділ **Відкат** нижче.

### Відкат

```bash
# Зупинити агента
make external-agent-down name=my-agent

# Повернутися до відомого стабільного коміту
git -C projects/my-agent checkout <previous-tag>

# Перезапустити
make external-agent-up name=my-agent
```

---

## Відключення зовнішнього агента

```bash
# 1. Зупинити сервіс агента
make external-agent-down name=my-agent

# 2. Видалити compose-фрагмент
rm compose.fragments/my-agent.yaml

# 3. (Опціонально) Видалити checkout
rm -rf projects/my-agent

# 4. (Опціонально) Депровізіонувати в адмін-панелі
#    Адмін → Агенти → Встановлені → Видалити
#    Це видаляє базу даних агента, ключі Redis та індекси OpenSearch.
```

---

## Список зовнішніх агентів

```bash
make external-agent-list
```

Вивід:
```
  External agent compose fragments:
  ─────────────────────────────────
  my-agent                       projects/my-agent
  another-agent                  (no checkout)
```

---

## Контракт compose-фрагмента

Кожен compose-фрагмент зовнішнього агента ПОВИНЕН відповідати наступному:

| Вимога | Значення |
|--------|---------|
| Ім'я сервісу | Закінчується на `-agent` (наприклад, `my-agent`) |
| Мітка | `ai.platform.agent=true` |
| Мережа | `dev-edge` |
| Healthcheck | `GET /health` повертає `{"status":"ok"}` |
| Manifest | `GET /api/v1/manifest` повертає валідний Agent Card JSON |
| A2A ендпоінт | `POST /api/v1/a2a` (обов'язково якщо оголошені skills) |
| Міжагентні виклики | Тільки через `PLATFORM_CORE_URL/api/v1/a2a/send-message` |

Дивіться `docs/agent-requirements/conventions.md` для повного контракту.

---

## Змінні оточення та секрети

| Файл | Розташування | Призначення |
|------|-------------|-------------|
| Секрети платформи | `.env.local` (корінь репо) | LLM ключі, Telegram токен |
| Секрети агента | `projects/<name>/.env.local` | API ключі агента, паролі БД |
| Compose-фрагмент | `compose.fragments/<name>.yaml` | Визначення runtime-сервісу |

Ні `projects/`, ні `compose.fragments/*.yaml` не комітяться до репозиторію платформи.
Вони є локальними для оператора та додані до gitignore.

---

## Очікування CI

- **Репозиторій агента**: власні тести, лінтинг та перевірки збірки
- **Репозиторій платформи**: перевірки сумісності (`make conventions-test`) та E2E тести
- Зовнішні агенти не включаються до CI платформи за замовчуванням
- Для запуску перевірок конвенцій проти зовнішнього агента:
  ```bash
  AGENT_URL=http://localhost:<port> make conventions-test
  ```
