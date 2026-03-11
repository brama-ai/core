# Матриця топологій деплою

## Огляд

AI Community Platform підтримує два офіційних режими деплою. Цей документ описує підтримувані
топології, їх компроміси та спільний контракт деплою, що застосовується до обох.

Англійська версія: [`docs/guides/deployment/en/deployment-topology.md`](../en/deployment-topology.md)

## Підтримувані топології

| Топологія | Режим | Найкраще для | Статус |
|-----------|-------|-------------|--------|
| Single-host Docker Compose | Docker | Локальна розробка, хобі, малий self-hosted | Підтримується |
| Kubernetes з вбудованою інфраструктурою | Kubernetes | Кластерні оператори, dev/staging | Початковий скелет |
| Kubernetes із зовнішньою керованою інфраструктурою | Kubernetes | Продакшн кластерні оператори | Початковий скелет |

> **Примітка**: Kubernetes-пакування знаходиться на початковій стадії скелету. Чарт визначає
> операторський контракт, але публікація образів та хостинг chart-репозиторію заплановані на
> майбутній реліз.

## Порівняння режимів деплою

| Аспект | Docker Compose | Kubernetes |
|--------|---------------|------------|
| **Інтерфейс оператора** | `make` targets + compose-файли | `helm upgrade` + `values.yaml` |
| **Ін'єкція конфігурації** | `.env.local` + `compose.override.yaml` | Kubernetes Secrets + `values.yaml` |
| **Міграції** | `make migrate` (явна команда) | Job-хук pre-upgrade/post-install |
| **Health checks** | Docker healthcheck + curl | Kubernetes readiness/liveness/startup probes |
| **Ingress** | Traefik (вбудований) | Ingress-контролер (надається оператором) |
| **TLS** | Traefik ACME або зовнішній | cert-manager або зовнішній |
| **Масштабування** | Тільки single-host | Горизонтальне масштабування для stateless-сервісів |
| **Stateful залежності** | Завжди вбудовані | Вбудовані (за замовчуванням) або зовнішні керовані |
| **Відкат** | `git checkout` + `make up` | `helm rollback` |
| **Процес оновлення** | Pull/checkout + migrate + restart | `helm upgrade` з migration hook |

## Класифікація сервісів

### Обов'язкові сервіси застосунку

| Сервіс | Docker | Kubernetes | Примітки |
|--------|--------|------------|---------|
| `core` | `compose.core.yaml` | `core` Deployment | Основна платформа |
| `core-scheduler` | `compose.core.yaml` | `core-scheduler` Deployment | Тільки одна репліка |

### Опціональні агенти

| Агент | Docker compose-файл | Ключ Kubernetes values | За замовчуванням |
|-------|--------------------|-----------------------|-----------------|
| knowledge-agent | `compose.agent-knowledge.yaml` | `agents.knowledge.enabled` | true |
| hello-agent | `compose.agent-hello.yaml` | `agents.hello.enabled` | true |
| news-maker-agent | `compose.agent-news-maker.yaml` | `agents.newsMaker.enabled` | false |
| wiki-agent | `compose.agent-wiki.yaml` | Ще не в чарті | — |
| dev-agent | `compose.agent-dev.yaml` | Ще не в чарті | — |
| dev-reporter-agent | `compose.agent-dev-reporter.yaml` | Ще не в чарті | — |

### Stateful залежності інфраструктури

| Залежність | Docker | Kubernetes (вбудована) | Kubernetes (зовнішня) |
|------------|--------|----------------------|----------------------|
| PostgreSQL | Вбудована | `postgresql.enabled: true` | `externalDependencies.postgres.external: true` |
| Redis | Вбудована | `redis.enabled: true` | `externalDependencies.redis.external: true` |
| OpenSearch | Вбудована | Ще не в чарті | Заплановано |
| RabbitMQ | Вбудована | Ще не в чарті | Заплановано |

**Рекомендація для продакшн Kubernetes**: використовуйте зовнішні керовані сервіси для PostgreSQL
та Redis. Вбудовані sub-charts підходять для dev та staging середовищ.

## Спільний контракт деплою

Обидва режими деплою використовують один логічний контракт:

### Вхідні дані конфігурації

| Вхідні дані | Docker | Kubernetes |
|-------------|--------|------------|
| Секрети застосунку | `.env.local` | Kubernetes Secret + `secretRef` |
| Публічний URL | `compose.override.yaml` env | `core.env.EDGE_AUTH_LOGIN_BASE_URL` |
| API ключі LLM | `.env.local` | Kubernetes Secret |
| URL бази даних | Авто-підключення через compose network | `DATABASE_URL` в Secret |
| Ключі Langfuse | `.env.local` або compose env | `core.env` або Secret |

### Поведінка міграцій

Міграції завжди явні — вони ніколи не є прихованим побічним ефектом запуску контейнера.

| Режим | Як запускаються міграції |
|-------|--------------------------|
| Docker | `make migrate` (та варіанти для агентів) |
| Kubernetes | Job-хук pre-upgrade/post-install |

### Ворота перевірки оновлення

Обидва режими використовують однакові логічні ворота перевірки після оновлення:

1. Міграція завершилася успішно
2. Health endpoint core відповідає
3. Критичний worker (планувальник) здоровий
4. Публічна точка входу (ingress/домен) доступна
5. Хоча б один критичний агентський потік працює (опціональний smoke)

## Пов'язані гайди

- [Встановлення на Kubernetes](./kubernetes-install.md)
- [Runbook оновлення Kubernetes](./kubernetes-upgrade.md)
- [Деплой Docker](./deployment.md)
