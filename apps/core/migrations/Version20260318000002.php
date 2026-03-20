<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create coder_tasks, coder_task_logs, and coder_workers tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE coder_tasks (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                template_type VARCHAR(64) DEFAULT NULL,
                priority INT NOT NULL DEFAULT 1,
                status VARCHAR(32) NOT NULL,
                current_stage VARCHAR(32) DEFAULT NULL,
                stage_progress JSONB NOT NULL DEFAULT '[]'::jsonb,
                pipeline_config JSONB NOT NULL DEFAULT '{}'::jsonb,
                compat_state JSONB DEFAULT NULL,
                builder_task_path VARCHAR(512) DEFAULT NULL,
                summary_path VARCHAR(512) DEFAULT NULL,
                artifacts_path VARCHAR(512) DEFAULT NULL,
                branch_name VARCHAR(255) DEFAULT NULL,
                worktree_path VARCHAR(512) DEFAULT NULL,
                worker_id VARCHAR(64) DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                retry_count INT NOT NULL DEFAULT 0,
                started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT now(),
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT now(),
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_coder_tasks_status_priority ON coder_tasks (status, priority DESC, created_at ASC)');
        $this->addSql('CREATE INDEX idx_coder_tasks_updated_at ON coder_tasks (updated_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE coder_task_logs (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                task_id UUID NOT NULL,
                stage VARCHAR(32) DEFAULT NULL,
                level VARCHAR(16) NOT NULL,
                source VARCHAR(32) NOT NULL,
                message TEXT NOT NULL,
                metadata JSONB DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT now(),
                PRIMARY KEY(id),
                CONSTRAINT fk_coder_task_logs_task FOREIGN KEY (task_id) REFERENCES coder_tasks (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_coder_task_logs_task_created ON coder_task_logs (task_id, created_at ASC)');
        $this->addSql('CREATE INDEX idx_coder_task_logs_created ON coder_task_logs (created_at ASC)');

        $this->addSql(<<<'SQL'
            CREATE TABLE coder_workers (
                id VARCHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL,
                current_task_id UUID DEFAULT NULL,
                pid INT DEFAULT NULL,
                worktree_path VARCHAR(512) DEFAULT NULL,
                started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT now(),
                last_heartbeat_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT now(),
                PRIMARY KEY(id),
                CONSTRAINT fk_coder_workers_task FOREIGN KEY (current_task_id) REFERENCES coder_tasks (id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_coder_workers_heartbeat ON coder_workers (last_heartbeat_at)');
        $this->addSql('CREATE INDEX idx_coder_workers_status ON coder_workers (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS coder_task_logs');
        $this->addSql('DROP TABLE IF EXISTS coder_workers');
        $this->addSql('DROP TABLE IF EXISTS coder_tasks');
    }
}
