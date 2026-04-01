<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4 migration: Register telegram-channel-agent in ChannelRegistry.
 *
 * Sets agent_name = 'telegram-channel-agent' for all channel_instances
 * where channel_type = 'telegram' and agent_name is NULL.
 *
 * This enables ChannelRegistry.resolveAgent('telegram') to return
 * 'telegram-channel-agent' after the agent is deployed.
 */
final class Version20260331000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Register telegram-channel-agent: set agent_name on channel_instances where channel_type=telegram (Phase 4: Telegram Channel Agent)';
    }

    public function up(Schema $schema): void
    {
        // Set agent_name for all existing telegram channel instances
        $this->addSql(<<<'SQL'
            UPDATE channel_instances
            SET agent_name = 'telegram-channel-agent'
            WHERE channel_type = 'telegram'
              AND agent_name IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Revert: clear agent_name for telegram instances that were set by this migration
        $this->addSql(<<<'SQL'
            UPDATE channel_instances
            SET agent_name = NULL
            WHERE channel_type = 'telegram'
              AND agent_name = 'telegram-channel-agent'
        SQL);
    }
}
