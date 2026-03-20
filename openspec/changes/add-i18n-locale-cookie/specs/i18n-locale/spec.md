## ADDED Requirements

### Requirement: Cookie-Based Locale Storage
The platform SHALL store the user's language preference in a cookie named `locale` with value `uk` (Ukrainian) or `en` (English). The default locale SHALL be `uk`. The cookie SHALL have path `/`, SameSite `Lax`, and max-age of one year. The cookie SHALL NOT be httpOnly to allow client-side reading.

#### Scenario: Default locale when no cookie present
- **WHEN** a user visits the admin UI without a `locale` cookie
- **THEN** the platform uses `uk` as the default locale

#### Scenario: Valid locale cookie is respected
- **WHEN** a user visits the admin UI with `locale=en` cookie
- **THEN** the platform renders the UI in English

#### Scenario: Invalid locale cookie value is ignored
- **WHEN** a user visits the admin UI with `locale=fr` cookie (unsupported value)
- **THEN** the platform falls back to the default locale `uk`

### Requirement: Locale Subscriber
The platform SHALL implement a `LocaleSubscriber` event listener on `kernel.request` that reads the `locale` cookie, validates it against allowed locales (`uk`, `en`), and calls `$request->setLocale()` with the resolved locale. The subscriber SHALL run at priority 100 (after session initialization but before controller resolution).

#### Scenario: Subscriber sets locale from cookie
- **WHEN** an HTTP request arrives with `locale=en` cookie
- **THEN** the `LocaleSubscriber` sets the request locale to `en`

#### Scenario: Subscriber ignores sub-requests
- **WHEN** a Symfony sub-request is dispatched
- **THEN** the `LocaleSubscriber` does not modify the sub-request locale

### Requirement: Translation Integration
The platform SHALL use Symfony's built-in translation component with YAML message catalogs. Translation files SHALL be located at `translations/messages.uk.yaml` and `translations/messages.en.yaml`. All admin UI labels, navigation items, button text, and status messages SHALL use the Twig `|trans` filter. The Ukrainian catalog SHALL contain all strings as the canonical source. The English catalog SHALL provide English translations for all keys.

#### Scenario: Admin UI renders in Ukrainian by default
- **WHEN** the admin dashboard is loaded with locale `uk`
- **THEN** all labels, navigation items, and status text display in Ukrainian

#### Scenario: Admin UI renders in English when locale is en
- **WHEN** the admin dashboard is loaded with locale `en`
- **THEN** all labels, navigation items, and status text display in English

#### Scenario: Missing translation key falls back to Ukrainian
- **WHEN** a translation key exists in `messages.uk.yaml` but not in `messages.en.yaml`
- **THEN** the platform displays the Ukrainian text as fallback

### Requirement: Admin Language Switcher
The admin layout SHALL include a language switcher dropdown in the top header bar. The switcher SHALL display the current language and allow switching between Ukrainian and English. Switching SHALL be performed via a POST request to `/admin/locale/switch` that sets the `locale` cookie and redirects back to the current page.

#### Scenario: User switches language to English
- **WHEN** the user clicks "EN" in the language switcher
- **THEN** a POST request sets `locale=en` cookie and the page reloads in English

#### Scenario: User switches language back to Ukrainian
- **WHEN** the user clicks "UA" in the language switcher while on English locale
- **THEN** a POST request sets `locale=uk` cookie and the page reloads in Ukrainian

#### Scenario: Language switcher shows current locale
- **WHEN** the admin layout renders with locale `en`
- **THEN** the language switcher highlights "EN" as the active language

### Requirement: A2A Locale Forwarding
The A2A client SHALL include an `Accept-Language` HTTP header in all outbound A2A calls. The header value SHALL be the current request locale (e.g., `uk` or `en`). If no request context is available, the header SHALL default to `uk`.

#### Scenario: A2A call includes Accept-Language header
- **WHEN** the platform makes an A2A call while the user's locale is `en`
- **THEN** the outbound HTTP request includes the header `Accept-Language: en`

#### Scenario: A2A call defaults to uk when no request context
- **WHEN** the platform makes an A2A call from a CLI command or background job with no HTTP request
- **THEN** the outbound HTTP request includes the header `Accept-Language: uk`
