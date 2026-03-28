# Встановлення на Kubernetes

## Огляд

Цей гайд описує встановлення Brama на кластер Kubernetes за допомогою офіційного
Helm-чарту, розташованого в `deploy/charts/brama/`.

> **Статус**: Початковий скелет пакування. Чарт визначає операторський контракт для конфігурації,
> секретів, міграцій, проб та ingress. Публікація образів та хостинг чарт-репозиторію заплановані
> на майбутній реліз. Наразі встановлення виконується з локального шляху чарту.

Англійська версія: [`docs/guides/deployment/en/kubernetes-install.md`](../en/kubernetes-install.md)

Структура цього гайду розбита на чотири практичні частини:

- швидкий локальний старт
- production-style встановлення через values
- day-2 операції
- troubleshooting

Це ближче до operator-facing стилю документації на кшталт LangChain/LangSmith та Apache Airflow:
спочатку короткий шлях до працюючого середовища, потім стабільний сценарій для реального кластера.

## Режими деплою

Платформа підтримує два офіційних режими деплою:

| Режим | Найкраще для | Пакування |
|-------|-------------|-----------|
| **Docker Compose** | Локальна розробка, хобі, single-host продакшн | `compose.yaml` + Makefile |
| **Kubernetes** | Кластерні оператори, керована інфраструктура | Helm-чарт |

Цей гайд описує Kubernetes-шлях. Для Docker дивіться
[`docs/guides/deployment/ua/deployment.md`](./deployment.md).

## Передумови

- Kubernetes 1.27+
- Helm 3.12+
- `kubectl` налаштований для цільового кластера
- Ingress-контролер (рекомендується nginx-ingress)
- cert-manager (опціонально, для автоматизації TLS)
- Доступ до container registry з образами платформи

### Для локального K3s/dev профілю з workspace helper-ами

Якщо ви підіймаєте Brama локально через workspace Make targets, додатково потрібні:

- Docker для локального build образів
- Rancher Desktop або сумісний K3s setup з `rdctl`
- локальний chart path `brama-core/deploy/charts/brama`

> `make k8s-load` зараз імпортує образи через `rdctl shell sudo k3s ctr images import -`.
> Тобто цей helper flow орієнтований саме на локальний K3s у Rancher Desktop.
> Для `kind`, `minikube` або віддаленого кластера краще використовувати прямий `helm upgrade --install`
> і свій спосіб доставки образів (наприклад, через ssh piping у k3s containerd).

### Для віддаленого K3s кластера (без власного registry)
Якщо образів немає в публічному registry (наразі вони збираються локально), для деплою на віддалений сервер (наприклад, VPS з K3s) потрібно перенести локально зібрані образи на віддалену машину перед інсталяцією через Helm:

```bash
# Зібрати образи локально
make k8s-build

# Передати та імпортувати у віддалений K3s без registry
docker save brama-core:dev | ssh root@YOUR_SERVER_IP "k3s ctr images import -"
docker save agent-hello:dev | ssh root@YOUR_SERVER_IP "k3s ctr images import -"
```

## Швидкий старт: локальний K3s/dev

Це найкоротший шлях, якщо треба швидко підняти платформу локально.

### Що деплоїться в dev профілі

Поточний локальний профіль розгортає:

- `core`
- `core-scheduler`
- `hello-agent`
- PostgreSQL
- Redis
- RabbitMQ

### 1. Перевірте контекст кластера

```bash
make k8s-ctx
```

### 2. Виконайте повний bootstrap

```bash
make k8s-setup
```

Ця команда послідовно виконує:

1. `make k8s-build`
2. `make k8s-load`
3. `make k8s-secrets`
4. `make k8s-deploy`

### 3. Перевірте стан

```bash
make k8s-status
```

### 4. Відкрийте сервіс локально

```bash
make k8s-port-forward svc=core port=8080:80
curl -sf http://localhost:8080/health
```

### 5. Подивіться логи при проблемах

```bash
make k8s-logs svc=core
make k8s-logs svc=core-scheduler
make k8s-logs-all
```

### 6. Видаліть реліз

```bash
make k8s-destroy
```

### Команди quickstart, які варто знати

