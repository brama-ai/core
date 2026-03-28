## ADDED Requirements

### Requirement: Cloudflare Turnstile CAPTCHA on Edge Auth Login

The edge authentication login form at `/edge/auth/login` SHALL integrate Cloudflare Turnstile CAPTCHA to protect against automated brute-force attacks.

When `TURNSTILE_ENABLED` is `true`, the login form SHALL render the Turnstile widget and the server SHALL verify the `cf-turnstile-response` token against Cloudflare's siteverify API before validating credentials.

When `TURNSTILE_ENABLED` is `false`, the login form SHALL function without the Turnstile widget and no verification SHALL be performed.

The system SHALL fail closed: if the Turnstile API returns an error, times out, or is unreachable, the login attempt SHALL be denied.

#### Scenario: Login form renders Turnstile widget when enabled

- **WHEN** `TURNSTILE_ENABLED` is `true`
- **AND** a user visits `GET /edge/auth/login`
- **THEN** the page SHALL include the Cloudflare Turnstile JavaScript (`challenges.cloudflare.com/turnstile/v0/api.js`)
- **AND** the form SHALL contain a `div.cf-turnstile` element with the configured `data-sitekey`

#### Scenario: Login form omits Turnstile widget when disabled

- **WHEN** `TURNSTILE_ENABLED` is `false`
- **AND** a user visits `GET /edge/auth/login`
- **THEN** the page SHALL NOT include the Turnstile JavaScript
- **AND** the form SHALL NOT contain a `div.cf-turnstile` element

#### Scenario: Successful login with valid Turnstile token

- **WHEN** `TURNSTILE_ENABLED` is `true`
- **AND** a user submits valid credentials with a valid `cf-turnstile-response` token
- **THEN** the server SHALL POST the token to `https://challenges.cloudflare.com/turnstile/v0/siteverify` with the secret key and client IP
- **AND** Cloudflare returns `{"success": true}`
- **THEN** the login SHALL proceed normally (issue JWT cookie and redirect)

#### Scenario: Login rejected when Turnstile token is missing

- **WHEN** `TURNSTILE_ENABLED` is `true`
- **AND** a user submits credentials without a `cf-turnstile-response` token
- **THEN** the server SHALL return HTTP 401
- **AND** the login page SHALL display a CAPTCHA error message

#### Scenario: Login rejected when Turnstile token is invalid

- **WHEN** `TURNSTILE_ENABLED` is `true`
- **AND** a user submits credentials with an invalid `cf-turnstile-response` token
- **AND** Cloudflare returns `{"success": false}`
- **THEN** the server SHALL return HTTP 401
- **AND** the login page SHALL display a CAPTCHA error message

#### Scenario: Login rejected when Turnstile API is unreachable

- **WHEN** `TURNSTILE_ENABLED` is `true`
- **AND** a user submits credentials with a `cf-turnstile-response` token
- **AND** the Cloudflare siteverify API is unreachable or returns an error
- **THEN** the server SHALL return HTTP 401
- **AND** the login page SHALL display a CAPTCHA error message

#### Scenario: Login works normally when Turnstile is disabled

- **WHEN** `TURNSTILE_ENABLED` is `false`
- **AND** a user submits valid credentials
- **THEN** the login SHALL proceed without any Turnstile verification

### Requirement: Turnstile Configuration via Environment Variables

The system SHALL accept Cloudflare Turnstile configuration through environment variables with safe defaults.

The following environment variables SHALL be supported:
- `TURNSTILE_ENABLED` — boolean, defaults to `false`
- `TURNSTILE_SITE_KEY` — string, the public site key for the frontend widget
- `TURNSTILE_SECRET_KEY` — string, the private key for server-side verification

The `TURNSTILE_SECRET_KEY` SHALL never be logged, returned in HTTP responses, or exposed to the frontend.

#### Scenario: Default configuration disables Turnstile

- **WHEN** `TURNSTILE_ENABLED` is not set
- **THEN** Turnstile SHALL be disabled and the login form SHALL function without CAPTCHA

#### Scenario: Configuration wired through Symfony service container

- **WHEN** `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, and `TURNSTILE_SECRET_KEY` are set
- **THEN** the `LoginController` SHALL receive these values as constructor arguments via `services.yaml` parameter binding
