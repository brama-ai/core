## 1. Dependencies

- [x] 1.1 Add `symfony/security-bundle` to `apps/core/composer.json` require
- [x] 1.2 Add `symfony/twig-bundle` + `twig/twig` to `apps/core/composer.json` require
- [x] 1.3 Add `doctrine/migrations` to `apps/core/composer.json` require
- [x] 1.4 Run `composer update` inside the core container (`make install`)

## 2. Database Migration

- [x] 2.1 Configure `doctrine/migrations` in `apps/core/config/packages/doctrine_migrations.yaml`
- [x] 2.2 Add `make migrate` target: `$(COMPOSE) exec core ./vendor/bin/doctrine-migrations migrate --no-interaction`
- [x] 2.3 Generate migration file under `apps/core/migrations/` that:
  - Creates `admin_users` table: `id SERIAL PK`, `username VARCHAR(180) UNIQUE NOT NULL`, `password VARCHAR(255) NOT NULL`, `roles JSONB NOT NULL DEFAULT '["ROLE_ADMIN"]'`
  - Inserts seed row: `username=admin`, `password=<bcrypt hash of test-password>`, `roles=["ROLE_ADMIN"]`

## 3. Security Layer

- [x] 3.1 Create `apps/core/src/Security/AdminUser.php` implementing `UserInterface` + `PasswordAuthenticatedUserInterface`
- [x] 3.2 Create `apps/core/src/Security/AdminUserProvider.php` implementing `UserProviderInterface` — loads user from DBAL by username
- [x] 3.3 Create `apps/core/config/packages/security.yaml`:
  - password hasher: `bcrypt`
  - provider: custom `AdminUserProvider`
  - firewall `admin`: pattern `^/admin`, form-login, logout at `/admin/logout`
  - access control: `GET /admin/login` → `PUBLIC_ACCESS`, `^/admin` → `ROLE_ADMIN`

## 4. Controllers

- [x] 4.1 Create `apps/core/src/Controller/Admin/LoginController.php` — `GET /admin/login` (renders login template; Symfony Security handles POST internally)
- [x] 4.2 Create `apps/core/src/Controller/Admin/DashboardController.php` — `GET /admin/dashboard` (renders dashboard template)

## 5. Templates

- [x] 5.1 Configure `apps/core/config/packages/twig.yaml` (template path `%kernel.project_dir%/templates`)
- [x] 5.2 Create `apps/core/templates/admin/login.html.twig` — HTML form POSTing to `/admin/login` with CSRF token, username + password fields, error message block
- [x] 5.3 Create `apps/core/templates/admin/dashboard.html.twig` — welcome message showing logged-in username and logout link

## 6. Tests

- [x] 6.1 Write Codeception functional test `LoginCest` — GET /admin/login returns 200 with form
- [x] 6.2 Write Codeception functional test — POST valid credentials → 302 to /admin/dashboard
- [x] 6.3 Write Codeception functional test — POST invalid credentials → 200 with error
- [x] 6.4 Write Codeception functional test — GET /admin/dashboard unauthenticated → 302 to /admin/login
- [x] 6.5 Write Codeception functional test — GET /admin/dashboard after login → 200 with welcome text
- [x] 6.6 Write Codeception functional test — GET /admin/logout invalidates session → 302 to /admin/login

## 7. Documentation

- [x] 7.1 Add admin login section to `LOCAL_DEV.md`: URL, credentials, warning to rotate before production

## 8. Validation

- [x] 8.1 `make migrate` — runs without error against local Postgres
- [x] 8.2 `make analyse` — zero PHPStan errors at level 8
- [x] 8.3 `make cs-check` — no style violations
- [x] 8.4 `make test` — all Codeception suites pass (unit + functional)
- [x] 8.5 Manual smoke test: `GET http://localhost/admin/login` returns login form, valid login redirects to dashboard
