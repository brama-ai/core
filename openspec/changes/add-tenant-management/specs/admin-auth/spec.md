## MODIFIED Requirements

### Requirement: Admin Users Database Table
The system SHALL maintain a `users` table (evolved from `admin_users`) in Postgres with UUID primary key, email, username, hashed password, global roles, and timestamps. At least one seeded super-admin record SHALL exist for local development, managed by Doctrine Migrations.

#### Scenario: Migration evolves admin_users to users table
- **WHEN** `doctrine:migrations:migrate` is run
- **THEN** the `users` table exists with columns: `id` (UUID), `email`, `username`, `password`, `roles` (JSONB), `created_at`, `updated_at`
- **AND** the existing admin credentials from `admin_users` are preserved in the new table

#### Scenario: Default super-admin is seeded
- **WHEN** `doctrine:migrations:migrate` is run against an empty database
- **THEN** the `users` table contains a row with `username = 'admin'`, `email = 'admin@localhost'`, a bcrypt-hashed password for `test-password`, and `roles` containing `ROLE_SUPER_ADMIN`

### Requirement: Admin Login Page
The system SHALL expose a login form at `GET /admin/login` that accepts an email or username and password and authenticates the user against the `users` database table.

#### Scenario: Successful login redirects to dashboard
- **WHEN** a POST request is sent to `/admin/login` with valid credentials (`admin` / `test-password`)
- **THEN** the response redirects to `GET /admin/dashboard` with HTTP 302

#### Scenario: Failed login returns to login page with error
- **WHEN** a POST request is sent to `/admin/login` with invalid credentials
- **THEN** the response returns HTTP 200 and the login page body contains an error message

#### Scenario: Login page is publicly accessible
- **WHEN** an unauthenticated user visits `GET /admin/login`
- **THEN** the response is HTTP 200 and the page contains a login form

### Requirement: Admin Dashboard Page
The system SHALL expose a protected page at `GET /admin/dashboard` that is only accessible to authenticated users. The dashboard SHALL display resources scoped to the user's active tenant context.

#### Scenario: Dashboard accessible after login
- **WHEN** an authenticated user visits `GET /admin/dashboard`
- **THEN** the response is HTTP 200 and the page shows tenant-scoped content

#### Scenario: Dashboard redirects unauthenticated visitors to login
- **WHEN** an unauthenticated user visits `GET /admin/dashboard`
- **THEN** the response redirects to `GET /admin/login` with HTTP 302

#### Scenario: Dashboard sets default tenant context
- **WHEN** an authenticated user visits `GET /admin/dashboard` without a tenant selected
- **THEN** the first tenant they belong to is auto-selected as the active tenant context
