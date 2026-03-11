# Встановлення на Kubernetes

## Огляд

Цей гайд описує встановлення AI Community Platform на кластер Kubernetes за допомогою офіційного
Helm-чарту, розташованого в `deploy/charts/ai-community-platform/`.

> **Статус**: Початковий скелет пакування. Чарт визначає операторський контракт для конфігурації,
> секретів, міграцій, проб та ingress. Публікація образів та хостинг чарт-репозиторію заплановані
> на майбутній реліз. Наразі встановлення виконується з локального шляху чарту.

Англійська версія: [`docs/guides/deployment/en/kubernetes-install.md`](../en/kubernetes-install.md)

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
kubectl create namespace acp

kubectl create secret generic core-secrets \
  --namespace acp \
  --from-literal=APP_SECRET="$(openssl rand -hex 32)" \
  --from-literal=EDGE_AUTH_JWT_SECRET="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/ai_community_platform?serverVersion=16&charset=utf8" \
  --from-literal=LANGFUSE_PUBLIC_KEY="lf_pk_your_key" \
  --from-literal=LANGFUSE_SECRET_KEY="lf_sk_your_key"
```

### Секрети LiteLLM

```bash
kubectl create secret generic litellm-secrets \
  --namespace acp \
  --from-literal=LITELLM_MASTER_KEY="$(openssl rand -hex 32)" \
  --from-literal=DATABASE_URL="postgresql://app:PASSWORD@postgres-host:5432/litellm?serverVersion=16&charset=utf8" \
  --from-literal=OPENROUTER_API_KEY="sk-or-your-key"
```

> **Примітка безпеки**: У продакшн рекомендується використовувати зовнішній оператор секретів
> (External Secrets Operator, Sealed Secrets, Vault Agent Injector) замість `kubectl create secret`.

## Крок 2: Підготовка values

Скопіюйте приклад values-файлу та налаштуйте його:

```bash
cp deploy/charts/ai-community-platform/values-prod.example.yaml values-prod.yaml
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

## Крок 3: Встановлення чарту

```bash
helm upgrade --install ai-community-platform \
  ./deploy/charts/ai-community-platform \
  --namespace acp \
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
kubectl get pods -n acp
```

Всі поди мають досягти стану `Running`. Под завдання міграції покаже `Completed`.

### Перевірка завдання міграції

```bash
kubectl get jobs -n acp
kubectl logs job/ai-community-platform-migrate-1 -n acp
```

Логи завдання міграції мають завершуватися рядком `==> Migrations complete`.

### Перевірка статусу розгортання

```bash
kubectl rollout status deploy/ai-community-platform-core -n acp
```

### Перевірка ingress

```bash
kubectl get ingress -n acp
```

### Тест health endpoint

```bash
curl -sf https://platform.example.com/health
```

Або через port-forward:

```bash
kubectl port-forward -n acp svc/ai-community-platform-core 8080:80
curl -sf http://localhost:8080/health
```

## Крок 5: Перевірка після встановлення

Мінімальні перевірки після свіжого встановлення:

- [ ] URL платформи завантажується та показує сторінку входу
- [ ] Вхід адміністратора працює
- [ ] Хоча б один health endpoint агента відповідає
- [ ] UI LiteLLM доступний (якщо увімкнено)
- [ ] Завдання міграції завершилося без помилок

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
kubectl describe pod <pod-name> -n acp
```

Типові причини: недостатньо ресурсів кластера, відсутній PVC, відсутній секрет.

### Завдання міграції завершилося з помилкою

```bash
kubectl logs job/ai-community-platform-migrate-1 -n acp
```

Перевірте проблеми з підключенням до бази даних або конфлікти схеми.

### Под core у CrashLoopBackOff

```bash
kubectl logs deploy/ai-community-platform-core -n acp --previous
```

Типові причини: відсутнє посилання на секрет, неправильний DATABASE_URL, невдала міграція.

## Наступні кроки

- [Runbook оновлення](./kubernetes-upgrade.md) — як оновити до нового релізу
- [Матриця топологій деплою](./deployment-topology.md) — підтримувані топології та компроміси
- [Гайд Docker деплою](./deployment.md) — шлях Docker Compose
