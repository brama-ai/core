# Tenant Context Developer Guide

## Architecture

Tenant isolation uses logical scoping via `tenant_id` columns. All tenant-aware tables include a
`tenant_id UUID` foreign key to the `tenants` table.

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `TenantContext` | `src/Tenant/TenantContext.php` | Request-scoped service holding the current tenant |
| `TenantContextListener` | `src/Tenant/TenantContextListener.php` | Sets TenantContext from session on each request |
| `TenantRepository` | `src/Tenant/TenantRepository.php` | DBAL queries for tenants and user-tenant pivot |
| `TenantVoter` | `src/Security/TenantVoter.php` | RBAC for TENANT_VIEW/EDIT/DELETE |
| `AgentVoter` | `src/Security/AgentVoter.php` | RBAC for AGENT_INSTALL/MANAGE |
| `TenantDeletionGuard` | `src/Tenant/TenantDeletionGuard.php` | Prevents deletion of tenants with active resources |

### Data Model

```
users (evolved from admin_users)
├── id (serial, legacy PK)
├── uuid (UUID, new logical PK)
├── username, email, password, roles
└── created_at, updated_at

tenants
├── id (UUID)
├── name, slug (unique)
├── enabled
└── created_at, updated_at

user_tenant (pivot)
├── user_id (FK → users.uuid)
├── tenant_id (FK → tenants.id)
├── role ('owner', 'admin', 'member')
└── joined_at
```

### Tenant-Aware Tables

These tables have a `tenant_id` column:
- `agent_registry` — NOT NULL, ON DELETE RESTRICT, unique on `(name, tenant_id)`
- `scheduled_jobs` — NOT NULL, ON DELETE CASCADE, unique on `(agent_name, job_name, tenant_id)`
- `agent_registry_audit` — nullable, ON DELETE SET NULL
- `scheduler_job_logs` — nullable, ON DELETE SET NULL
- `a2a_message_audit` — nullable, ON DELETE SET NULL

## Usage in Repositories

Repositories inject `TenantContext` and scope queries:

```php
// For user-facing queries (admin panel, API)
$tenantId = $this->tenantContext->requireTenantId();
$this->connection->fetchAllAssociative(
    'SELECT * FROM agent_registry WHERE tenant_id = :tenantId',
    ['tenantId' => $tenantId],
);

// For background processes (scheduler, health poller)
// Do NOT scope — these run globally
$this->connection->fetchAllAssociative(
    'SELECT * FROM scheduled_jobs WHERE enabled = TRUE AND next_run_at <= now()',
);
```

## RBAC

### Role Hierarchy

```
ROLE_SUPER_ADMIN > ROLE_ADMIN > ROLE_USER
```

### Voter Attributes

| Attribute | Voter | Required Tenant Role |
|-----------|-------|---------------------|
| TENANT_VIEW | TenantVoter | any member |
| TENANT_EDIT | TenantVoter | owner or admin |
| TENANT_DELETE | TenantVoter | owner only |
| AGENT_INSTALL | AgentVoter | owner or admin |
| AGENT_MANAGE | AgentVoter | owner or admin |

`ROLE_SUPER_ADMIN` bypasses all voter checks.

## Testing

In functional tests, the `Helper\\Functional` module automatically sets the default tenant context
(`00000000-0000-4000-a000-000000000001`) before each test. No manual setup needed.

For unit tests, create a `TenantContext` instance and call `set()`:

```php
$tenantContext = new TenantContext();
$tenantContext->set(new Tenant('test-id', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable()));
```

## Migration from `admin_users`

The `admin_users` table was renamed to `users` with these additions:
- `uuid` column (UUID, unique) — used as the logical primary key for all new references
- `email` column (unique) — backfilled as `{username}@localhost`
- `created_at`, `updated_at` timestamps
- Default roles changed from `ROLE_ADMIN` to `ROLE_USER` for new users
- Existing admin upgraded to `ROLE_SUPER_ADMIN`
