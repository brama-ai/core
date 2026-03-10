<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create scheduled_jobs table for central cron scheduler';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE scheduled_jobs (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                agent_name VARCHAR(64) NOT NULL,
                job_name VARCHAR(128) NOT NULL,
                skill_id VARCHAR(128) NOT NULL,
                payload JSONB NOT NULL DEFAULT '{}',
                cron_expression VARCHAR(64) DEFAULT NULL,
                next_run_at TIMESTAMPTZ NOT NULL,
                last_run_at TIMESTAMPTZ DEFAULT NULL,
                last_status VARCHAR(32) DEFAULT NULL,
                retry_count INTEGER NOT NULL DEFAULT 0,
                max_retries INTEGER NOT NULL DEFAULT 3,
                retry_delay_seconds INTEGER NOT NULL DEFAULT 60,
                enabled BOOLEAN NOT NULL DEFAULT TRUE,
                timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT uq_scheduled_jobs_agent_job UNIQUE (agent_name, job_name)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_scheduled_jobs_enabled_next_run ON scheduled_jobs (enabled, next_run_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduled_jobs');
    }
}
