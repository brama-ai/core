# Перевірка локального k3s-середовища

## Огляд

Цей runbook описує відтворюваний 5-етапний процес перевірки, який підтверджує, що локальне
k3s-середовище працює коректно з поточними Helm-чартами та конфігурацією devcontainer.
Виконуйте кожен етап по порядку — кожен наступний залежить від успішного проходження попереднього.

**Цільове середовище**: Rancher Desktop k3s (локальна розробка)  
**Helm-чарт**: `brama-core/deploy/charts/brama/`  
**Файл values**: `values-k3s-dev.yaml`  
**Makefile-цілі**: `k8s-setup`, `k8s-build`, `k8s-load`, `k8s-secrets`, `k8s-deploy`, `k8s-status`

Англійська версія: [`docs/guides/deployment/en/local-k3s-validation.md`](../en/local-k3s-validation.md)

---

## Передумови

Перед початком перевірки переконайтеся, що наступне доступне:

- Rancher Desktop ≥ 1.12 з увімкненим k3s (не режим dockerd)
- `kubectl` налаштований і вказує на контекст `rancher-desktop`
- `helm` 3.12+ встановлений
- `rdctl` доступний у PATH (постачається з Rancher Desktop)
- Devcontainer запущений з увімкненою функцією Docker-outside-of-Docker
- Вихідний код знаходиться в кореневій директорії workspace

Перевірте контекст перед будь-яким кроком:

```bash
kubectl config current-context
# Очікується: rancher-desktop
```

---

## Етап 1: Готовність кластера

**Мета**: Підтвердити, що k3s-кластер працює перед будь-яким деплоєм.

### 1.1 — Підтвердити доступність вузла кластера

```bash
kubectl get nodes
```

**Очікуваний результат** (принаймні один вузол у стані `Ready`):

```
NAME                   STATUS   ROLES                  AGE   VERSION
rancher-desktop        Ready    control-plane,master   5d    v1.31.x+k3s1
```

**Якщо вузол у стані `NotReady`**: Перезапустіть Rancher Desktop і зачекайте 60 секунд, потім повторіть.

### 1.2 — Підтвердити існування цільового namespace

```bash
kubectl get ns brama
```

Якщо namespace не існує, створіть його:

```bash
make k8s-ns
# або: kubectl create namespace brama
```

**Очікуваний результат**:

```
NAME    STATUS   AGE
brama   Active   1m
```

### 1.3 — Підтвердити відсутність критичних збоїв системних подів

```bash
kubectl get pods -n kube-system
```

**Очікується**: Всі поди у стані `Running` або `Completed`. Жоден под не повинен бути у стані `CrashLoopBackOff` або `Error`.

**Команди для діагностики при збої пода**:

```bash
kubectl describe pod <pod-name> -n kube-system
kubectl logs <pod-name> -n kube-system
```

**Критерії прийняття для Етапу 1**:
- [ ] `kubectl get nodes` показує всі вузли у стані `Ready`
- [ ] `kubectl get pods -A | grep kube-system` не показує подів у стані `CrashLoopBackOff` або `Error`
- [ ] Namespace `brama` існує і має статус `Active`

---

## Етап 2: Інфраструктурний шар

**Мета**: Підтвердити, що всі in-cluster інфраструктурні сервіси (PostgreSQL, Redis, RabbitMQ) справні.

> **Примітка щодо OpenSearch**: OpenSearch **вимкнений** у `values-k3s-dev.yaml` (`opensearch.enabled: false`).
> Локальний k3s-профіль використовує натомість екземпляр OpenSearch з Docker Compose. Перевірка
> in-cluster OpenSearch для цього профілю не потрібна.

### 2.1 — Перевірка готовності PostgreSQL

```bash
# Перевірити статус пода
kubectl get pods -n brama -l app.kubernetes.io/name=postgresql

# Зайти в под і виконати запит
kubectl exec -n brama deploy/brama-postgresql -- \
  psql -U app -d ai_community_platform -c "SELECT 1;"
```

**Очікується**: Под у стані `Running 1/1`. Запит повертає:

```
 ?column?
----------
        1
(1 row)
```

**Команди для діагностики при збої**:

```bash
kubectl logs -n brama -l app.kubernetes.io/name=postgresql
kubectl describe pod -n brama -l app.kubernetes.io/name=postgresql
```

### 2.2 — Перевірка готовності Redis

```bash
# Перевірити статус пода
kubectl get pods -n brama -l app.kubernetes.io/name=redis

# Зайти в под і виконати ping
kubectl exec -n brama deploy/brama-redis-master -- redis-cli ping
```

**Очікується**: Под у стані `Running 1/1`. Команда повертає `PONG`.

**Команди для діагностики при збої**:

