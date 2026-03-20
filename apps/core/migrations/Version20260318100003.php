<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318100003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_tenant pivot table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_tenant (
                user_id   UUID        NOT NULL,
                tenant_id UUID        NOT NULL,
                role      VARCHAR(32) NOT NULL DEFAULT 'member',
                joined_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (user_id, tenant_id),
                CONSTRAINT fk_user_tenant_user   FOREIGN KEY (user_id)   REFERENCES users (uuid)   ON DELETE CASCADE,
                CONSTRAINT fk_user_tenant_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_user_tenant_tenant ON user_tenant (tenant_id)');
        $this->addSql('CREATE INDEX idx_user_tenant_user ON user_tenant (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_tenant');
    }
}