| Команда | Що робить |
|--------|-----------|
| `make k8s-ctx` | Показує поточний cluster context |
| `make k8s-build` | Будує локальні Docker images |
| `make k8s-load` | Імпортує образи в локальний K3s containerd |
| `make k8s-secrets` | Створює базовий secret для core |
| `make k8s-deploy` | Виконує `helm upgrade --install` |
| `make k8s-status` | Показує pods, services, ingress і Helm release |
| `make k8s-shell svc=core` | Відкриває shell у pod |
| `make k8s-port-forward svc=core port=8080:80` | Дає локальний доступ до сервісу |

## Топологія сервісів

### Обов'язкові сервіси застосунку

| Сервіс | Опис | Репліки |
|--------|------|---------|
| `core` | Основна платформа (PHP/Symfony) | 1+ |
| `core-scheduler` | Фоновий планувальник | 1 (фіксовано) |

### Опціональні агенти (увімкнути за потребою)

| Агент | За замовчуванням | Порт |
|-------|-----------------|------|
| `knowledge` | увімкнено | 8083 |
| `hello` | увімкнено | 8085 |
| `newsMaker` | вимкнено | 8087 |

### Залежності інфраструктури

| Залежність | Вбудована за замовчуванням | Рекомендовано для продакшн |
|------------|--------------------------|---------------------------|
| PostgreSQL | Так (sub-chart) | Зовнішня керована (RDS, Cloud SQL тощо) |
| Redis | Так (sub-chart) | Зовнішня керована (ElastiCache, Memorystore тощо) |
| OpenSearch | Ні | Зовнішня керована або не використовувати |
| RabbitMQ | Ні | Зовнішня керована або не використовувати |

## Крок 1: Підготовка секретів

Створіть Kubernetes Secrets перед встановленням чарту. Чарт не створює секрети — він посилається
на них за іменем.

### Секрети core

```bash
kubectl create namespace brama

kubectl create secret generic core-secrets \
  --namespace brama \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=EDGE_AUTH_JWT_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/ai_community_platform?serverVersion=16&charset=utf8" \
  --from-literal=LANGFUSE_PUBLIC_KEY="lf_pk_your_key" \
  --from-literal=LANGFUSE_SECRET_KEY="lf_sk_your_key"
```

### Секрети LiteLLM

```bash
kubectl create secret generic litellm-secrets \
  --namespace brama \
  --from-literal=LITELLM_MASTER_KEY="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/litellm?serverVersion=16&charset=utf8" \
  --from-literal=OPENROUTER_API_KEY="sk-or-your-key"
```

> **Примітка безпеки**: У продакшн рекомендується використовувати зовнішній оператор секретів
> (External Secrets Operator, Sealed Secrets, Vault Agent Injector) замість `kubectl create secret`.

### Секрети для локального helper flow

`make k8s-secrets` створює secret `brama-core-secrets` у namespace `brama` з такими ключами:

- `APP_SECRET`
- `EDGE_AUTH_JWT_SECRET`
- `DATABASE_URL`
- `REDIS_URL`
- `RABBITMQ_URL`
- `POSTGRES_PROVISIONER_URL`

Для локального dev цього достатньо. Для production краще:

- рознести секрети по сервісах
- не генерувати їх через shell history
- керувати ними через External Secrets / Vault / Sealed Secrets

## Крок 2: Підготовка values

Скопіюйте приклад values-файлу та налаштуйте його:

```bash
cp deploy/charts/brama/values-prod.example.yaml values-prod.yaml
```

Відредагуйте `values-prod.yaml`:

- Встановіть `ingress.hosts.*` на ваші реальні домени
- Встановіть поля `secretRef` відповідно до імен створених секретів
- Встановіть теги образів на цільову версію релізу
- Вимкніть вбудовані sub-charts при використанні зовнішніх сервісів:
  ```yaml
  postgresql:
    enabled: false
  redis:
    enabled: false
  externalDependencies:
    postgres:
      external: true
      host: your-postgres-host
    redis:
      external: true
      host: your-redis-host
  ```

### Який values-файл брати за основу

| Сценарій | Стартовий файл |
|----------|----------------|
| Локальний K3s / demo | `deploy/charts/brama/values-k3s-dev.yaml` |
| Production-like кластер | `deploy/charts/brama/values-prod.example.yaml` |

Практичне правило:

- `values-k3s-dev.yaml` для швидкого локального підйому
- `values-prod.example.yaml` як шаблон для реального rollout
- не варто "дорощувати" dev values до production без ревізії секретів, ingress та persistence