```bash
kubectl logs -n brama -l app.kubernetes.io/name=redis
kubectl describe pod -n brama -l app.kubernetes.io/name=redis
```

### 2.3 — Перевірка готовності RabbitMQ

```bash
# Перевірити статус пода
kubectl get pods -n brama -l app.kubernetes.io/name=rabbitmq

# Зайти в под і перевірити статус
kubectl exec -n brama deploy/brama-rabbitmq -- rabbitmqctl status
```

**Очікується**: Под у стані `Running 1/1`. Вивід містить рядок з версією RabbitMQ без помилок.

**Команди для діагностики при збої**:

```bash
kubectl logs -n brama -l app.kubernetes.io/name=rabbitmq
kubectl describe pod -n brama -l app.kubernetes.io/name=rabbitmq
```

### 2.4 — OpenSearch (пропускається — вимкнений у k3s-dev профілі)

OpenSearch навмисно вимкнений у `values-k3s-dev.yaml`:

```yaml
opensearch:
  enabled: false
```

**Обґрунтування**: Локальний k3s-профіль має обмежені ресурси. OpenSearch потребує значного
обсягу пам'яті і не є необхідним для перевірки основної платформи. Docker Compose стек надає
OpenSearch для робочих процесів розробки, які його потребують.

**Критерії прийняття для Етапу 2**:
- [ ] Под PostgreSQL у стані `Running 1/1` і `SELECT 1` виконується успішно
- [ ] Под Redis у стані `Running 1/1` і `redis-cli ping` повертає `PONG`
- [ ] Под RabbitMQ у стані `Running 1/1` і `rabbitmqctl status` показує версію без помилок
- [ ] Пропуск OpenSearch задокументований (цей пункт — `opensearch.enabled: false` у файлі values)

---

## Етап 3: Основний рантайм

**Мета**: Підтвердити, що под основного застосунку запущений, справний і доступний операторам.

### 3.1 — Перевірка готовності пода core

```bash
kubectl get pods -n brama -l app.kubernetes.io/component=core
```

**Очікується**:

```
NAME                          READY   STATUS    RESTARTS   AGE
brama-core-7d9f8b6c4-xk2pq   1/1     Running   0          5m
```

Под повинен показувати `READY 1/1` і `STATUS Running`.

**Команди для діагностики при збої**:

```bash
kubectl logs deploy/brama-core -n brama
kubectl logs deploy/brama-core -n brama --previous
kubectl describe pod -n brama -l app.kubernetes.io/component=core
```

### 3.2 — Перевірка health-ендпоінту core через exec

```bash
CORE_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=core -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$CORE_POD" -- curl -sf http://localhost/health
```

**Очікувана відповідь**:

```json
{"status":"ok","timestamp":"2026-03-28T12:00:00+00:00"}
```

### 3.3 — Перевірка доступу оператора

#### Варіант А: Port-forward (завжди працює, не потребує `/etc/hosts`)

```bash
kubectl port-forward -n brama svc/brama-core 8080:80 &
curl -sf http://localhost:8080/health
```

**Очікується**: Та сама відповідь health, що й вище.

Зупиніть port-forward після завершення:

```bash
kill %1
```

#### Варіант Б: Traefik ingress (потребує запису в `/etc/hosts`)

Додайте до `/etc/hosts`:

```
127.0.0.1 core.localhost
```

Потім:

```bash
curl -sf http://core.localhost/health
```

**Очікується**: Та сама відповідь health.

**Перевірте конфігурацію ingress**:

```bash
kubectl get ingress -n brama
# Очікується: ingress brama з хостом core.localhost
```

**Критерії прийняття для Етапу 3**:
- [ ] Под core показує `READY 1/1` і `STATUS Running`
- [ ] `curl http://localhost/health` через exec повертає `{"status":"ok","timestamp":"..."}`
- [ ] Port-forward `svc/brama-core 8080:80` обслуговує health-ендпоінт на `localhost:8080`
- [ ] Traefik ingress маршрутизує `core.localhost` до сервісу core (з записом у `/etc/hosts`)

---

## Етап 4: Рантайм референсного агента

**Мета**: Підтвердити, що hello-agent запущений, справний і виявляється основною платформою.

### 4.1 — Перевірка готовності пода hello-agent

```bash
kubectl get pods -n brama -l app.kubernetes.io/component=agent-hello
```

**Очікується**:

```
NAME                                READY   STATUS    RESTARTS   AGE
brama-agent-hello-5f8d9c7b4-m3nqr   1/1     Running   0          5m
```

Под повинен показувати `READY 1/1` і `STATUS Running`.

**Команди для діагностики при збої**:

```bash
kubectl logs deploy/brama-agent-hello -n brama
kubectl logs deploy/brama-agent-hello -n brama --previous
kubectl describe pod -n brama -l app.kubernetes.io/component=agent-hello
```

