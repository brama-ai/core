# Продакшн-домени для зовнішніх сервісів на K3S

## Огляд

Цей гайд описує налаштування маршрутизації продакшн-доменів та edge-автентифікації для зовнішніх
адмін-сервісів (Langfuse, LiteLLM, OpenClaw) при деплої платформи Brama на кластер K3S.

У Docker Compose (локальна розробка) ці сервіси доступні через Traefik з middleware `edge-auth@docker`.
На Kubernetes еквівалентне налаштування потребує:

1. Traefik `Middleware` CRD для ForwardAuth
2. Ingress-правил для кожного зовнішнього сервісу
3. Змінних середовища core-сервісу з правильними публічними URL

---

## Стратегія доменів

### Варіант A — nip.io (швидкий staging, без DNS)

Використовуйте `nip.io` для отримання wildcard DNS для будь-якої IP-адреси без реєстрації домену:

| Сервіс | URL |
|--------|-----|
| Core-платформа | `http://46.62.135.86.nip.io` |
| Langfuse | `http://langfuse.46.62.135.86.nip.io` |
| LiteLLM | `http://litellm.46.62.135.86.nip.io` |
| OpenClaw | `http://openclaw.46.62.135.86.nip.io` |

**Переваги**: Не потрібне налаштування DNS, працює одразу.  
**Недоліки**: Не підходить для TLS (cert-manager не може видавати сертифікати для nip.io), не для продакшну.

### Варіант B — Реальний домен (production-ready)

Зареєструйте домен і створіть DNS A-записи, що вказують на IP сервера:

| Запис | Значення |
|-------|---------|
| `platform.example.com` | `46.62.135.86` |
| `langfuse.example.com` | `46.62.135.86` |
| `litellm.example.com` | `46.62.135.86` |
| `openclaw.example.com` | `46.62.135.86` |

**Переваги**: Підтримка TLS, production-grade, стабільні URL.  
**Недоліки**: Потрібна реєстрація домену та час на поширення DNS.

---

## Стратегія деплою зовнішніх сервісів

Зовнішні сервіси (Langfuse, LiteLLM, OpenClaw) мають складні інфраструктурні залежності:

| Сервіс | Залежності |
|--------|-----------|
| Langfuse | PostgreSQL, Redis, ClickHouse, MinIO |
| LiteLLM | PostgreSQL |
| OpenClaw | OpenSearch |

**Рекомендований підхід для K3S**: Запускати зовнішні сервіси як Docker Compose на тому ж сервері,
виставляти їх через Traefik ingress до K3S кластера.

Це дозволяє уникнути складності пакування кожного сервісу як Kubernetes sub-chart, зберігаючи
уніфіковану маршрутизацію доменів та edge-автентифікацію.

---

## Крок 1: Деплой Edge Auth Middleware

Створіть Traefik `Middleware` CRD у namespace `brama`. Це Kubernetes-еквівалент middleware
`edge-auth@docker` з Docker Compose.

Створіть `edge-auth-middleware.yaml`:

```yaml
apiVersion: traefik.containo.us/v1alpha1
kind: Middleware
metadata:
  name: edge-auth
  namespace: brama
spec:
  forwardAuth:
    address: http://brama-core/edge/auth/verify
    trustForwardHeader: true
    authResponseHeaders:
      - X-Forwarded-User
```

Застосуйте:

```bash
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
kubectl apply -f edge-auth-middleware.yaml
kubectl get middleware -n brama
```

> **Примітка**: Адреса middleware `http://brama-core/edge/auth/verify` передбачає, що core-сервіс
> називається `brama-core` у namespace `brama`. Скоригуйте, якщо ваш Helm release має іншу назву.

---

## Крок 2: Налаштування Ingress для зовнішніх сервісів

Оновіть Helm values для додавання ingress-маршрутів для зовнішніх сервісів. Шаблон ingress
(`templates/ingress.yaml`) маршрутизує трафік до in-cluster сервісів. Для Docker Compose сервісів,
що працюють на тому ж хості, потрібні `ExternalName` сервіси або пряма IP-маршрутизація.

### Варіант A: ExternalName сервіси (рекомендовано)

Створіть Kubernetes `Service` об'єкти типу `ExternalName`, що вказують на Docker Compose сервіси хоста:

