## 1. Database Migrations
- [x] 1.1 Create migration: `tenants` table (id UUID, name, slug UNIQUE, enabled, timestamps)
- [x] 1.2 Create migration: evolve `admin_users` → `users` table (add UUID id, email, timestamps; preserve existing credentials)
- [x] 1.3 Create migration: `user_tenant` pivot table (user_id, tenant_id, role, joined_at; PK on user_id+tenant_id)
- [x] 1.4 Create migration: add `tenant_id` (UUID, FK) to `agent_registry` and `scheduled_jobs`; add `shared` flag; update unique constraints
- [x] 1.5 Create migration: add `tenant_id` (nullable, FK SET NULL) to `agent_registry_audit`, `scheduler_job_logs`, `a2a_message_audit`
- [x] 1.6 Create seed migration: insert default tenant, assign existing admin user as owner, backfill tenant_id

## 2. User Entity and Security
- [x] 2.1 Create User entity class (UUID, email, username, password, roles, tenant memberships)
- [x] 2.2 Create UserProvider with DBAL queries (loads user + tenant memberships, supports login by username or email)
- [x] 2.3 Update Symfony security config: firewall, user provider, role hierarchy (`ROLE_USER` < `ROLE_ADMIN` < `ROLE_SUPER_ADMIN`)
- [x] 2.4 Update all controllers to use User instead of AdminUser

## 3. Tenant Entity and Context
- [x] 3.1 Create Tenant entity class, TenantRepository, and TenantRepositoryInterface
- [x] 3.2 Create `TenantContext` service (holds current tenant from session; injectable)
- [x] 3.3 Create `TenantContextListener` (sets TenantContext from session on each request)
- [x] 3.4 Create `TenantDeletionGuard` service (checks for active agents and enabled jobs)

## 4. RBAC (Voters)
- [x] 4.1 Implement `TenantVoter` (attributes: TENANT_VIEW, TENANT_EDIT, TENANT_DELETE; checks user_tenant role)
- [x] 4.2 Implement `AgentVoter` (attributes: AGENT_INSTALL, AGENT_MANAGE; checks tenant membership)
- [x] 4.3 Write unit tests for both Voters

## 5. Tenant CRUD
- [x] 5.1 Implement Tenant creation controller and form (auto-generates slug, assigns creator as owner)
- [x] 5.2 Implement Tenant update controller and form
- [x] 5.3 Implement Tenant deletion controller (uses guard, returns specific errors on rejection)
- [x] 5.4 Create tenant list and management admin views (index, create, edit templates)

## 6. Agent Isolation
- [x] 6.1 Add `shared` boolean column to agent_registry table (migration)
- [x] 6.2 Update AgentRegistryRepository: all queries scoped by tenant_id via TenantContext
- [x] 6.3 Add `isAgentInstalledInOtherTenant()` method for shared/dedicated enforcement
- [x] 6.4 Update agent register/enable/disable/delete/uninstall to scope by tenant_id
- [x] 6.5 Update AgentInstallController to enforce shared/dedicated constraint on install

## 7. Scheduler Tenant Scoping
- [x] 7.1 Update ScheduledJobRepository: registerJob includes tenant_id, findAll scoped by tenant
- [x] 7.2 Update deleteByAgent/enableByAgent/disableByAgent to scope by tenant when context set
- [x] 7.3 Scheduler polling (findDueJobs) remains global across all tenants

## 8. Admin UI Updates
- [x] 8.1 Add tenant switcher dropdown to admin navigation bar (layout.html.twig)
- [x] 8.2 Create TenantSwitchController (stores selection in session)
- [x] 8.3 Create TenantExtension (Twig globals: currentTenant, userTenants)
- [x] 8.4 Add tenant management pages (list, create, edit templates)
- [x] 8.5 Add "Тенанти" nav link for ROLE_SUPER_ADMIN in sidebar

## 9. Tests
- [x] 9.1 Unit tests: User entity, TenantContext, TenantDeletionGuard (26 unit tests pass)
- [x] 9.2 Unit tests: TenantVoter (7 tests), AgentVoter (5 tests)
- [x] 9.3 Functional tests: Tenant CRUD (7 tests) and tenant switch (4 tests)
- [x] 9.4 Updated existing AgentRegistryRepositoryTest for tenant context
- [x] 9.5 Updated Functional helper to auto-set default tenant context
- [x] 9.6 E2E: TenantsPage Page Object (`tests/e2e/support/pages/TenantsPage.js`)
- [x] 9.7 E2E: tenant_management_test.js — CRUD operations (9 scenarios)
- [x] 9.8 E2E: tenant_switch_test.js — context switching (4 scenarios)

## 10. Documentation
- [x] 10.1 Developer guide: `docs/guides/tenant-context.md` (architecture, data model, RBAC, testing, migration)
- [x] 10.2 User-facing docs: `docs/features/tenant-management/ua/` + `en/` mirror

## 11. Quality Checks
- [x] 11.1 `phpstan analyse` — zero new errors at level 8 (4 pre-existing in CoderAgent/)
- [x] 11.2 `codecept run Unit` — 243 tests, 797 assertions, all pass
- [x] 11.3 `codecept run Functional` — tenant tests + existing scheduler/login tests pass
- [ ] 11.4 `make e2e` — requires running Docker stack (E2E tests written, pending stack verification)
