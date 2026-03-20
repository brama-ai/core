## Context

The admin UI serves both Ukrainian and English-speaking operators. All UI strings are currently hardcoded in Ukrainian with no translation infrastructure. Agents receive A2A calls without locale context, so they cannot tailor responses to the user's language.

## Goals / Non-Goals

- Goals:
  - Cookie-based locale switching (uk/en) for admin UI
  - Symfony translation integration with `|trans` Twig filter
  - Language switcher in admin header
  - Locale forwarding to agents via Accept-Language header
  - Auditor WARN check for agent i18n readiness
- Non-Goals:
  - No user preference persistence in database (cookie is sufficient)
  - No per-agent translation catalogs (agents manage their own i18n)
  - No locale support for public/API endpoints (admin only for now)
  - No right-to-left (RTL) layout support

## Decisions

### Cookie-based locale (not session-based)
- **Decision**: Store locale in a plain `locale` cookie (not tied to PHP session)
- **Why**: Simpler, stateless, works for cookie-forwarding to agents
- **Alternatives**: Session-based (rejected — adds session dependency, harder to forward), URL prefix like `/en/admin` (rejected — requires route duplication)

### Symfony Translation component
- **Decision**: Use Symfony's built-in translation component (ships with FrameworkBundle)
- **Why**: Zero new dependencies, well-integrated with Twig, standard approach
- **Configuration**: `framework.default_locale: uk`, `framework.translator.default_path: '%kernel.project_dir%/translations'`

### LocaleSubscriber pattern
- **Decision**: Create `App\Locale\LocaleSubscriber` implementing `EventSubscriberInterface` on `kernel.request` (priority 100, after session but before routing)
- **Why**: Follows existing pattern of `TraceIdSubscriber`. Reads `locale` cookie from request, validates against allowed locales [uk, en], sets `$request->setLocale()`
- **Alternatives**: Twig global (rejected — doesn't integrate with Symfony translator)

### WARN not FAIL for auditor check
- **Decision**: Auditor check for Accept-Language handling is WARN severity, not FAIL
- **Why**: Not all agents need i18n. The check encourages but doesn't enforce.

### Locale cookie values
- **Decision**: Use ISO 639-1 codes: `uk` (Ukrainian), `en` (English). Default: `uk`
- **Cookie properties**: name=`locale`, path=`/`, SameSite=Lax, max-age=1 year, httpOnly=false (JS needs to read it for switcher state)

## Data Flow

```
User clicks language switcher
  → POST /admin/locale/switch (sets cookie, redirects back)
  → Next request: LocaleSubscriber reads cookie
  → Sets request locale to uk|en
  → Twig trans() uses messages.{locale}.yaml
  → Admin UI renders in selected language

A2A call from platform:
  → A2AClient reads RequestStack current request locale
  → Adds Accept-Language: uk (or en) header to outbound POST
  → Agent receives header, may use for response language
```

## Risks / Trade-offs

- **Risk**: Large number of template strings to translate → Mitigation: Extract all strings systematically, use Ukrainian as default fallback
- **Risk**: Missing translations show raw keys → Mitigation: Use descriptive Ukrainian as message keys so untranslated strings are still readable
- **Trade-off**: httpOnly=false for locale cookie (less secure) vs JS readability → Accepted because locale is not sensitive data

## Migration Plan

1. Add translation config and catalogs — no breaking changes
2. Add LocaleSubscriber — transparent, defaults to uk
3. Modify templates to use `|trans` — visual output unchanged for uk locale
4. Add language switcher — new UI element, non-breaking
5. Modify A2AClient — adds header, agents that don't read it are unaffected
6. Update auditor checklist — WARN only, no existing agents fail

## Open Questions

None — design is straightforward and uses standard Symfony patterns.
