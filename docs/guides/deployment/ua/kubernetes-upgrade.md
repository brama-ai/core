# Runbook оновлення Kubernetes

## Огляд

Цей runbook описує підтримуваний процес оновлення для Kubernetes-інсталяції, керованої через
офіційний Helm-чарт `deploy/charts/ai-community-platform/`.

Англійська версія: [`docs/guides/deployment/en/kubernetes-upgrade.md`](../en/kubernetes-upgrade.md)

> **Статус**: Цей runbook відображає початковий скелет Helm-чарту. Імена workload-ів, завдань
> міграції та деталі chart-репозиторію будуть уточнені в міру розвитку пакування.

## Коли використовувати

Використовуйте цей runbook при:
- Оновленні до нового релізу платформи на існуючій Kubernetes-інсталяції
- Відкаті невдалого оновлення
- Відновленні після часткового збою міграції

Для свіжого встановлення дивіться [`kubernetes-install.md`](./kubernetes-install.md).

## Чеклист перед оновленням

### 1. Зафіксуйте поточний стан релізу

```bash
helm list -n acp
helm history ai-community-platform -n acp
kubectl get pods -n acp
```

Запишіть поточний номер ревізії — він знадобиться для відкату.

### 2. Перегляньте release notes цільового релізу

Перед оновленням перевірте:
- Зміни версії чарту та версії застосунку
- Нові або змінені ключі `values.yaml`
- Нові обов'язкові секрети
- Попередження про міграції або зміни схеми
- Зміни проб або ingress

### 3. Порівняйте поточні та цільові values

```bash
helm get values ai-community-platform -n acp -o yaml > current-values.yaml
```

Порівняйте `current-values.yaml` з вашим `values-prod.yaml` та новим `values.yaml` чарту.

Якщо доступний плагін Helm diff:

```bash
helm diff upgrade ai-community-platform ./deploy/charts/ai-community-platform \
  -n acp \
  -f values-prod.yaml
```

### 4. Підтвердьте наявність резервних копій

Перед застосуванням будь-якого оновлення переконайтеся, що є актуальні резервні копії:
- Бази даних PostgreSQL (всі БД, що використовуються core та агентами)
- Стан Redis (якщо увімкнено persistence)
- Будь-які зовнішні джерела секретів

### 5. Перевірте здоров'я кластера

```bash
kubectl get deploy,statefulset,job -n acp
kubectl top pods -n acp
```

Не оновлюйте, якщо існуючі workload-и нездорові або кластер під тиском ресурсів.

## Стандартний процес оновлення

### 1. Оновіть теги образів у values

У вашому `values-prod.yaml` оновіть теги образів до цільового релізу:

```yaml
core:
  image:
    tag: "0.2.0"

coreScheduler:
  image:
    tag: "0.2.0"

agents:
  knowledge:
    image:
      tag: "0.2.0"
  hello:
    image:
      tag: "0.2.0"

migrations:
  image:
    tag: "0.2.0"
```

### 2. Оновіть залежності чарту (якщо потрібно)

```bash
helm dependency update ./deploy/charts/ai-community-platform
```

### 3. Застосуйте оновлення

```bash
helm upgrade --install ai-community-platform \
  ./deploy/charts/ai-community-platform \
  --namespace acp \
  -f values-prod.yaml \
  --wait \
  --timeout 15m
```

### 4. Спостерігайте за завданням міграції

Завдання міграції запускається як хук `pre-upgrade` до старту нових подів застосунку.

```bash
kubectl get jobs -n acp
kubectl logs job/ai-community-platform-migrate-<revision> -n acp
```

Якщо завдання міграції завершилося з помилкою:
- Не продовжуйте перевірку трафіку
- Визначте, чи схема застосована частково
- Вирішіть між виправленням вперед та відкатом залежно від оборотності міграції

### 5. Спостерігайте за статусом розгортання

```bash
kubectl rollout status deploy/ai-community-platform-core -n acp
kubectl rollout status deploy/ai-community-platform-core-scheduler -n acp
```

### 6. Перевірка після оновлення

Мінімальні ворота перевірки:

- [ ] Всі поди у стані Running
- [ ] Завдання міграції завершилося успішно
- [ ] Health endpoint core відповідає: `curl -sf https://platform.example.com/health`
- [ ] Маршрути ingress вирішуються коректно
- [ ] Вхід адміністратора працює
- [ ] Хоча б один критичний агентський потік працює

## Процес відкату

> **Важливо**: Відкат не є автоматично безпечним, якщо невдалий реліз застосував незворотні
> зміни схеми або даних. Завжди оцінюйте поведінку міграції перед відкатом workload-ів.

### 1. Перегляньте історію релізів

```bash
helm history ai-community-platform -n acp
```

Визначте номер останньої відомо-справної ревізії.

### 2. Відкатіть Helm-реліз

```bash
helm rollback ai-community-platform <revision> -n acp --wait --timeout 15m
```

### 3. Перевірте розгортання після відкату

```bash
kubectl get pods -n acp
kubectl rollout status deploy/ai-community-platform-core -n acp
curl -sf https://platform.example.com/health
```

### 4. Відновіть дані, якщо відкат несумісний зі схемою

Якщо невдалий реліз змінив схему або дані незворотним чином:

1. Зупиніть поди застосунку:
   ```bash
   kubectl scale deploy/ai-community-platform-core -n acp --replicas=0
   ```
2. Відновіть уражені бази даних з резервної копії до оновлення
3. Масштабуйте застосунок назад:
   ```bash
   kubectl scale deploy/ai-community-platform-core -n acp --replicas=1
   ```
4. Повторно запустіть перевірку здоров'я

## Пов'язані runbook-и

- [Гайд встановлення](./kubernetes-install.md)
- [Матриця топологій деплою](./deployment-topology.md)
- [Runbook оновлення Docker](./docker-upgrade.md)
