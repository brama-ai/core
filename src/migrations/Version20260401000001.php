<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create agent_public_endpoints table for the Agent API Proxy feature.
 *
 * Stores public endpoint declarations from agent manifests.
 * Cascade-deletes when the owning agent_registry row is removed.
 */
final class Version20260401000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create agent_public_endpoints table (Agent API Proxy)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE agent_public_endpoints (
                id          SERIAL          PRIMARY KEY,
                agent_id    UUID            NOT NULL,
                path        VARCHAR(255)    NOT NULL,
                methods     JSONB           NOT NULL,
                description TEXT            NULL,
                created_at  TIMESTAMPTZ     NOT NULL DEFAULT now(),
                updated_at  TIMESTAMPTZ     NOT NULL DEFAULT now(),
                CONSTRAINT fk_agent_public_endpoints_agent
                    FOREIGN KEY (agent_id)
                    REFERENCES agent_registry (id)
                    ON DELETE CASCADE,
                CONSTRAINT uq_agent_public_endpoints_agent_path
                    UNIQUE (agent_id, path)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_agent_public_endpoints_agent ON agent_public_endpoints (agent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS agent_public_endpoints');
    }
}
