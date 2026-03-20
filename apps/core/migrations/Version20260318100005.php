<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318100005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default tenant, assign admin as owner, backfill tenant_id, add constraints';
    }

    public function up(Schema $schema): void
    {
        // 1. Insert default tenant
        $this->addSql(<<<'SQL'
            INSERT INTO tenants (id, name, slug, enabled, created_at, updated_at)
            VALUES (
                '00000000-0000-4000-a000-000000000001',
                'Default',
                'default',
                TRUE,
                now(),
                now()
            )
        SQL);

        // 2. Assign existing admin user to default tenant as owner
        $this->addSql(<<<'SQL'
            INSERT INTO user_tenant (user_id, tenant_id, role, joined_at)
            SELECT uuid, '00000000-0000-4000-a000-000000000001', 'owner', now()
            FROM users
            WHERE username = 'admin'
        SQL);

        // 3. Backfill tenant_id in agent_registry
        $this->addSql(<<<'SQL'
            UPDATE agent_registry SET tenant_id = '00000000-0000-4000-a000-000000000001' WHERE tenant_id IS NULL
        SQL);

        // 4. Backfill tenant_id in scheduled_jobs
        $this->addSql(<<<'SQL'
            UPDATE scheduled_jobs SET tenant_id = '00000000-0000-4000-a000-000000000001' WHERE tenant_id IS NULL
        SQL);

        // 5. Backfill tenant_id in audit tables
        $this->addSql(<<<'SQL'
            UPDATE agent_registry_audit SET tenant_id = '00000000-0000-4000-a000-000000000001' WHERE tenant_id IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE scheduler_job_logs SET tenant_id = '00000000-0000-4000-a000-000000000001' WHERE tenant_id IS NULL
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE a2a_message_audit SET tenant_id = '00000000-0000-4000-a000-000000000001' WHERE tenant_id IS NULL
        SQL);

        // 6. Make tenant_id NOT NULL and add FK on agent_registry
        $this->addSql('ALTER TABLE agent_registry ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE agent_registry
                ADD CONSTRAINT fk_agent_registry_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE agent_registry
                ADD CONSTRAINT uq_agent_registry_name_tenant UNIQUE (name, tenant_id)
        SQL);

        // 7. Make tenant_id NOT NULL and add FK on scheduled_jobs
        $this->addSql('ALTER TABLE scheduled_jobs ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE scheduled_jobs
                ADD CONSTRAINT fk_scheduled_jobs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE scheduled_jobs
                ADD CONSTRAINT uq_scheduled_jobs_agent_job_tenant UNIQUE (agent_name, job_name, tenant_id)
        SQL);

        // 8. Add FK on audit tables (nullable, SET NULL)
        $this->addSql(<<<'SQL'
            ALTER TABLE agent_registry_audit
                ADD CONSTRAINT fk_agent_registry_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE scheduler_job_logs
                ADD CONSTRAINT fk_scheduler_job_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE a2a_message_audit
                ADD CONSTRAINT fk_a2a_message_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys
        $this->addSql('ALTER TABLE a2a_message_audit DROP CONSTRAINT IF EXISTS fk_a2a_message_audit_tenant');
        $this->addSql('ALTER TABLE scheduler_job_logs DROP CONSTRAINT IF EXISTS fk_scheduler_job_logs_tenant');
        $this->addSql('ALTER TABLE agent_registry_audit DROP CONSTRAINT IF EXISTS fk_agent_registry_audit_tenant');
        $this->addSql('ALTER TABLE scheduled_jobs DROP CONSTRAINT IF EXISTS fk_scheduled_jobs_tenant');
        $this->addSql('ALTER TABLE agent_registry DROP CONSTRAINT IF EXISTS fk_agent_registry_tenant');

        // Revert NOT NULL
        $this->addSql('ALTER TABLE scheduled_jobs ALTER COLUMN tenant_id DROP NOT NULL');
        $this->addSql('ALTER TABLE agent_registry ALTER COLUMN tenant_id DROP NOT NULL');

        // Remove default tenant assignment
        $this->addSql("DELETE FROM user_tenant WHERE tenant_id = '00000000-0000-4000-a000-000000000001'");
        $this->addSql("DELETE FROM tenants WHERE id = '00000000-0000-4000-a000-000000000001'");
    }
}
