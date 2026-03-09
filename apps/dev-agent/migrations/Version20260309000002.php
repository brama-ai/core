<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260309000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dev_task_logs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dev_task_logs (
                id         BIGSERIAL PRIMARY KEY,
                task_id    INTEGER NOT NULL REFERENCES dev_tasks(id) ON DELETE CASCADE,
                agent_step VARCHAR(30) DEFAULT NULL,
                level      VARCHAR(10) NOT NULL DEFAULT 'info',
                message    TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dev_task_logs_task_id ON dev_task_logs (task_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dev_task_logs_task_created ON dev_task_logs (task_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS dev_task_logs');
    }
}
