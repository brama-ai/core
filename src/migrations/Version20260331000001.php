<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2 migration: Add channel_type and agent_name columns to telegram_bots,
 * and channel_type column to telegram_chats.
 *
 * This is the first step toward making the schema channel-agnostic.
 * Existing rows are backfilled with 'telegram' as the default channel_type.
 */
final class Version20260331000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel_type + agent_name to telegram_bots; add channel_type to telegram_chats (Phase 2: Core Services)';
    }

    public function up(Schema $schema): void
    {
        // Add channel_type column to telegram_bots (default 'telegram')
        $this->addSql(<<<'SQL'
            ALTER TABLE telegram_bots
                ADD COLUMN IF NOT EXISTS channel_type VARCHAR(50) DEFAULT 'telegram'
        SQL);

        // Add agent_name column to telegram_bots (nullable — filled when channel agent is registered)
        $this->addSql(<<<'SQL'
            ALTER TABLE telegram_bots
                ADD COLUMN IF NOT EXISTS agent_name VARCHAR(255) DEFAULT NULL
        SQL);

        // Backfill existing rows with 'telegram' channel_type
        $this->addSql(<<<'SQL'
            UPDATE telegram_bots SET channel_type = 'telegram' WHERE channel_type IS NULL
        SQL);

        // Add channel_type column to telegram_chats (default 'telegram')
        $this->addSql(<<<'SQL'
            ALTER TABLE telegram_chats
                ADD COLUMN IF NOT EXISTS channel_type VARCHAR(50) DEFAULT 'telegram'
        SQL);

        // Backfill existing rows with 'telegram' channel_type
        $this->addSql(<<<'SQL'
            UPDATE telegram_chats SET channel_type = 'telegram' WHERE channel_type IS NULL
        SQL);

        // Add index on channel_type for efficient ChannelRegistry lookups
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_telegram_bots_channel_type ON telegram_bots (channel_type)
        SQL);

        // Add index on channel_type for ConversationTracker lookups
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_telegram_chats_channel_type ON telegram_chats (channel_type)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_telegram_chats_channel_type');
        $this->addSql('DROP INDEX IF EXISTS idx_telegram_bots_channel_type');
        $this->addSql('ALTER TABLE telegram_chats DROP COLUMN IF EXISTS channel_type');
        $this->addSql('ALTER TABLE telegram_bots DROP COLUMN IF EXISTS agent_name');
        $this->addSql('ALTER TABLE telegram_bots DROP COLUMN IF EXISTS channel_type');
    }
}
