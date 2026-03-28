# Change: Add Cloudflare Turnstile CAPTCHA to Edge Authentication

## Why

The edge authentication login form at `/edge/auth/login` currently has no bot or brute-force protection. Any automated script can submit unlimited credential attempts against the admin login endpoint. Cloudflare Turnstile adds a privacy-friendly, user-transparent CAPTCHA challenge that blocks automated attacks while preserving a smooth login experience.

## What Changes

- **Add Turnstile widget** to the edge auth login template (`edge_auth/login.html.twig`) — renders a Cloudflare challenge before form submission
- **Add server-side Turnstile verification** in `LoginController` — validates the `cf-turnstile-response` token against Cloudflare's `/siteverify` API before checking credentials
- **Add feature toggle** via `TURNSTILE_ENABLED` environment variable — allows disabling CAPTCHA in development or when Cloudflare API is unreachable
- **Add environment configuration** — `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `TURNSTILE_ENABLED` in `.env.deployment.example` and Symfony service config
- **Add setup documentation** — operator guide for Cloudflare dashboard registration, key management, and troubleshooting
- **Add/update tests** — functional tests with mocked Turnstile API, E2E test strategy for CAPTCHA-protected login

## Impact

- Affected specs: `observability-integration` (edge auth login flow)
- Affected code:
  - `brama-core/src/src/Controller/EdgeAuth/LoginController.php` — add Turnstile verification logic and constructor params
  - `brama-core/src/templates/edge_auth/login.html.twig` — add Turnstile widget and script tag
  - `brama-core/src/config/services.yaml` — add Turnstile parameters and service wiring
  - `.env.deployment.example` — add Turnstile environment variables
- New external dependency: Cloudflare Turnstile API (`challenges.cloudflare.com/turnstile/v0/siteverify`)
- No database migrations required
- No breaking changes — feature is opt-in via `TURNSTILE_ENABLED=true`
