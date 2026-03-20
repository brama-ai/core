## 1. Translation Infrastructure
- [x] 1.1 Add `framework.default_locale` and `framework.translator` config to `apps/core/config/packages/framework.yaml`
- [x] 1.2 Create `apps/core/translations/messages.uk.yaml` with all admin UI strings (Ukrainian canonical)
- [x] 1.3 Create `apps/core/translations/messages.en.yaml` with English translations

## 2. Locale Subscriber
- [x] 2.1 Create `apps/core/src/Locale/LocaleSubscriber.php` — reads `locale` cookie, validates against [uk, en], sets request locale
- [x] 2.2 Create `apps/core/src/Controller/Admin/LocaleController.php` — POST endpoint to switch locale (sets cookie, redirects back)
- [x] 2.3 Register route for locale switch in controller

## 3. Admin Layout Language Switcher
- [x] 3.1 Add language switcher dropdown to `apps/core/templates/admin/layout.html.twig` header
- [x] 3.2 Set `<html lang>` attribute dynamically based on current locale

## 4. Apply Translations to Admin Templates
- [x] 4.1 Apply `|trans` filter to `layout.html.twig` (sidebar nav, header, footer)
- [x] 4.2 Apply `|trans` filter to `dashboard.html.twig`
- [x] 4.3 Apply `|trans` filter to `agents.html.twig` and `_agents_table.html.twig`
- [x] 4.4 Apply `|trans` filter to `chats.html.twig` and `chat_detail.html.twig`
- [x] 4.5 Apply `|trans` filter to `logs.html.twig` and `log_trace.html.twig`
- [x] 4.6 Apply `|trans` filter to `settings.html.twig`
- [x] 4.7 Apply `|trans` filter to `login.html.twig`
- [x] 4.8 Apply `|trans` filter to scheduler templates
- [x] 4.9 Apply `|trans` filter to coder templates
- [x] 4.10 Apply `|trans` filter to tenant templates (N/A - no tenant templates found)

## 5. A2A Locale Forwarding
- [x] 5.1 Inject `RequestStack` into `A2AClient`
- [x] 5.2 Add `Accept-Language` header to outbound A2A calls in `callAgent()` method
- [x] 5.3 Update `A2AClient` service definition in `services.yaml` if needed

## 6. Auditor Skill Update
- [x] 6.1 Add i18n check row to `skills/agent-auditor/references/checklist-php.md` (Q section, WARN severity)
- [x] 6.2 Sync skills to agent directories: `make sync-skills` or `./scripts/sync-skills.sh claude`

## 7. Tests
- [ ] 7.1 Unit test for `LocaleSubscriber` — cookie reading, default fallback, invalid value handling
- [ ] 7.2 Unit test for `A2AClient` — verify Accept-Language header is included in outbound calls
- [ ] 7.3 Functional test for locale switch controller — POST sets cookie and redirects

## 8. Documentation
- [ ] 8.1 Create `docs/features/i18n-locale/en/i18n-locale.md` (English, developer-facing)
- [ ] 8.2 Create `docs/features/i18n-locale/ua/i18n-locale.md` (Ukrainian mirror)
- [ ] 8.3 Update `docs/agent-requirements/conventions.md` — document Accept-Language header convention

## 9. Quality Checks
- [ ] 9.1 `make analyse` — PHPStan level 8, zero errors
- [ ] 9.2 `make cs-fix` — PHP CS Fixer, zero violations
- [ ] 9.3 `make test` — all unit + functional suites pass