```yaml
# external-services.yaml
apiVersion: v1
kind: Service
metadata:
  name: langfuse-external
  namespace: brama
spec:
  type: ExternalName
  externalName: host.k3s.internal  # IP шлюзу хоста K3S
  ports:
    - port: 3000
      targetPort: 3000
---
apiVersion: v1
kind: Service
metadata:
  name: litellm-external
  namespace: brama
spec:
  type: ExternalName
  externalName: host.k3s.internal
  ports:
    - port: 4000
      targetPort: 4000
---
apiVersion: v1
kind: Service
metadata:
  name: openclaw-external
  namespace: brama
spec:
  type: ExternalName
  externalName: host.k3s.internal
  ports:
    - port: 3001
      targetPort: 3001
```

> **IP хоста K3S**: У K3S хост зазвичай доступний за адресою `172.17.0.1` або через
> `host.k3s.internal`. Перевірте командою:
> ```bash
> kubectl run -it --rm debug --image=busybox --restart=Never -- nslookup host.k3s.internal
> ```

---

## Крок 3: Створення файлу продакшн-values

Створіть `values-k3s-production.yaml` (НЕ комітьте — містить environment-специфічну конфігурацію):

```yaml
# K3S Production Values
# Замініть 46.62.135.86 на реальний IP сервера або домен

global:
  imagePullPolicy: IfNotPresent

core:
  enabled: true
  image:
    repository: brama/brama-core
    tag: "dev"
    pullPolicy: IfNotPresent
  replicaCount: 1
  env:
    APP_ENV: prod
    LANGFUSE_ENABLED: "true"
    EDGE_AUTH_COOKIE_NAME: ACP_EDGE_TOKEN
    EDGE_AUTH_TOKEN_TTL: "43200"
    # ⚠️ Встановіть реальний домен:
    EDGE_AUTH_LOGIN_BASE_URL: "http://46.62.135.86.nip.io"
    EDGE_AUTH_COOKIE_DOMAIN: ".46.62.135.86.nip.io"
    ADMIN_LANGFUSE_URL: "http://langfuse.46.62.135.86.nip.io/"
    ADMIN_LITELLM_URL: "http://litellm.46.62.135.86.nip.io/"
    ADMIN_OPENCLAW_URL: "http://openclaw.46.62.135.86.nip.io/"
  secretRef: brama-core-secrets

ingress:
  enabled: true
  className: traefik
  annotations:
    traefik.ingress.kubernetes.io/router.middlewares: brama-edge-auth@kubernetescrd
  hosts:
    core: 46.62.135.86.nip.io
    langfuse: langfuse.46.62.135.86.nip.io
    litellm: litellm.46.62.135.86.nip.io
    openclaw: openclaw.46.62.135.86.nip.io
  tls:
    enabled: false
```

> **Cookie domain**: Використовуйте `.46.62.135.86.nip.io` (з крапкою на початку) для спільного
> використання auth cookie між усіма субдоменами. Для реальних доменів використовуйте `.example.com`.

---

## Крок 4: Деплой з продакшн-values

```bash
# На сервері
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml

# Передача чарту (з dev-машини)
tar czf /tmp/brama-chart.tar.gz -C brama-core/deploy/charts brama
scp -i ~/.ssh/ai_platform -F /dev/null -o IdentitiesOnly=yes \
    /tmp/brama-chart.tar.gz root@46.62.135.86:/tmp/

# На сервері — розпакування та деплой
ssh -i ~/.ssh/ai_platform -F /dev/null root@46.62.135.86 << 'EOF'
  export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
  mkdir -p /tmp/brama-deploy
  tar xzf /tmp/brama-chart.tar.gz -C /tmp/brama-deploy

  helm upgrade --install brama /tmp/brama-deploy/brama \
    --namespace brama \
    -f /tmp/brama-deploy/brama/values-k3s-production.yaml \
    --wait --timeout 5m
EOF
```

---

## Крок 5: Перевірка Edge-автентифікації

Після деплою перевірте правильність роботи edge auth:

```bash
# Має перенаправляти на логін (HTTP 302 або 401)
curl -v http://langfuse.46.62.135.86.nip.io/
# Очікується: redirect на http://46.62.135.86.nip.io/edge/auth/login

# Має перенаправляти на логін
curl -v http://litellm.46.62.135.86.nip.io/

# Має перенаправляти на логін
curl -v http://openclaw.46.62.135.86.nip.io/

# Core-платформа має бути доступна (сторінка логіну)
curl -sf http://46.62.135.86.nip.io/health
# Очікується: {"status":"ok",...}
```

### Перевірка bypass для вебхуків (OpenClaw)