## Крок 3: Встановлення чарту

```bash
helm upgrade --install brama \
  ./deploy/charts/brama \
  --namespace brama \
  --create-namespace \
  -f values-prod.yaml \
  --wait \
  --timeout 15m
```

Прапор `--wait` змушує Helm чекати, поки всі Deployments та Jobs досягнуть готового стану.
Завдання міграції запускається як хук `post-install` перед стартом подів застосунку.

## Крок 4: Перевірка встановлення

### Перевірка статусу подів

```bash
kubectl get pods -n brama
```

Всі поди мають досягти стану `Running`. Под завдання міграції покаже `Completed`.

### Перевірка завдання міграції

```bash
kubectl get jobs -n brama
kubectl logs job/brama-migrate-1 -n brama
```

Логи завдання міграції мають завершуватися рядком `==> Migrations complete`.

### Перевірка статусу розгортання

```bash
kubectl rollout status deploy/brama-core -n brama
```

### Перевірка ingress

```bash
kubectl get ingress -n brama
```

### Тест health endpoint

```bash
curl -sf https://platform.example.com/health
```

Або через port-forward:

```bash
kubectl port-forward -n brama svc/brama-core 8080:80
curl -sf http://localhost:8080/health
```

### Перевірка Helm release

```bash
helm status brama -n brama
```

## Крок 5: Перевірка після встановлення

Мінімальні перевірки після свіжого встановлення:

- [ ] URL платформи завантажується та показує сторінку входу
- [ ] Вхід адміністратора працює
- [ ] Хоча б один health endpoint агента відповідає
- [ ] UI LiteLLM доступний (якщо увімкнено)
- [ ] Завдання міграції завершилося без помилок

## Щоденна експлуатація

### Оновити залежності чарту

```bash
cd deploy/charts/brama
helm dependency update
```

Або через workspace helper:

```bash
make k8s-deps
```

### Подивитися diff перед оновленням

```bash
make k8s-diff
```

### Оновити реліз

```bash
make k8s-upgrade
```

### Подивитися стан workload-ів

```bash
make k8s-status
kubectl get pods -n brama -o wide
```

### Подивитися логи сервісу

```bash
make k8s-logs svc=core
make k8s-logs svc=core-scheduler
make k8s-logs svc=agent-hello
```

### Зайти всередину pod

```bash
make k8s-shell svc=core
```

### Пробросити порт

```bash
make k8s-port-forward svc=core port=8080:80
```

## Операторський checklist перед production rollout

- [ ] Образи опубліковані в registry, доступному кластеру
- [ ] Namespace та ingress policy погоджені
- [ ] Secrets винесені у зовнішню систему керування
- [ ] Визначено persistence policy для stateful компонентів
- [ ] `postgresql.enabled` та `redis.enabled` вимкнені, якщо використовуються зовнішні managed сервіси
- [ ] Перевірено rollback сценарій через `helm rollback`
- [ ] Є post-deploy smoke checks для `/health`, login і scheduler

## Поведінка міграцій

Міграції запускаються як Kubernetes Job з анотаціями Helm-хуків:

```yaml
helm.sh/hook: pre-upgrade,post-install
helm.sh/hook-weight: "-5"
helm.sh/hook-delete-policy: before-hook-creation,hook-succeeded
```

Це означає:
- При свіжому встановленні: завдання міграції запускається після створення ресурсів чарту
- При оновленні: завдання міграції запускається до старту нових подів застосунку
- Завершені завдання автоматично очищаються при наступному релізі

## Вирішення проблем

### Под застряг у Pending

```bash
kubectl describe pod <pod-name> -n brama
```

Типові причини: недостатньо ресурсів кластера, відсутній PVC, відсутній секрет.

### Завдання міграції завершилося з помилкою

```bash
kubectl logs job/brama-migrate-1 -n brama
```

Перевірте проблеми з підключенням до бази даних або конфлікти схеми.

### Под core у CrashLoopBackOff

```bash
kubectl logs deploy/brama-core -n brama --previous
```

Типові причини: відсутнє посилання на секрет, неправильний DATABASE_URL, невдала міграція.

### `make k8s-load` не працює

Найчастіша причина: у локальному середовищі немає `rdctl`, або кластер не Rancher Desktop K3s.

Що робити:

