## ADDED Requirements

### Requirement: Tenant Entity and Ownership
The system SHALL support multiple Tenants, each associated with one or more Users via a pivot table. A User MAY belong to multiple Tenants with a per-tenant role (owner, admin, member).

#### Scenario: User creates a tenant
- **WHEN** a user creates a new tenant with a unique name and slug
- **THEN** the tenant is persisted in the `tenants` table
- **AND** the user is assigned as the owner in `user_tenant` with `role = 'owner'`

#### Scenario: User belongs to multiple tenants
- **WHEN** a user is added to a second tenant
- **THEN** a new `user_tenant` row is created for that tenant
- **AND** the user can switch between tenants in the admin UI

### Requirement: Tenant CRUD
The system SHALL provide create, read, update, and delete operations for Tenants via admin controllers.

#### Scenario: Admin creates a tenant
- **WHEN** an authenticated admin submits the tenant creation form with a valid name
- **THEN** a new tenant is created with an auto-generated slug
- **AND** the admin is assigned as owner of the new tenant

#### Scenario: Admin updates a tenant
- **WHEN** a tenant owner or super admin updates tenant details (name, enabled flag)
- **THEN** the tenant record is updated

#### Scenario: Admin views tenant list
- **WHEN** a super admin navigates to the tenant management page
- **THEN** all tenants are listed with their name, slug, enabled status, and member count

### Requirement: Tenant Safe Deletion
The system MUST prevent deletion of a Tenant that has active agents or enabled scheduled jobs.

#### Scenario: Deleting a tenant with active agents
- **WHEN** an admin attempts to delete a tenant with installed agents in `agent_registry`
- **THEN** the system rejects the deletion and returns an error specifying the active agents that must be uninstalled first

#### Scenario: Deleting a tenant with active scheduled jobs
- **WHEN** an admin attempts to delete a tenant with enabled rows in `scheduled_jobs`
- **THEN** the system rejects the deletion and returns an error specifying the jobs that must be disabled first

#### Scenario: Deleting an empty tenant
- **WHEN** an admin attempts to delete a tenant with no agents and no enabled jobs
- **THEN** the tenant is deleted along with its `user_tenant` associations

### Requirement: Tenant Context Scoping
The system SHALL enforce tenant-level data isolation by scoping all repository queries on tenant-aware tables to the current tenant context via explicit `TenantContext` checks in each repository method.

#### Scenario: Queries are scoped to current tenant
- **WHEN** a repository method executes against `agent_registry`, `scheduled_jobs`, or other tenant-aware tables while `TenantContext` is set
- **THEN** the query includes `WHERE tenant_id = :current_tenant_id`

#### Scenario: Cross-tenant data is not accessible
- **WHEN** a user in Tenant A attempts to access agent data belonging to Tenant B
- **THEN** the query returns no results (filtered by TenantContext)

#### Scenario: Super admin can bypass tenant scoping
- **WHEN** a super admin explicitly requests cross-tenant data (e.g., global admin views)
- **THEN** the tenant filter is temporarily disabled for that query

### Requirement: Role-Based Access Control
The system SHALL enforce access control using Symfony Role Hierarchy for global roles and attribute-based Voters for tenant-scoped permissions.

#### Scenario: Role hierarchy is applied
- **WHEN** the security system evaluates permissions
- **THEN** `ROLE_SUPER_ADMIN` inherits `ROLE_ADMIN`, which inherits `ROLE_USER`

#### Scenario: Tenant Voter denies unauthorized access
- **WHEN** a user with `role = 'member'` in a tenant attempts to modify tenant settings
- **THEN** the `TenantVoter` denies the `TENANT_EDIT` attribute and returns HTTP 403

#### Scenario: Tenant Voter grants owner access
- **WHEN** a user with `role = 'owner'` in a tenant attempts to modify tenant settings
- **THEN** the `TenantVoter` grants the `TENANT_EDIT` attribute

### Requirement: Agent Tenant Isolation
Agents installed by a tenant MUST be exclusive to that tenant unless explicitly marked as shared.

#### Scenario: Attempt to install a non-shared agent
- **WHEN** Tenant B attempts to install an agent already installed by Tenant A (and `shared` is false)
- **THEN** the system returns an error explaining the agent is bound to Tenant A, advising the creation of a new agent instance

#### Scenario: Install a shared agent
- **WHEN** Tenant B attempts to install an agent marked `shared: true`
- **THEN** the installation succeeds and the agent operates simultaneously in both tenants

#### Scenario: Agent queries are scoped to tenant
- **WHEN** a tenant admin views their agent list
- **THEN** only agents with `tenant_id` matching their current tenant are shown

### Requirement: Admin Tenant Switcher
The administrative user interface SHALL provide a switcher allowing users to change their active tenant context.

#### Scenario: User switches tenant context
- **WHEN** an administrator belongs to multiple tenants and selects a different tenant in the top navigation switcher
- **THEN** the administrative interface context switches to display resources only belonging to the selected tenant

#### Scenario: Single-tenant user sees no switcher
- **WHEN** an administrator belongs to only one tenant
- **THEN** the tenant switcher is hidden and the single tenant is auto-selected

#### Scenario: Tenant context persists across navigation
- **WHEN** a user selects a tenant and navigates to different admin pages
- **THEN** the selected tenant context is preserved in the session
