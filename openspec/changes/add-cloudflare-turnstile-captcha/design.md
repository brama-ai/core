## Context

The edge authentication login form (`/edge/auth/login`) protects admin tool entrypoints (Langfuse, OpenClaw, agent admin pages) via Traefik forward-auth. Currently there is no rate limiting or bot protection on the login endpoint, making it vulnerable to brute-force credential attacks.

Cloudflare Turnstile is a free, privacy-focused CAPTCHA alternative that provides bot detection without user-hostile challenges. It integrates via a frontend JavaScript widget and a backend token verification API.

**Stakeholders:** Platform operators, admin users.

## Goals / Non-Goals

- **Goals:**
  - Block automated brute-force login attempts on `/edge/auth/login`
  - Maintain smooth UX with managed (invisible when possible) challenge mode
  - Support feature toggle for development and fallback scenarios
  - Fail closed — if Turnstile verification fails or API is unreachable, deny login

- **Non-Goals:**
  - Protecting the admin login at `/admin/login` (separate auth flow, out of scope)
  - IP-based rate limiting (future enhancement)
  - Account lockout after N failed attempts (future enhancement)
  - Two-factor authentication (future enhancement)

## Decisions

### Decision 1: Use Symfony HttpClient for server-side verification

**What:** Inject `HttpClientInterface` into `LoginController` and use it to POST to Cloudflare's `/siteverify` endpoint.

**Why:** Symfony HttpClient is already available in the stack, provides proper timeout handling, exception management, and is easily mockable in tests. Avoids raw `file_get_contents()` which lacks timeout control and error handling.

**Alternatives considered:**
- `file_get_contents()` with stream context — simpler but no timeout control, harder to mock in tests, no retry support
- Dedicated `TurnstileVerifier` service — over-engineering for a single verification call; can extract later if reuse emerges

### Decision 2: Inline verification in LoginController (no separate service)

**What:** Add a `private verifyTurnstile(Request): bool` method directly in `LoginController`.

**Why:** The verification is a single HTTP call used in exactly one place. Extracting to a service adds indirection without benefit. If Turnstile is later needed on other forms, extract then.

**Alternatives considered:**
- Dedicated `TurnstileVerifierService` — premature abstraction for single-use case
- Symfony event listener on login — edge auth doesn't use Symfony's security authenticator, so events don't apply

### Decision 3: Feature toggle via environment variable

**What:** `TURNSTILE_ENABLED` (bool) controls whether the widget renders and verification runs. Defaults to `false`.

**Why:** Allows development without Cloudflare keys, graceful degradation if Cloudflare is unreachable, and easy rollback.

### Decision 4: Fail closed on verification errors

**What:** If the Turnstile API returns an error, times out, or is unreachable, treat it as verification failure and deny login.

**Why:** Fail-open would defeat the purpose — an attacker could block Cloudflare API access and bypass CAPTCHA. Operators can disable Turnstile via toggle if Cloudflare has an outage.

### Decision 5: Pass template variables via controller (not Twig global)

**What:** Pass `turnstile_enabled` and `turnstile_site_key` as template variables from the controller render calls.

**Why:** Keeps the scope narrow — only the edge auth login template needs these values. Twig globals would expose them to all templates unnecessarily.

## Risks / Trade-offs

- **Cloudflare API dependency** — Login blocked if Cloudflare is down. Mitigation: `TURNSTILE_ENABLED=false` toggle for emergency bypass.
- **Added latency** — ~100-300ms per login for server-side verification. Acceptable for a login form that's used infrequently.
- **E2E test complexity** — Turnstile widget complicates automated testing. Mitigation: Use Cloudflare's test keys (`1x00000000000000000000AA`) in E2E environment, or disable Turnstile in test config.
- **Secret key exposure** — `TURNSTILE_SECRET_KEY` must never be logged or returned in responses. Mitigation: Treat as sensitive parameter, use Kubernetes secrets in production.

## Migration Plan

1. Add environment variables to `.env.deployment.example` with test keys and `TURNSTILE_ENABLED=false` default
2. Deploy code changes — no effect until `TURNSTILE_ENABLED=true`
3. Operator registers Turnstile site in Cloudflare dashboard
4. Operator sets real keys and `TURNSTILE_ENABLED=true` in production secrets
5. Rollback: set `TURNSTILE_ENABLED=false` — instant, no code change needed

## Open Questions

None — the task spec is comprehensive and all design decisions are straightforward.
