<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source column to scheduled_jobs to distinguish manifest vs admin-created jobs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE scheduled_jobs ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'manifest'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE scheduled_jobs DROP COLUMN IF EXISTS source');
    }
}
