# Design: Admin Web Login

## Context

The core platform uses Symfony 7 + PHP 8.5. Currently only `doctrine/dbal` is present —
there is no ORM, no security bundle, and no templating layer. The admin login must be
introduced with minimal dependency surface while remaining idiomatic Symfony.

## Goals / Non-Goals

- Goals:
  - Browser-accessible login form at `/admin/login`
  - Session-based authentication backed by `admin_users` DB table
  - Single seeded user `admin` / `test-password`
  - Protected dashboard page shown after login
  - DB migration managed by `doctrine/migrations` (consistent with future DBAL usage)
- Non-Goals:
  - User management UI (create / edit / delete admins)
  - Role-based access control beyond a single `ROLE_ADMIN` role
  - Remember-me / OAuth / 2FA
  - Full ORM integration (only DBAL is used)

## Decisions

### Authentication: Symfony Security Bundle (form-login)
Symfony's built-in form-login authenticator handles CSRF, password hashing, and session
management without custom middleware. It is the idiomatic Symfony choice and the least
error-prone path to secure session auth.

Alternative considered: manual POST handler + `$_SESSION` — rejected because it
bypasses CSRF protection and duplicates logic Symfony already provides safely.

### Templating: Twig (symfony/twig-bundle)
Twig is the canonical Symfony templating engine. The login and dashboard pages are simple
HTML forms; Twig handles CSRF token injection via `csrf_token()`. Adding Twig here also
unblocks future admin views without re-architecting.

Alternative considered: plain PHP controllers returning HTML strings — rejected as
unmaintainable and inconsistent with future admin growth.

### Persistence: Doctrine Migrations (DBAL-only)
`doctrine/migrations` works with DBAL directly (no ORM required). Migrations provide
version-controlled, repeatable schema changes consistent with the project's existing DBAL
dependency. The `admin_users` table is created via a single `Version*.php` migration.

Alternative considered: raw SQL seed script — rejected because it is not version-tracked
and breaks the migration-first pattern.

### User Provider: Custom DBAL UserProvider
Since there is no Doctrine ORM EntityManager, the standard `entity` provider is not
available. A small custom class implementing `UserProviderInterface` queries `admin_users`
via DBAL and is registered in `security.yaml`. This keeps the ORM-free approach intact.

## Risks / Trade-offs

| Risk | Mitigation |
|---|---|
| Adding twig + security increases dependency surface | Both are stable Symfony 7 components; risk is low |
| Hardcoded seed credentials in migration | Acceptable for dev/MVP; note in LOCAL_DEV.md that credentials must be rotated before production |
| No CSRF on logout (GET /admin/logout) | Symfony's `logout` can use POST + CSRF; for MVP GET logout is acceptable and will be noted |

## Migration Plan

Migration file created at `apps/core/migrations/Version<timestamp>.php`:
1. `CREATE TABLE admin_users (id SERIAL PRIMARY KEY, username VARCHAR(180) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, roles JSONB NOT NULL DEFAULT '["ROLE_ADMIN"]')`
2. `INSERT INTO admin_users (username, password, roles) VALUES ('admin', '<bcrypt-hash>', '["ROLE_ADMIN"]')`

Run via `make migrate` (new Makefile target).

## Open Questions

- Should `/admin/dashboard` show anything beyond a "logged in" confirmation? (MVP: no — just a welcome message)
- Should `test-password` be configurable via `.env`? (MVP: no — seed migration only; rotate before prod)
