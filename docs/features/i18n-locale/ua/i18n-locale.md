# Багатомовний інтерфейс (i18n Locale Cookie)

## Огляд

Платформа підтримує багатомовний інтерфейс адміністратора через cookie-based визначення мови. Мовні преференції користувача зберігаються в cookie `locale` та автоматично застосовуються до всіх сторінок адмін-панелі. Система також передає мову користувача агентам через HTTP header `Accept-Language` під час A2A викликів.

**Підтримувані мови:**
- `uk` — Українська (за замовчуванням)
- `en` — English

## Функціонал

### Cookie-based Locale

- Cookie `locale` зберігає вибір користувача
- Значення: `uk` або `en`
- Значення за замовчуванням: `uk`
- Cookie валідується проти whitelist дозволених мов
- Невалідні значення автоматично замінюються на `uk`

### Language Switcher

- Випадаючий список у header адмін-панелі
- Миттєве перемикання без перезавантаження сторінки
- Збереження вибору в cookie з `SameSite=lax`

### A2A Header Forwarding

- Locale автоматично передається як `Accept-Language` header
- Агенти отримують мову користувача для локалізованих відповідей
- Headerчитається з Symfony RequestStack

### Translation Infrastructure

- Symfony Translation component з YAML catalogs
- Файли перекладів: `messages.uk.yaml`, `messages.en.yaml`
- Twig filter `|trans` для всіх UI строк
- Fallback chain: uk → en

## Конфігурація

### framework.yaml

```yaml
framework:
    translator:
        default_locale: 'uk'
        fallbacks: ['uk', 'en']
        paths:
            - '%kernel.project_dir%/translations'
```

### LocaleSubscriber

Автоматично визначає locale з cookie:

```php
// brama-core/src/src/Locale/LocaleSubscriber.php
class LocaleSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->cookies->get('locale', 'uk');
        if (!in_array($locale, ['uk', 'en'], true)) {
            $locale = 'uk';
        }
        $request->setLocale($locale);
    }
}
```

### LocaleController

Endpoint для перемикання мови:

```php
// brama-core/src/src/Controller/Admin/LocaleController.php
#[Route('/admin/locale/switch', name: 'admin_locale_switch', methods: ['POST'])]
public function switch(Request $request): Response
{
    $locale = $request->request->getString('locale', 'uk');
    // Validate and set cookie
    $response = new RedirectResponse($request->headers->get('referer', '/admin'));
    $response->headers->setCookie(Cookie::create('locale')
        ->withValue($locale)
        ->withSameSite('lax')
        ->withPath('/'));
    return $response;
}
```

### A2AClient Header Forwarding

```php
// brama-core/src/src/A2AGateway/A2AClient.php
$locale = $currentRequest?->getLocale() ?? LocaleSubscriber::DEFAULT_LOCALE;
$headers['Accept-Language'] = $locale;
```

## Використання

### Перемикання мови

Користувач обирає мову з випадаючого списку в header:

1. POST запит до `/admin/locale/switch`
2. Cookie встановлюється на `uk` або `en`
3. Сторінка перезавантажується з новою мовою

### В Templates

Використовуйте `|trans` filter для локалізованих строк:

```twig
<h1>{{ 'page.dashboard.title'|trans }}</h1>
<button>{{ 'button.save'|trans }}</button>
```

### Додавання нових перекладів

1. Додайте ключ до `brama-core/src/translations/messages.uk.yaml`:
```yaml
page:
  dashboard:
    title: 'Панель управління'
```

2. Додайте переклад до `brama-core/src/translations/messages.en.yaml`:
```yaml
page:
  dashboard:
    title: 'Dashboard'
```

### Агентам: читання Accept-Language

Агенти повинні читати `Accept-Language` header для локалізації відповідей:

```php
// У агенті
$locale = $request->headers->get('Accept-Language', 'uk');
// Використовуйте $locale для вибору мови відповіді
```

## API

### POST /admin/locale/switch

Перемикання мови інтерфейсу. Endpoint публічний (автентифікація не потрібна).

**Request:**
```http
POST /admin/locale/switch
Content-Type: application/x-www-form-urlencoded

locale=en
```

**Response:**
- HTTP 302 Redirect назад до referer
- Cookie `locale` встановлено

### Cookie Specification

| Attribute | Value |
|-----------|-------|
| Name | `locale` |
| Values | `uk`, `en` |
| Default | `uk` |
| Path | `/` |
| SameSite | `lax` |
| HttpOnly | `false` (доступний через JS) |
| Expires | 1 рік |

## Розробка

### LocaleSubscriberTest

Unit тести покривають:
- Читання cookie
- Fallback до default
- Обробка невалідних значень
- Ігнорування sub-requests

```bash
./vendor/bin/codecept run Unit Locale/LocaleSubscriberTest
```

### LocaleControllerTest (Unit)

Unit тести покривають:
- Endpoint перемикання
- Валідація locale
- Встановлення cookie
- Redirect behavior

```bash
./vendor/bin/codecept run Unit Controller/Admin/LocaleControllerTest
```

### LocaleControllerCest (Functional)

Functional тести покривають:
- Cookie встановлюється правильно для валідних locale
- Невалідний locale повертається до default
- Redirect до referer працює

```bash
./vendor/bin/codecept run Functional Admin/LocaleControllerCest
```

### A2AClient Accept-Language Test

Unit тести перевіряють forwarding header:

```bash
./vendor/bin/codecept run Unit A2AGateway/A2AClientTest
```

### Auditor Checklist

Новий check Q-06 перевіряє, що агенти читають `Accept-Language` header:

```markdown
[Q-06] A2A endpoint reads Accept-Language header from incoming requests
Level: WARN
```

### Файли

| Файл | Призначення |
|------|-------------|
| `brama-core/src/src/Locale/LocaleSubscriber.php` | Subscriber для визначення locale |
| `brama-core/src/src/Controller/Admin/LocaleController.php` | Controller для перемикання мови |
| `brama-core/src/translations/messages.uk.yaml` | Українські переклади |
| `brama-core/src/translations/messages.en.yaml` | Англійські переклади |
| `brama-core/src/config/packages/framework.yaml` | Конфігурація translator |
| `brama-core/src/templates/admin/layout.html.twig` | Language switcher UI |
| `brama-core/src/config/packages/security.yaml` | Locale switch endpoint є PUBLIC_ACCESS |