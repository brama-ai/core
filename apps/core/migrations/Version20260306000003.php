<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add http_status_code and error_code columns to a2a_message_audit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE a2a_message_audit ADD COLUMN http_status_code INT DEFAULT NULL');
        $this->addSql('ALTER TABLE a2a_message_audit ADD COLUMN error_code VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE a2a_message_audit DROP COLUMN error_code');
        $this->addSql('ALTER TABLE a2a_message_audit DROP COLUMN http_status_code');
    }
}
