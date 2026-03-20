# Change: Add Admin Web Login

## Why

The MVP includes a minimal web admin panel as an entry point for platform administration.
A browser-accessible login screen enables quick access verification and serves as the
foundation for future admin tooling.

## What Changes

- Add Symfony Security Bundle for session-based form authentication
- Add Twig templating (symfony/twig-bundle) for login and dashboard pages
- Add Doctrine Migrations (doctrine/migrations) for schema management
- Create `admin_users` database table via migration
- Seed one admin user: `admin` / `test-password` (bcrypt-hashed)
- Implement a DBAL-backed custom User + UserProvider
- Add `GET /admin/login`, `POST /admin/login`, `GET /admin/logout` routes
- Add `GET /admin/dashboard` — protected success page after login
- Add Makefile target `make migrate` to apply migrations inside the container

## Impact

- Affected specs: `admin-auth` (new capability)
- Affected code:
  - `apps/core/composer.json` — new runtime + dev deps
  - `apps/core/config/packages/security.yaml` — new (security config)
  - `apps/core/config/packages/twig.yaml` — new (twig config)
  - `apps/core/src/Controller/Admin/` — new controllers
  - `apps/core/src/Security/` — new User + UserProvider classes
  - `apps/core/migrations/` — new migration file
  - `apps/core/templates/admin/` — new Twig templates
  - `Makefile` — `migrate` target
