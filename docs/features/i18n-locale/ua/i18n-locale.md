# Багатомовний інтерфейс (i18n Locale Cookie)

## Огляд

Платформа підтримує багатомовний інтерфейс адміністратора через cookie-based визначення мови. Мовні преференції користувача зберігаються в cookie `locale` та автоматично застосовуються до всіх сторінок адмін-панелі. Система також передає мову користувача агентам через HTTP header `Accept-Language` під час A2A викликів.

**Підтримувані мови:**
- `ua` — Українська (за замовчуванням)
- `en` — English

## Функціонал

### Cookie-based Locale

- Cookie `locale` зберігає вибір користувача
- Значення: `ua` або `en`
- Значення за замовчуванням: `ua`
- Cookie валідується проти whitelist дозволених мов
- Невалідні значення автоматично замінюються на `ua`

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
// apps/core/src/EventSubscriber/LocaleSubscriber.php
class LocaleSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->cookies->get('locale', 'ua');// Validate against whitelist
        if (!in_array($locale, ['ua', 'en'], true)) {
            $locale = 'ua';
        }
        $request->setLocale($locale);
    }
}
```

### LocaleController

Endpoint для перемикання мови:

```php
// apps/core/src/Controller/Admin/LocaleController.php
#[Route('/admin/locale', name: 'admin_locale_switch', methods: ['POST'])]
public function switchLocale(Request $request): Response
{
    $locale = $request->request->get('locale', 'ua');
    // Validate and set cookie
    $response = new RedirectResponse($request->headers->get('referer'));
    $response->headers->setCookie(Cookie::create('locale', $locale)
        ->withSameSite('lax')
        ->withPath('/'));
    return $response;
}
```

### A2AClient Header Forwarding

```php
// apps/core/src/A2AGateway/A2AClient.php
$headers['Accept-Language'] = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'uk';
```

## Використання

### Перемикання мови

Користувач обирає мову з випадаючого списку в header:

1. POST запит до `/admin/locale`
2. Cookie встановлюється на `ua` або `en`
3. Сторінка перезавантажується з новою мовою

### В Templates

Використовуйте `|trans` filter для локалізованих строк:

```twig
<h1>{{ 'page.dashboard.title'|trans }}</h1>
<button>{{ 'button.save'|trans }}</button>
```

### Додавання нових перекладів

1. Додайте ключ до `apps/core/translations/messages.uk.yaml`:
```yaml
page:
  dashboard:
    title: 'Панель управління'
```

2. Додайте переклад до `apps/core/translations/messages.en.yaml`:
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

### POST /admin/locale

Перемикання мови інтерфейсу.

**Request:**
```http
POST /admin/locale
Content-Type: application/x-www-form-urlencoded

locale=en
```

**Response:**
- HTTP 302 Redirect назад доreferer
- Cookie `locale` встановлено

### Cookie Specification

| Attribute | Value |
|-----------|-------|
| Name | `locale` |
| Values | `ua`, `en` |
| Default | `ua` |
| Path| `/` |
| SameSite | `lax` |
| HttpOnly | `false` (доступний через JS) |

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

### LocaleControllerTest

Unit тести покривають:
- Endpoint перемикання
- Валідація locale
- Встановлення cookie
- Redirect behavior

```bash
./vendor/bin/codecept run Unit Controller/Admin/LocaleControllerTest
```

### A2AClient Accept-Language Test

Integration тести перевіряють forwarding header:

```bash
./vendor/bin/codecept run Unit A2AGateway/A2AClientTest --filter "Accept-Language"
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
| `apps/core/src/EventSubscriber/LocaleSubscriber.php` | Subscriberдля визначення locale |
| `apps/core/src/Controller/Admin/LocaleController.php` | Controller для перемикання мови |
| `apps/core/translations/messages.uk.yaml` | Українські переклади |
| `apps/core/translations/messages.en.yaml` | Англійські переклади |
| `apps/core/config/packages/framework.yaml` | Конфігурація translator |
| `apps/core/templates/admin/layout.html.twig` | Language switcher UI |