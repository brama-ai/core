# Multilingual Interface (i18n Locale Cookie)

## Overview

The platform supports a multilingual admin interface through cookie-based locale detection. User language preferences are stored in the `locale` cookie and automatically applied to all admin panel pages. The system also forwards the user's locale to agents via the `Accept-Language` HTTP header during A2A calls.

**Supported Languages:**
- `ua` — Ukrainian (default)
- `en` — English

## Features

### Cookie-based Locale

- Cookie `locale` stores user's choice
- Values: `ua` or `en`
- Default value: `ua`
- Cookie validated against whitelist of allowed locales
- Invalid values automatically replaced with `ua`

### Language Switcher

- Dropdown menu in admin panel header
- Instant switching without page reload
- Choice saved in cookie with `SameSite=lax`

### A2A Header Forwarding

- Locale automatically forwarded as `Accept-Language` header
- Agents receive user's language for localized responses
- Header read from Symfony RequestStack

### Translation Infrastructure

- Symfony Translation component with YAML catalogs
- Translation files: `messages.uk.yaml`, `messages.en.yaml`
- Twig filter `|trans` for all UI strings
- Fallback chain: uk → en

## Configuration

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

Automatically detects locale from cookie:

```php
// apps/core/src/EventSubscriber/LocaleSubscriber.php
class LocaleSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->cookies->get('locale', ' ua');// Validate against whitelist
        if (!in_array($locale, ['ua', 'en'], true)) {
            $locale = 'ua';
        }
        $request->setLocale($locale);
    }
}
```

### LocaleController

Endpoint for language switching:

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

## Usage

### Switching Language

User selects language from dropdown in header:

1. POST request to `/admin/locale`
2. Cookie set to `ua` or `en`
3. Page reloads with new language

### In Templates

Use `|trans` filter for localized strings:

```twig
<h1>{{ 'page.dashboard.title'|trans }}</h1>
<button>{{ 'button.save'|trans }}</button>
```

### Adding New Translations

1. Add key to `apps/core/translations/messages.uk.yaml`:
```yaml
page:
  dashboard:
    title: 'Панель управління'
```

2. Add translation to `apps/core/translations/messages.en.yaml`:
```yaml
page:
  dashboard:
    title: 'Dashboard'
```

### For Agents: Reading Accept-Language

Agents should read `Accept-Language` header for response localization:

```php
// In agent
$locale = $request->headers->get('Accept-Language', 'uk');
// Use $locale to choose response language
```

## API

### POST /admin/locale

Switch interface language.

**Request:**
```http
POST /admin/locale
Content-Type: application/x-www-form-urlencoded

locale=en
```

**Response:**
- HTTP 302 Redirect back to referer
- Cookie `locale` set

### Cookie Specification

| Attribute | Value |
|-----------|-------|
| Name | `locale` |
| Values | `ua`, `en` |
| Default | `ua` |
| Path | `/` |
| SameSite | `lax` |
| HttpOnly | `false` (accessible via JS) |

## Development

### LocaleSubscriberTest

Unit tests cover:
- Cookie reading
- Fallback to default
- Invalid value handling
- Sub-request ignore

```bash
./vendor/bin/codecept run Unit Locale/LocaleSubscriberTest
```

### LocaleControllerTest

Unit tests cover:
- Switch endpoint
- Locale validation
- Cookie setting
- Redirect behavior

```bash
./vendor/bin/codecept run Unit Controller/Admin/LocaleControllerTest
```

### A2AClient Accept-Language Test

Integration tests verify header forwarding:

```bash
./vendor/bin/codecept run Unit A2AGateway/A2AClientTest --filter "Accept-Language"
```

### Auditor Checklist

New check Q-06 verifies that agents read `Accept-Language` header:

```markdown
[Q-06] A2A endpoint reads Accept-Language header from incoming requests
Level: WARN
```

### Files

| File | Purpose |
|------|---------|
| `apps/core/src/EventSubscriber/LocaleSubscriber.php` | Subscriber for locale detection |
| `apps/core/src/Controller/Admin/LocaleController.php` | Controller for language switching |
| `apps/core/translations/messages.uk.yaml` | Ukrainian translations |
| `apps/core/translations/messages.en.yaml` | English translations |
| `apps/core/config/packages/framework.yaml` | Translator configuration |
| `apps/core/templates/admin/layout.html.twig` | Language switcher UI |