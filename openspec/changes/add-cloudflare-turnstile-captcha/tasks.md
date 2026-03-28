## 1. Environment Configuration

- [ ] 1.1 Add `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` to `.env.deployment.example` under a new "Cloudflare Turnstile Configuration" section (after Edge Authentication block, line ~96). Use Cloudflare test keys as defaults with `TURNSTILE_ENABLED=false`.
- [ ] 1.2 Add Symfony parameters in `brama-core/src/config/services.yaml`: `turnstile.enabled` (`env(bool:TURNSTILE_ENABLED)`), `turnstile.site_key` (`env(TURNSTILE_SITE_KEY)`), `turnstile.secret_key` (`env(TURNSTILE_SECRET_KEY)`) with safe `env()` defaults.
- [ ] 1.3 Wire `LoginController` arguments `$turnstileEnabled`, `$turnstileSiteKey`, `$turnstileSecretKey` in `services.yaml`.

## 2. Backend Implementation

- [ ] 2.1 Add constructor parameters to `LoginController`: `bool $turnstileEnabled`, `string $turnstileSiteKey`, `string $turnstileSecretKey`, and inject `HttpClientInterface $httpClient`.
- [ ] 2.2 Pass `turnstile_enabled` and `turnstile_site_key` template variables in all `render()` calls for the login template (GET handler, POST error responses).
- [ ] 2.3 Add `private verifyTurnstile(Request $request): bool` method that POSTs `cf-turnstile-response` token to `https://challenges.cloudflare.com/turnstile/v0/siteverify` with secret key and client IP. Return `false` on empty token, API error, or `success !== true`.
- [ ] 2.4 Add Turnstile verification check in POST handler: if `turnstileEnabled` and `!verifyTurnstile()`, return 401 with CAPTCHA error message before credential validation.

## 3. Frontend Implementation

- [ ] 3.1 Add conditional Turnstile script tag in `<head>` of `edge_auth/login.html.twig`: `<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>` when `turnstile_enabled` is true.
- [ ] 3.2 Add conditional `<div class="cf-turnstile" data-sitekey="{{ turnstile_site_key }}" data-theme="light">` before the submit button when `turnstile_enabled` is true.

## 4. Testing

- [ ] 4.1 Add functional test: login with `TURNSTILE_ENABLED=false` works without Turnstile token (existing behavior preserved).
- [ ] 4.2 Add functional test: login with `TURNSTILE_ENABLED=true` and missing `cf-turnstile-response` returns 401 with CAPTCHA error.
- [ ] 4.3 Add functional test: login with `TURNSTILE_ENABLED=true` and mocked successful Turnstile API response proceeds to credential check.
- [ ] 4.4 Add functional test: login with `TURNSTILE_ENABLED=true` and mocked failed Turnstile API response returns 401.
- [ ] 4.5 Update E2E test configuration to use `TURNSTILE_ENABLED=false` or Cloudflare test keys to avoid CAPTCHA blocking automated tests.

## 5. Documentation

- [ ] 5.1 Create `docs/security/cloudflare-turnstile-setup.md` with: Cloudflare dashboard walkthrough, development vs production key differences, environment variable reference, verification instructions, troubleshooting guide.
- [ ] 5.2 Create `docs/security/cloudflare-turnstile-setup.en.md` English mirror.

## 6. Quality Checks

- [ ] 6.1 `phpstan analyse` passes at level 8 with zero errors.
- [ ] 6.2 `php-cs-fixer check` reports no style violations.
- [ ] 6.3 `codecept run` — all unit and functional suites pass.
- [ ] 6.4 `make e2e` — Playwright E2E passes with Turnstile disabled or test keys.