- перевірити `rdctl version`
- або пропустити helper `k8s-load` і використовувати image з registry
- або адаптувати flow під свій runtime (`kind load docker-image`, `minikube image load` тощо)
- для розгортання на віддалений K3s використовуйте SSH-pipe: `docker save my-image | ssh user@host "k3s ctr images import -"`

### Helm реліз є, але сервіс недоступний

Перевірте послідовно:

1. `kubectl get ingress -n brama`
2. `kubectl get svc -n brama`
3. `kubectl describe ingress <name> -n brama`
4. `kubectl port-forward -n brama svc/brama-core 8080:80`

Якщо через port-forward `/health` працює, проблема майже напевно в ingress, DNS або TLS шарі.

## Розгортання k3s на одному вузлі Hetzner VPS

Цей розділ описує міграцію з Docker Compose на k3s на Hetzner CX32 VPS
(4 vCPU / 8 GB RAM). Це рекомендований production-шлях для single-operator розгортань.

Англійська версія з повними командами: [`docs/guides/deployment/en/kubernetes-install.md`](../en/kubernetes-install.md)

### Передумови

- Hetzner CX32 VPS (або аналог) з Ubuntu 24.04+
- SSH-доступ як root
- Доменне ім'я, що вказує на IP VPS
- Налаштовані GitHub Actions secrets: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`

### Бюджет RAM

Повний стек вміщується в 8 GB RAM з консервативними resource requests (~2.7 Gi загалом):

| Сервіс | Requests | Limits |
|--------|----------|--------|
| PostgreSQL | 256 Mi | 512 Mi |
| Redis | 64 Mi | 128 Mi |
| OpenSearch | 768 Mi | 1536 Mi |
| RabbitMQ | 128 Mi | 256 Mi |
| Core | 256 Mi | 512 Mi |
| Core Scheduler | 128 Mi | 256 Mi |
| LiteLLM | 256 Mi | 384 Mi |
| Knowledge Agent + Worker | 256 Mi | 512 Mi |
| Hello Agent | 64 Mi | 128 Mi |
| Wiki Agent | 64 Mi | 128 Mi |
| News Maker Agent | 128 Mi | 256 Mi |
| Dev Reporter Agent | 64 Mi | 128 Mi |
| Langfuse (web+worker) | 256 Mi | 512 Mi |
| **Разом** | **~2.7 Gi** | **~5.0 Gi** |

> **dev-agent** вимкнено за замовчуванням у `values-hetzner.yaml` — він важкий (git + gh CLI).
> Вмикайте лише за потреби та моніторте RAM через `kubectl top nodes`.

### Кроки міграції

Детальні команди з поясненнями — в англійській версії:
[`docs/guides/deployment/en/kubernetes-install.md`](../en/kubernetes-install.md#k3s-single-node-deployment-on-hetzner-vps)

Короткий план:

1. **Резервна копія PostgreSQL** — `pg_dumpall` перед зупинкою Docker Compose
2. **Зупинити Docker Compose** — `docker compose down`
3. **Встановити k3s** — `curl -sfL https://get.k3s.io | sh -`
4. **Встановити Helm** — `get-helm-3` скрипт
5. **Розгорнути локальний registry** — Deployment + HostNetwork на порту 5000
6. **Налаштувати registries.yaml** — довіряти `registry.localhost:5000`
7. **Встановити cert-manager** — Let's Encrypt ClusterIssuer
8. **Зібрати та запушити образи** — `bash brama-core/deploy/build-and-push.sh`
9. **Створити namespace та secrets** — `kubectl create secret generic ...`
10. **Helm upgrade --install** — з `values-hetzner.yaml`
11. **Відновити PostgreSQL** — `kubectl cp` + `psql -f backup.sql`
12. **Перевірити** — `kubectl get pods -n brama`, `/health` endpoints

### Rollback до Docker Compose

Якщо k3s-деплой не вдався:

```bash
helm uninstall brama -n brama
systemctl stop k3s
docker compose up -d  # дані PostgreSQL збережені у Docker volumes
```

## Наступні кроки

- [Runbook оновлення](./kubernetes-upgrade.md) — як оновити до нового релізу
- [Матриця топологій деплою](./deployment-topology.md) — підтримувані топології та компроміси
- [Гайд Docker деплою](./deployment.md) — шлях Docker Compose
