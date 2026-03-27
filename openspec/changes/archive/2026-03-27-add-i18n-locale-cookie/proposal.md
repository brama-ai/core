# Change: Add multilingual admin interface via cookie-based locale

## Why
The admin UI currently has all labels and text hardcoded in Ukrainian. To support English-speaking administrators and enable agents to respond in the user's preferred language, the platform needs cookie-based locale switching with Symfony's built-in translation integration.

## What Changes
- Add Symfony Translation component configuration and message catalogs (uk, en)
- Create `LocaleSubscriber` that reads a `locale` cookie and sets the request locale
- Add a language switcher dropdown to the admin layout header
- Apply `|trans` filter to all hardcoded strings in admin Twig templates
- Forward the user's locale as `Accept-Language` HTTP header in A2A outbound calls
- Add a WARN-level auditor check verifying agents read the `Accept-Language` header

## Impact
- Affected specs: `i18n-locale` (new), `a2a-server` (modified)
- Affected code: `apps/brama-core/` (config, src, templates), `skills/agent-auditor/`
- New files: translation config, message catalogs, LocaleSubscriber, locale controller
- Modified files: admin layout, all admin templates, A2AClient, auditor checklist
- No database changes required
- No breaking changes — Ukrainian remains the default locale