### 4.2 — Перевірка health-ендпоінту hello-agent

```bash
HELLO_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=agent-hello -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$HELLO_POD" -- curl -sf http://localhost/health
```

**Очікувана відповідь**:

```json
{"status":"ok","service":"hello-agent"}
```

### 4.3 — Перевірка зв'язку core → agent

#### Перевірка Kubernetes discovery-міток

```bash
kubectl get svc -n brama -l ai.platform.agent=true
```

**Очікується**: `brama-agent-hello` присутній у списку з міткою `ai.platform.agent=true`.

```bash
kubectl get svc brama-agent-hello -n brama --show-labels
# Очікувані мітки: ai.platform.agent=true, ai.platform.agent-name=hello-agent
```

#### Перевірка DNS-зв'язку кластера з пода core

```bash
CORE_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=core -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$CORE_POD" -- \
  curl -sf http://brama-agent-hello.brama.svc.cluster.local/health
```

**Очікується**: Відповідь health hello-agent повертається зсередини пода core.

**Критерії прийняття для Етапу 4**:
- [ ] Под hello-agent показує `READY 1/1` і `STATUS Running`
- [ ] Health hello-agent повертає `{"status":"ok","service":"hello-agent"}` через exec
- [ ] `kubectl get svc -n brama -l ai.platform.agent=true` показує `brama-agent-hello`
- [ ] Под core може звернутися до `http://brama-agent-hello.brama.svc.cluster.local/health`

---

## Етап 5: Верифікований runbook

**Мета**: Підтвердити, що весь процес перевірки відтворюваний і задокументований.

### 5.1 — Точний порядок кроків для Rancher Desktop

Верифікована послідовність деплою для чистого середовища Rancher Desktop:

1. **Запустіть Rancher Desktop** з увімкненим k3s (не режим dockerd)
2. **Перевірте контекст**: `kubectl config current-context` → `rancher-desktop`
3. **Бутстрап**: `make k8s-setup` (виконує build → load → secrets → deploy по порядку)
4. **Зачекайте на поди**: `make k8s-status` — зачекайте, поки всі поди перейдуть у стан `Running`
5. **Перевірте**: Виконайте Етапи 1–4 цього runbook
6. **Доступ**: `make k8s-port-forward svc=core port=8080:80`, потім `curl http://localhost:8080/health`

### 5.2 — Відомі проблеми та способи їх вирішення

