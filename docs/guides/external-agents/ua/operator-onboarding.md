# Посібник оператора — Зовнішні агенти

Цей посібник описує, як додати, запустити, оновити та видалити зовнішньо підтримуваний агент
у самостійно розгорнутій платформі AI Community Platform.

---

## Передумови

- Платформа клонована та запущена (`make up`)
- Docker Engine 24+ з плагіном Compose v2
- Git

---

## 1. Клонування репозиторію агента

```bash
# З кореня платформи
make external-agent-clone repo=https://github.com/your-org/my-agent.git name=my-agent
```

Це клонує репозиторій у `projects/my-agent/`.

Або вручну:

```bash
mkdir -p projects
git clone https://github.com/your-org/my-agent.git projects/my-agent
```

---

## 2. Увімкнення compose-фрагмента

Скопіюйте compose-фрагмент агента в операторський каталог фрагментів:

```bash
cp projects/my-agent/compose.fragment.yaml compose.fragments/my-agent.yaml
```

`Makefile` платформи автоматично підключає всі файли `compose.fragments/*.yaml` до compose-стеку.

---

## 3. Налаштування середовища

Якщо агент потребує змінних середовища понад стандартні значення у `compose.fragment.yaml`,
додайте їх у `compose.override.yaml`:

```yaml
# compose.override.yaml
services:
  my-agent:
    environment:
      MY_AGENT_API_KEY: your-secret-key
```

---

## 4. Запуск агента

```bash
# Запустити агент (збирає образ за потреби)
make external-agent-up name=my-agent

# Або запустити весь стек разом з новим агентом
make up
```

---

## 5. Міграції (якщо є)

Якщо агент оголошує блок `storage.postgres` у маніфесті, запустіть міграції:

```bash
docker compose -f compose.yaml -f compose.core.yaml \
  -f compose.fragments/my-agent.yaml \
  exec my-agent <команда-міграції>
```

Точна команда міграції задокументована у README агента.

---

## 6. Перевірка health та discovery

```bash
# Перевірити health-ендпоінт
curl -s http://localhost:<порт-агента>/health

# Перевірити manifest-ендпоінт
curl -s http://localhost:<порт-агента>/api/v1/manifest | jq .

# Запустити discovery в core
make agent-discover
```

Агент має з'явитися в адмін-панелі core у розділі **Agents → Marketplace** протягом 60 секунд
після запуску контейнера.

---

## 7. Встановлення та увімкнення в адмінці

1. Відкрийте адмін-панель core
2. Перейдіть до **Agents → Marketplace**
3. Натисніть **Install** на картці агента
4. Після завершення встановлення натисніть **Enable**

Агент активний і маршрутизує трафік.

---

## Оновлення зовнішнього агента

```bash
# Отримати останній код
git -C projects/my-agent pull

# Перезібрати та перезапустити
make external-agent-up name=my-agent

# Запустити міграції (якщо є база даних)
docker compose -f compose.yaml -f compose.core.yaml \
  -f projects/my-agent/compose.fragment.yaml \
  exec my-agent <команда-міграції>

# Перевірити health
curl -s http://localhost:<порт-агента>/health
```

### Відкат

```bash
# Відкотитися до попереднього коміту
git -C projects/my-agent checkout <попередній-тег-або-коміт>

# Перезібрати та перезапустити
make external-agent-up name=my-agent
```

---

## Видалення зовнішнього агента

```bash
# Зупинити контейнер агента
make external-agent-down name=my-agent

# Видалити скопійований compose-фрагмент
rm -f compose.fragments/my-agent.yaml

# За потреби видалити checkout
rm -rf projects/my-agent
```

Якщо агент має постійні дані (база Postgres, індекс OpenSearch), спочатку депровізуйте його
через адмін-панель core:

1. Відкрийте **Agents → Installed**
2. Натисніть **Delete** на картці агента
3. Підтвердіть діалог депровізації

---

## Перегляд виявлених зовнішніх агентів

```bash
make external-agent-list
```

---

## Пов'язані документи

- [Зовнішній workspace агента (EN)](../en/external-agent-workspace.md)
- [Посібник з міграції (EN)](../en/migration-playbook.md)
- [Конвенції платформи агентів](../../../agent-requirements/conventions.md)
