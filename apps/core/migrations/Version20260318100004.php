<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318100004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_id to agent_registry, scheduled_jobs, and audit tables';
    }

    public function up(Schema $schema): void
    {
        // agent_registry: add tenant_id (nullable initially for backfill)
        $this->addSql('ALTER TABLE agent_registry ADD COLUMN tenant_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE agent_registry ADD COLUMN shared BOOLEAN NOT NULL DEFAULT FALSE');

        // Drop old unique constraint on name, replace with (name, tenant_id)
        $this->addSql('ALTER TABLE agent_registry DROP CONSTRAINT IF EXISTS agent_registry_name_unique');
        // The original migration used inline UNIQUE on name column — try both forms
        $this->addSql('DROP INDEX IF EXISTS agent_registry_name_key');

        // scheduled_jobs: add tenant_id (nullable initially for backfill)
        $this->addSql('ALTER TABLE scheduled_jobs ADD COLUMN tenant_id UUID DEFAULT NULL');

        // Drop old unique constraint on (agent_name, job_name), replace with (agent_name, job_name, tenant_id)
        $this->addSql('ALTER TABLE scheduled_jobs DROP CONSTRAINT IF EXISTS uq_scheduled_jobs_agent_job');

        // Audit tables: add tenant_id (nullable, stays nullable)
        $this->addSql('ALTER TABLE agent_registry_audit ADD COLUMN tenant_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE scheduler_job_logs ADD COLUMN tenant_id UUID DEFAULT NULL');

        // a2a_message_audit if exists
        $this->addSql('ALTER TABLE a2a_message_audit ADD COLUMN IF NOT EXISTS tenant_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE a2a_message_audit DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE scheduler_job_logs DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE agent_registry_audit DROP COLUMN IF EXISTS tenant_id');

        // Restore old unique constraints
        $this->addSql('ALTER TABLE scheduled_jobs DROP CONSTRAINT IF EXISTS uq_scheduled_jobs_agent_job_tenant');
        $this->addSql('ALTER TABLE scheduled_jobs ADD CONSTRAINT uq_scheduled_jobs_agent_job UNIQUE (agent_name, job_name)');
        $this->addSql('ALTER TABLE scheduled_jobs DROP COLUMN IF EXISTS tenant_id');

        $this->addSql('ALTER TABLE agent_registry DROP CONSTRAINT IF EXISTS uq_agent_registry_name_tenant');
        $this->addSql('CREATE UNIQUE INDEX agent_registry_name_key ON agent_registry (name)');
        $this->addSql('ALTER TABLE agent_registry DROP COLUMN IF EXISTS shared');
        $this->addSql('ALTER TABLE agent_registry DROP COLUMN IF EXISTS tenant_id');
    }
}