Endpoint вебхуку Telegram в OpenClaw має обходити edge auth:

```bash
# НЕ має перенаправляти — має повертати 200 або 404 (не 302)
curl -v http://openclaw.46.62.135.86.nip.io/api/channels/
```

### Перевірка bypass для edge auth login

Сам endpoint логіну має обходити edge auth (інакше логін неможливий):

```bash
# Має повертати HTML сторінки логіну (не redirect)
curl -v http://46.62.135.86.nip.io/edge/auth/login
```

---

## Довідка з Edge-автентифікації

### Як це працює

```
Браузер → Traefik → ForwardAuth middleware
                        ↓
                    POST http://brama-core/edge/auth/verify
                        ↓
                    Перевірка cookie: ACP_EDGE_TOKEN (JWT)
                        ↓
                    Валідний?  → 204 No Content → дозволити запит
                    Невалідний? → 401 → redirect на сторінку логіну
```

### Змінні середовища

| Змінна | Опис | Приклад |
|--------|------|---------|
| `EDGE_AUTH_JWT_SECRET` | Секрет підпису JWT — **змінити в продакшні!** | `openssl rand -hex 32` |
| `EDGE_AUTH_COOKIE_NAME` | Назва cookie | `ACP_EDGE_TOKEN` |
| `EDGE_AUTH_TOKEN_TTL` | Час життя токена в секундах | `43200` (12 годин) |
| `EDGE_AUTH_LOGIN_BASE_URL` | Базовий URL для redirect на логін | `http://46.62.135.86.nip.io` |
| `EDGE_AUTH_COOKIE_DOMAIN` | Cookie domain (з крапкою для субдоменів) | `.46.62.135.86.nip.io` |

### Файли реалізації

| Файл | Призначення |
|------|------------|
| `brama-core/src/src/EdgeAuth/EdgeJwtService.php` | Створення та валідація JWT |
| `brama-core/src/src/Controller/EdgeAuth/VerifyController.php` | Endpoint перевірки автентифікації |
| `brama-core/src/src/Controller/EdgeAuth/LoginController.php` | Форма логіну та створення токена |

---

## Чеклист перевірки

- [ ] Edge auth middleware задеплоєний у K3S (`kubectl get middleware -n brama`)
- [ ] Ingress-маршрути створені для всіх зовнішніх сервісів (`kubectl get ingress -n brama`)
- [ ] Core-сервіс має правильні змінні середовища `ADMIN_*_URL`
- [ ] `curl http://langfuse.46.62.135.86.nip.io/` перенаправляє на логін
- [ ] Після логіну Langfuse UI доступний
- [ ] Після логіну LiteLLM UI доступний
- [ ] Після логіну OpenClaw UI доступний
- [ ] Edge auth cookie правильно встановлюється для кожного субдомену
- [ ] Endpoint вебхуку OpenClaw (`/api/channels/`) обходить edge auth
- [ ] Endpoint логіну edge auth (`/edge/auth/`) обходить edge auth

---

## Вирішення проблем

### Middleware не знайдено

```
Error: middleware "brama-edge-auth@kubernetescrd" not found
```

Traefik Middleware CRD не було застосовано. Перевірте:

```bash
kubectl get middleware -n brama
kubectl describe middleware edge-auth -n brama
```

### Cookie не поширюється між субдоменами

Переконайтеся, що `EDGE_AUTH_COOKIE_DOMAIN` починається з крапки (`.46.62.135.86.nip.io`).
Без крапки на початку cookie є host-only і не надсилається до субдоменів.

> **Примітка**: `.localhost` та IP-based домени можуть мати обмеження браузера на cross-subdomain
> cookies. Використовуйте реальний домен для продакшну.

### Зовнішній сервіс недоступний з K3S

Якщо використовуються `ExternalName` сервіси, перевірте доступність IP хоста з кластера:

```bash
kubectl run -it --rm debug --image=busybox --restart=Never -- \
  wget -O- http://host.k3s.internal:3000/health
```

### Ingress маршрутизує до неправильного сервісу

Перевірте конфігурацію backend ingress:

```bash
kubectl describe ingress brama -n brama
```

---

## Пов'язані гайди

- [Встановлення на Kubernetes](./kubernetes-install.md) — повне налаштування K3S
- [Деплой на продакшн (Docker)](./deployment.md) — шлях через Docker Compose
- [Огляд деплою](./deployment-overview.md) — порівняння топологій
