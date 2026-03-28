# Налаштування Cloudflare Turnstile

Cloudflare Turnstile — це приватна альтернатива CAPTCHA, яка захищає форму входу edge auth
від атак методом перебору паролів та підстановки облікових даних.

## Що таке Turnstile?

- Безкоштовний рівень: 1 мільйон перевірок на місяць
- Орієнтований на конфіденційність — без відстеження Google
- Кращий UX, ніж reCAPTCHA (керований/невидимий виклик)
- Простий фронтенд-віджет + серверна перевірка

**Документація:** https://developers.cloudflare.com/turnstile/

## Як це працює

1. **Фронтенд:** Віджет Turnstile відображається у формі входу
2. **Взаємодія користувача:** Cloudflare запускає невидимий або видимий виклик
3. **Відправка форми:** Токен `cf-turnstile-response` включається в тіло POST-запиту
4. **Серверна перевірка:** Платформа надсилає токен до API Cloudflare
5. **Результат:** Вхід виконується лише у разі успішної перевірки

## Кроки налаштування

### 1. Створення сайту Turnstile в Cloudflare

1. Увійдіть до [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Перейдіть до **Turnstile** у лівій бічній панелі
3. Натисніть **Add Site**
4. Заповніть:
   - **Site name:** `Brama Platform - Production` (або будь-яка назва)
   - **Domain:** ваш виробничий домен (наприклад, `platform.example.com`)
   - **Widget mode:** `Managed` (рекомендовано — Cloudflare визначає рівень виклику)
5. Натисніть **Create**
6. Скопіюйте **Site Key** (публічний) та **Secret Key** (приватний)

> Для локальної розробки використовуйте тестові ключі Cloudflare, що завжди проходять (див. нижче).

### 2. Налаштування змінних середовища

**Локальна розробка (`.env.deployment`):**

```bash
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=1x00000000000000000000AA       # Тестовий ключ — завжди проходить
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA  # Тестовий ключ — завжди проходить
```

Встановіть `TURNSTILE_ENABLED=false`, щоб повністю вимкнути віджет під час розробки.

**Виробництво (Kubernetes secret):**

```bash
kubectl create secret generic core-secrets \
  --from-literal=TURNSTILE_SITE_KEY=<ваш-реальний-site-key> \
  --from-literal=TURNSTILE_SECRET_KEY=<ваш-реальний-secret-key> \
  -n brama \
  --dry-run=client -o yaml | kubectl apply -f -
```

Helm-чарт (`values-prod.example.yaml`) вже встановлює `TURNSTILE_ENABLED: "true"` та
посилається на `core-secrets` через `secretRef`.

### 3. Перевірка налаштування

1. Перейдіть на сторінку входу edge auth: `http://localhost/edge/auth/login`
2. Ви повинні побачити віджет Turnstile під полем пароля
3. Перевірте потік:
   - ✅ Віджет пройдено → вхід виконується нормально
   - ❌ Віджет не пройдено → повідомлення про помилку: "Не вдалося пройти перевірку CAPTCHA. Спробуйте ще раз."

## Тестові ключі (розробка)

Cloudflare надає спеціальні ключі для тестування, які завжди проходять перевірку:

| Тип ключа    | Значення                                       |
|--------------|------------------------------------------------|
| Site key     | `1x00000000000000000000AA`                     |
| Secret key   | `1x0000000000000000000000000000000AA`          |

Ці ключі попередньо налаштовані у `.env.deployment.example`.

## Усунення несправностей

**Віджет не відображається:**
- Перевірте консоль браузера на наявність помилок JavaScript
- Переконайтеся, що `TURNSTILE_ENABLED=true` у вашому середовищі
- Підтвердіть, що `TURNSTILE_SITE_KEY` встановлено та не порожній

**Перевірка завжди не проходить:**
- Переконайтеся, що `TURNSTILE_SECRET_KEY` відповідає site key у панелі Cloudflare
- Підтвердіть, що сервер платформи може досягти `challenges.cloudflare.com` (вихідний HTTPS)
- Перевірте Cloudflare Dashboard → Turnstile → Analytics для журналів невдалих перевірок

**Обмеження швидкості:**
- Безкоштовний рівень: 1 мільйон перевірок на місяць
- Оновіть план Cloudflare, якщо досягнете ліміту

## Примітки щодо безпеки

- **Секретний ключ** ніколи не передається браузеру — перевірка виконується лише на сервері
- Невдала перевірка повертає HTTP 401 Unauthorized
- IP-адреса клієнта передається до Cloudflare для покращеного виявлення шахрайства
- Якщо API Cloudflare недоступний, перевірка завершується невдачею (вхід заблоковано)
- Встановлюйте `TURNSTILE_ENABLED=false` лише у довірених внутрішніх середовищах

## Пов'язані файли

| Файл | Призначення |
|------|-------------|
| `brama-core/src/src/Controller/EdgeAuth/LoginController.php` | Логіка серверної перевірки |
| `brama-core/src/templates/edge_auth/login.html.twig` | Інтеграція фронтенд-віджета |
| `brama-core/src/config/services.yaml` | Параметри сервісу для Turnstile |
| `.env.deployment.example` | Довідник змінних середовища |
| `brama-core/deploy/charts/brama/values-prod.example.yaml` | Конфігурація Kubernetes/Helm |
