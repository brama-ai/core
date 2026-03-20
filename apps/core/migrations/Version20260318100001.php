<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenants table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tenants (
                id         UUID        NOT NULL DEFAULT gen_random_uuid(),
                name       VARCHAR(255) NOT NULL,
                slug       VARCHAR(128) NOT NULL,
                enabled    BOOLEAN     NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (id),
                CONSTRAINT tenants_slug_unique UNIQUE (slug)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenants');
    }
}
