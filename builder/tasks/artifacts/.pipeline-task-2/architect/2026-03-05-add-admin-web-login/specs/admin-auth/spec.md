## ADDED Requirements

### Requirement: Admin Login Page

The system SHALL expose a login form at `GET /admin/login` that accepts a username and
password and authenticates the user against the `admin_users` database table.

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

The system SHALL expose a protected page at `GET /admin/dashboard` that is only accessible
to authenticated admin users.

#### Scenario: Dashboard accessible after login

- **WHEN** an authenticated admin user visits `GET /admin/dashboard`
- **THEN** the response is HTTP 200 and the page contains a welcome message for the admin

#### Scenario: Dashboard redirects unauthenticated visitors to login

- **WHEN** an unauthenticated user visits `GET /admin/dashboard`
- **THEN** the response redirects to `GET /admin/login` with HTTP 302

### Requirement: Admin Logout

The system SHALL allow an authenticated admin user to log out by visiting `GET /admin/logout`,
invalidating their session.

#### Scenario: Logout invalidates session and redirects

- **WHEN** an authenticated admin user visits `GET /admin/logout`
- **THEN** the session is invalidated and the response redirects to `GET /admin/login`

### Requirement: Admin Users Database Table

The system SHALL maintain an `admin_users` table in Postgres with at least one seeded record
for local development, managed by a Doctrine Migration.

#### Scenario: Migration creates table and seeds admin user

- **WHEN** `doctrine:migrations:migrate` is run against an empty database
- **THEN** the `admin_users` table exists and contains a row with `username = 'admin'`
  and a bcrypt-hashed password for `test-password`
