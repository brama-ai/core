# Change: Add Multitenancy and User Management

## Why
The platform currently assumes a single-tenant MVP. To support multiple communities independently and securely, we need a multitenancy model. A Tenant will be tied to a User rather than a domain. We also need to manage Agent instance sharing between tenants and introduce proper RBAC to allow administrators to manage multiple tenants cleanly.

## What Changes
- Evolve `admin_users` into a full User entity with UUID, email, and multi-tenant relationships.
- Add Tenant entity with name, slug, enabled flag, and ownership.
- Introduce a `user_tenant` pivot table for many-to-many User ↔ Tenant with per-tenant role.
- Introduce Role-Based Access Control (RBAC) via Symfony Security Role Hierarchy and attribute-based Voters (`TenantVoter`, `AgentVoter`).
- Add Tenant Management (CRUD) with constraints preventing deletion if active agents or scheduled jobs exist.
- Add a tenant switcher to the Admin panel navigation.
- Add `tenant_id` column to `agent_registry`, `scheduled_jobs`, `agent_registry_audit`, and `scheduler_job_logs` tables.
- Enforce tenant scoping in all tenant-aware repository methods via `TenantContext` service injection (manual per-query approach chosen over DBAL middleware for transparency and debugging).
- Introduce the concept of a "Shared Agent" (`shared: true`) vs "Dedicated Agent" per tenant.
- Prevent installing a non-shared agent in multiple tenants (prompting user to create a new instance).

## Impact
- Affected specs: `tenant-management` (new), `admin-auth` (modified), `agent-registry` (modified), `job-scheduling` (modified)
- Affected code: `core` user/security models, admin panel routing and navigation, agent installation logic, scheduler job registration, Doctrine query layer, security configuration.
- **BREAKING**: `admin_users` table is replaced by `users` table; existing admin credentials will be migrated.
