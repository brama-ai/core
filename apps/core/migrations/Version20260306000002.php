<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename agent_invocation_audit → a2a_message_audit, column tool → skill';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_invocation_audit RENAME TO a2a_message_audit');
        $this->addSql('ALTER TABLE a2a_message_audit RENAME COLUMN tool TO skill');
        $this->addSql('ALTER INDEX idx_agent_invocation_audit_agent RENAME TO idx_a2a_message_audit_agent');
        $this->addSql('ALTER INDEX idx_agent_invocation_audit_tool RENAME TO idx_a2a_message_audit_skill');
        $this->addSql('ALTER INDEX idx_agent_invocation_audit_created_at RENAME TO idx_a2a_message_audit_created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_a2a_message_audit_created_at RENAME TO idx_agent_invocation_audit_created_at');
        $this->addSql('ALTER INDEX idx_a2a_message_audit_skill RENAME TO idx_agent_invocation_audit_tool');
        $this->addSql('ALTER INDEX idx_a2a_message_audit_agent RENAME TO idx_agent_invocation_audit_agent');
        $this->addSql('ALTER TABLE a2a_message_audit RENAME COLUMN skill TO tool');
        $this->addSql('ALTER TABLE a2a_message_audit RENAME TO agent_invocation_audit');
    }
}
