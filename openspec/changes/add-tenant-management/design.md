## Context
We are migrating from a single-tenant MVP to a multi-tenant platform where a Tenant is tied to a User rather than a domain structure. The existing `admin_users` table is a minimal auth stub that needs to evolve into a proper User entity. All tenant-aware tables (`agent_registry`, `scheduled_jobs`, audit tables) need a `tenant_id` foreign key and automatic query scoping.

## Goals / Non-Goals
- Goals: User entity evolution, User-to-Tenant many-to-many with per-tenant roles, Symfony RBAC setup, Tenant CRUD with safety guards, admin tenant switcher, Agent tenant isolation, automatic tenant scoping in queries.
- Non-Goals: Physical database isolation per tenant (we use logical isolation via `tenant_id`). No multi-database routing. No per-tenant configuration files or environment variables.

## Data Model

### New Tables

```
users (evolved from admin_users)
├── id (SERIAL, legacy PK — kept for backwards compatibility)
├── uuid (UUID, UNIQUE — logical PK used by all new foreign keys)
├── email (VARCHAR(180), UNIQUE)
├── username (VARCHAR(180), UNIQUE)
├── password (VARCHAR(255))
├── roles (JSONB, default '["ROLE_USER"]')  -- global roles
├── created_at (TIMESTAMPTZ)
└── updated_at (TIMESTAMPTZ)

tenants
├── id (UUID, PK)
├── name (VARCHAR(255))
├── slug (VARCHAR(128), UNIQUE)
├── enabled (BOOLEAN, default TRUE)
├── created_at (TIMESTAMPTZ)
└── updated_at (TIMESTAMPTZ)

user_tenant
├── user_id (UUID, FK → users.uuid ON DELETE CASCADE)
├── tenant_id (UUID, FK → tenants.id ON DELETE CASCADE)
├── role (VARCHAR(32), default 'member')  -- 'owner', 'admin', 'member'
├── joined_at (TIMESTAMPTZ)
└── PRIMARY KEY (user_id, tenant_id)
```

### Modified Tables

```
agent_registry
└── + tenant_id (UUID, FK → tenants.id ON DELETE RESTRICT, NOT NULL)
    -- existing unique(name) becomes unique(name, tenant_id)

scheduled_jobs
└── + tenant_id (UUID, FK → tenants.id ON DELETE CASCADE, NOT NULL)
    -- existing unique(agent_name, job_name) becomes unique(agent_name, job_name, tenant_id)

agent_registry_audit
└── + tenant_id (UUID, FK → tenants.id ON DELETE SET NULL, nullable)

scheduler_job_logs
└── + tenant_id (UUID, FK → tenants.id ON DELETE SET NULL, nullable)
```

## Decisions

- Decision: **Evolve admin_users → users.** Migration renames the table, adds UUID as new PK (keeping legacy int id temporarily), adds email column. Existing admin credentials are preserved. This avoids maintaining two separate user tables.

- Decision: **Symfony Security RBAC.** We use Symfony's native Role Hierarchy for global roles (`ROLE_USER`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`) and attribute-based Voters for tenant-scoped permissions:
  - `TenantVoter` — checks user's role within a specific tenant (`TENANT_VIEW`, `TENANT_EDIT`, `TENANT_DELETE`)
  - `AgentVoter` — checks agent installation permissions within tenant context (`AGENT_INSTALL`, `AGENT_MANAGE`)

- Decision: **Tenant Ownership.** A User can belong to multiple Tenants via `user_tenant` pivot with a per-tenant role. This supports the admin panel tenant switcher use case.

- Decision: **Tenant Context Scoping.** A `TenantContext` service holds the current tenant (set from session/switcher). Each repository method explicitly checks `TenantContext` and appends `WHERE tenant_id = ?` to tenant-aware queries. This manual approach was chosen over a DBAL middleware for transparency, easier debugging, and explicit control over which queries are scoped (e.g., scheduler polling runs globally, not per-tenant).

- Decision: **Agent Isolation.** Agent installations belong to a specific Tenant. If an agent is not marked as `shared: true`, an attempt by another tenant to install it is blocked, enforcing separate instances per tenant.

- Decision: **Safe Deletion.** A `TenantDeletionGuard` service verifies no active agents or enabled scheduled jobs exist before allowing a Tenant to be deleted. Uses `ON DELETE RESTRICT` on `agent_registry.tenant_id` as a database-level safety net.

- Alternatives considered:
  - Separate database per tenant — rejected: overkill for MVP, complicates migrations and connection pooling.
  - Domain-based tenancy — rejected: tenants are tied to users/communities, not domains.
  - Separate `tenant_admins` table — rejected: adds complexity; pivot table with role column is simpler.

## Risks / Trade-offs

- Risk: Cross-tenant data leakage → Mitigation: `TenantContext` DBAL middleware enforces scoping at query level; `TenantVoter` enforces at controller level. Both layers must pass.
- Risk: "Shared" agents accessing per-tenant data → Mitigation: Shared agents must still process messages strictly within a designated Tenant context; the `TenantContext` scoping applies to shared agents too.
- Risk: Migration from `admin_users` to `users` breaks existing sessions → Mitigation: Migration preserves credentials; session cookie name changes; users must re-login once.
- Risk: Forgotten `tenant_id` filter in custom queries → Mitigation: Manual scoping pattern is consistent across all repositories; code review checklist item; functional tests verify isolation.

## Migration Plan

1. Create `tenants` table and seed a default tenant.
2. Create `users` table by evolving `admin_users` (rename + add columns).
3. Create `user_tenant` pivot and assign existing admin to default tenant as owner.
4. Add `tenant_id` to `agent_registry`, `scheduled_jobs`, audit tables — set existing rows to default tenant.
5. Add NOT NULL constraint and foreign keys after backfill.
6. Update Symfony security configuration (firewall, providers, role hierarchy).
7. Deploy and verify; rollback = revert migration (columns are additive until step 5).

## Open Questions

- Should "shared agents" be global or only shared among a specific group of tenants? (MVP assumes globally shared if `shared: true`).
- Should tenant slug be used in URL routing (e.g., `/admin/{tenant-slug}/...`) or only in session context?

## Known Gaps (for follow-up changes)

- **Telegram tables** (`telegram_bots`, `telegram_chats`) do not yet have `tenant_id`. These were added in `Version20260318000001` and will need a separate change to add tenant scoping when Telegram integration becomes multi-tenant aware.
- **`agent_projects` table** does not yet have `tenant_id`. Needs follow-up when coder agent becomes tenant-aware.