Дивіться розділ [Відомі проблеми](#відомі-проблеми) нижче.

### 5.3 — Мінімальна послідовність повторної перевірки (6 кроків)

Виконайте ці шість команд, щоб підтвердити, що локальне k3s-середовище справне після будь-яких змін:

```bash
# Крок 1: Вузол кластера готовий
kubectl get nodes | grep -q Ready && echo "✓ Вузол готовий" || echo "✗ Вузол не готовий"

# Крок 2: Namespace існує
kubectl get ns brama -o name 2>/dev/null && echo "✓ Namespace існує" || echo "✗ Namespace відсутній"

# Крок 3: Под core запущений
kubectl get pods -n brama -l app.kubernetes.io/component=core --no-headers | grep -q "1/1.*Running" && echo "✓ Core запущений" || echo "✗ Core не запущений"

# Крок 4: Core health відповідає
CORE_POD=$(kubectl get pod -n brama -l app.kubernetes.io/component=core -o jsonpath='{.items[0].metadata.name}')
kubectl exec -n brama "$CORE_POD" -- curl -sf http://localhost/health | grep -q '"status":"ok"' && echo "✓ Core справний" || echo "✗ Core несправний"

# Крок 5: Hello-agent запущений
kubectl get pods -n brama -l app.kubernetes.io/component=agent-hello --no-headers | grep -q "1/1.*Running" && echo "✓ Hello-agent запущений" || echo "✗ Hello-agent не запущений"

# Крок 6: Агент виявляється
kubectl get svc -n brama -l ai.platform.agent=true --no-headers | grep -q "brama-agent-hello" && echo "✓ Агент виявляється" || echo "✗ Агент не виявляється"
```

Успішне проходження всіх шести кроків підтверджує, що локальне k3s-середовище верифіковане.

---

## Відомі проблеми

| Симптом | Причина | Вирішення |
|---------|---------|-----------|
| `connection refused` при командах `kubectl` | Неправильний контекст kubectl або кластер не запущений | Виконайте `kubectl config use-context rancher-desktop`; перезапустіть Rancher Desktop |
| Под застряг у стані `Pending` | Недостатньо ресурсів кластера або відсутній PVC | Перевірте `kubectl describe pod <name> -n brama`; збільшіть виділення пам'яті в Rancher Desktop |
| `ImagePullBackOff` | Образ не завантажений у containerd k3s | Виконайте `make k8s-load` для імпорту образів через `rdctl shell sudo k3s ctr images import -` |
| `CrashLoopBackOff` на поді core | Відсутній секрет, неправильний `DATABASE_URL` або невдала міграція | Перевірте `kubectl logs deploy/brama-core -n brama --previous`; переконайтеся, що `make k8s-secrets` виконувався |
| `exec format error` | Образ зібраний для неправильної архітектури CPU (наприклад, ARM Mac → x86 k3s) | Збирайте образи на тій самій архітектурі, що й вузол k3s; використовуйте `docker buildx build --platform linux/amd64` для крос-компіляції |
| `core.localhost` не резолвиться | Відсутній запис у `/etc/hosts` | Додайте `127.0.0.1 core.localhost` до `/etc/hosts`; використовуйте port-forward як запасний варіант |
| Неправильний контекст kubectl | `kubectl` вказує на інший кластер | Виконайте `kubectl config use-context rancher-desktop` |
| `make k8s-load` завершується з помилкою `rdctl: command not found` | Rancher Desktop не встановлений або не в PATH | Встановіть Rancher Desktop; переконайтеся, що `rdctl` є в PATH |
| Завдання міграції не завершилося | Перший `helm install` завершився по таймауту до виконання хука | Виконайте `kubectl exec -n brama deploy/brama-core -- php bin/console doctrine:migrations:migrate --no-interaction` |
| StorageClass `local-path` відсутній | Провізіонер Rancher Desktop не запущений | Перевірте `kubectl get storageclass`; перезапустіть Rancher Desktop |

---

## Довідник деплою

### Makefile-цілі

| Ціль | Призначення |
|------|-------------|
| `make k8s-ctx` | Показати поточний контекст кластера |
| `make k8s-ns` | Створити namespace `brama` |
| `make k8s-build` | Зібрати локальні Docker-образи |
| `make k8s-load` | Імпортувати образи в containerd k3s через `rdctl` |
| `make k8s-secrets` | Створити `brama-core-secrets` у namespace `brama` |
| `make k8s-deploy` | Виконати `helm upgrade --install` з `values-k3s-dev.yaml` |
| `make k8s-setup` | Повний бутстрап: build + load + secrets + deploy |
| `make k8s-status` | Показати поди, сервіси, ingress та Helm-реліз |
| `make k8s-logs svc=core` | Переглянути логи сервісу |
| `make k8s-shell svc=core` | Відкрити shell у поді |
| `make k8s-port-forward svc=core port=8080:80` | Прокинути порт сервісу |
| `make k8s-destroy` | Видалити Helm-реліз |

### Компоненти, що деплояться через `values-k3s-dev.yaml`

| Компонент | Тип | Примітки |
|-----------|-----|----------|
| `brama-core` | Deployment | Основна платформа (PHP/Symfony) |
| `brama-core-scheduler` | Deployment | Фоновий планувальник |
| `brama-migrate-N` | Job (хук) | Виконує міграції при install/upgrade |
| `brama-agent-hello` | Deployment | Референсний агент |
| `brama-agent-newsmaker` | Deployment | Агент новин |
| `brama-postgresql` | StatefulSet | Bitnami sub-chart, PVC `local-path` |
| `brama-redis-master` | StatefulSet | Bitnami sub-chart, PVC `local-path` |
| `brama-rabbitmq` | StatefulSet | Bitnami sub-chart, PVC `local-path` |
| Traefik ingress | Ingress | Маршрутизує `core.localhost` до сервісу core |

### Секрети, що створюються через `make k8s-secrets`

Ціль `k8s-secrets` створює секрет `brama-core-secrets` у namespace `brama` з:

- `APP_SECRET` — випадковий hex
- `EDGE_AUTH_JWT_SECRET` — випадковий hex
- `DATABASE_URL` — `postgresql://app:app@brama-postgresql:5432/ai_community_platform?serverVersion=16&charset=utf8`
- `REDIS_URL` — `redis://brama-redis-master:6379`
- `RABBITMQ_URL` — `amqp://app:app@brama-rabbitmq:5672`
- `POSTGRES_PROVISIONER_URL` — те саме, що й `DATABASE_URL`

> Це секрети лише для локальної розробки. Не використовуйте цей підхід для продакшну.

---

## Пов'язана документація

- [Гайд з встановлення на Kubernetes](./kubernetes-install.md) — повний довідник з встановлення, включаючи remote k3s
- [Runbook оновлення Kubernetes](./kubernetes-upgrade.md) — оновлення до нового релізу
- [Топологія деплою](./deployment-topology.md) — підтримувані топології та компроміси
- [Гайд з деплою Docker](./deployment.md) — шлях через Docker Compose
