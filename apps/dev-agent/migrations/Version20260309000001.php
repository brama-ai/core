<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260309000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dev_tasks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dev_tasks (
                id               SERIAL PRIMARY KEY,
                title            VARCHAR(200) NOT NULL,
                description      TEXT NOT NULL DEFAULT '',
                refined_spec     TEXT DEFAULT NULL,
                status           VARCHAR(20) NOT NULL DEFAULT 'draft',
                branch           VARCHAR(100) DEFAULT NULL,
                pipeline_id      VARCHAR(30) DEFAULT NULL,
                pr_url           VARCHAR(500) DEFAULT NULL,
                pr_number        INTEGER DEFAULT NULL,
                pipeline_options JSONB NOT NULL DEFAULT '{}'::jsonb,
                chat_history     JSONB NOT NULL DEFAULT '[]'::jsonb,
                error_message    TEXT DEFAULT NULL,
                duration_seconds INTEGER DEFAULT NULL,
                created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
                started_at       TIMESTAMPTZ DEFAULT NULL,
                finished_at      TIMESTAMPTZ DEFAULT NULL
            )
            SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dev_tasks_status ON dev_tasks (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dev_tasks_created_at ON dev_tasks (created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS dev_tasks');
    }
}
