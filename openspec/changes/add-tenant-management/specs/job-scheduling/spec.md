## MODIFIED Requirements

### Requirement: Scheduled Jobs Database Table
The platform SHALL persist scheduled jobs in a `scheduled_jobs` PostgreSQL table with columns for tenant ID, agent name, job name, skill ID, payload, cron expression, next/last run timestamps, retry state, and enabled flag. The table SHALL have a unique constraint on `(agent_name, job_name, tenant_id)` and an index on `(enabled, next_run_at)`.

#### Scenario: Migration adds tenant_id to scheduled_jobs
- **WHEN** the tenant management migration is executed
- **THEN** the `scheduled_jobs` table has a `tenant_id` UUID column with a foreign key to `tenants.id`

#### Scenario: Duplicate job registration is idempotent within tenant
- **WHEN** a job with the same `(agent_name, job_name, tenant_id)` is registered twice
- **THEN** the second registration updates the existing row instead of creating a duplicate

#### Scenario: Same job name allowed across tenants
- **WHEN** Tenant A and Tenant B both register a job named "daily-digest" for agent "news-digest"
- **THEN** both jobs exist as separate rows with different `tenant_id` values

### Requirement: Manifest-Driven Job Registration
Agents SHALL declare scheduled jobs in their `manifest.json` under a `scheduled_jobs` array. The platform SHALL register these jobs during agent install within the installing tenant's context and remove them during uninstall.

#### Scenario: Agent install registers scheduled jobs for tenant
- **WHEN** an agent with `scheduled_jobs` in its manifest is installed by Tenant A
- **THEN** each declared job is inserted into the `scheduled_jobs` table with `tenant_id` set to Tenant A's ID, `enabled = TRUE`, and `next_run_at` computed from the cron expression

#### Scenario: Agent uninstall removes tenant-scoped scheduled jobs
- **WHEN** an agent is uninstalled from Tenant A
- **THEN** all rows in `scheduled_jobs` with that agent's name and Tenant A's `tenant_id` are deleted
- **AND** jobs for the same agent in other tenants are not affected

#### Scenario: Agent disable pauses scheduled jobs
- **WHEN** an agent is disabled
- **THEN** all its scheduled jobs are set to `enabled = FALSE`

#### Scenario: Agent enable resumes scheduled jobs
- **WHEN** a previously disabled agent is enabled
- **THEN** all its scheduled jobs are set to `enabled = TRUE` and `next_run_at` is recomputed

### Requirement: Scheduler Admin Page
The platform SHALL provide an admin page at `/admin/scheduler` showing scheduled jobs scoped to the current tenant context, with controls for manual triggering and enabling/disabling.

#### Scenario: Admin views scheduler dashboard
- **WHEN** an authenticated admin navigates to `/admin/scheduler`
- **THEN** a table is displayed showing only jobs belonging to the admin's active tenant

#### Scenario: Admin triggers job manually
- **WHEN** an admin clicks "Run Now" for a job
- **THEN** the job's `next_run_at` is set to `now()` so it executes on the next scheduler tick

#### Scenario: Admin toggles job enabled state
- **WHEN** an admin toggles a job's enabled state
- **THEN** the job's `enabled` flag is updated and the change takes effect on the next scheduler tick
