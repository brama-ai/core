<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 3 migration: Rename tables and columns to channel-agnostic names.
 *
 * Renames:
 *   telegram_bots        -> channel_instances
 *   telegram_chats       -> channel_conversations
 *   bot_token_encrypted  -> credential_encrypted  (on channel_instances)
 *   bot_username         -> channel_username       (on channel_instances)
 *
 * All indexes and triggers are renamed accordingly.
 * FK constraint fk_telegram_chat_bot -> fk_channel_conversation_instance.
 *
 * Migration is fully reversible — down() restores all original names.
 */
final class Version20260331000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename telegram_bots -> channel_instances, telegram_chats -> channel_conversations; rename columns, indexes, triggers (Phase 3: Database Migration)';
    }

    public function up(Schema $schema): void
    {
        // 1. Rename tables
        $this->addSql('ALTER TABLE telegram_bots RENAME TO channel_instances');
        $this->addSql('ALTER TABLE telegram_chats RENAME TO channel_conversations');

        // 2. Rename columns on channel_instances
        $this->addSql('ALTER TABLE channel_instances RENAME COLUMN bot_token_encrypted TO credential_encrypted');
        $this->addSql('ALTER TABLE channel_instances RENAME COLUMN bot_username TO channel_username');

        // 3. Drop old triggers (they were created on old table names)
        $this->addSql('DROP TRIGGER IF EXISTS update_telegram_bots_updated_at ON channel_instances');
        $this->addSql('DROP TRIGGER IF EXISTS update_telegram_chats_updated_at ON channel_conversations');

        // 4. Recreate triggers with new names on renamed tables
        $this->addSql(<<<'SQL'
            CREATE TRIGGER update_channel_instances_updated_at
            BEFORE UPDATE ON channel_instances
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER update_channel_conversations_updated_at
            BEFORE UPDATE ON channel_conversations
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
        SQL);

        // 5. Rename indexes on channel_instances
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_bots_username RENAME TO idx_channel_instances_username');
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_bots_enabled RENAME TO idx_channel_instances_enabled');
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_bots_channel_type RENAME TO idx_channel_instances_channel_type');

        // 6. Rename indexes on channel_conversations
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_chats_bot_chat RENAME TO idx_channel_conversations_instance_chat');
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_chats_bot RENAME TO idx_channel_conversations_instance');
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_chats_activity RENAME TO idx_channel_conversations_activity');
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_chats_left RENAME TO idx_channel_conversations_left');
        $this->addSql('ALTER INDEX IF EXISTS idx_telegram_chats_channel_type RENAME TO idx_channel_conversations_channel_type');

        // 7. Rename FK constraint for clarity
        // PostgreSQL auto-updates FK references when tables are renamed, so the constraint still works.
        // We rename it to match the new table names.
        $this->addSql('ALTER TABLE channel_conversations RENAME CONSTRAINT fk_telegram_chat_bot TO fk_channel_conversation_instance');
    }

    public function down(Schema $schema): void
    {
        // Reverse FK constraint rename
        $this->addSql('ALTER TABLE channel_conversations RENAME CONSTRAINT fk_channel_conversation_instance TO fk_telegram_chat_bot');

        // Reverse index renames on channel_conversations
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_conversations_channel_type RENAME TO idx_telegram_chats_channel_type');
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_conversations_left RENAME TO idx_telegram_chats_left');
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_conversations_activity RENAME TO idx_telegram_chats_activity');
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_conversations_instance RENAME TO idx_telegram_chats_bot');
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_conversations_instance_chat RENAME TO idx_telegram_chats_bot_chat');

        // Reverse index renames on channel_instances
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_instances_channel_type RENAME TO idx_telegram_bots_channel_type');
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_instances_enabled RENAME TO idx_telegram_bots_enabled');
        $this->addSql('ALTER INDEX IF EXISTS idx_channel_instances_username RENAME TO idx_telegram_bots_username');

        // Drop new triggers
        $this->addSql('DROP TRIGGER IF EXISTS update_channel_conversations_updated_at ON channel_conversations');
        $this->addSql('DROP TRIGGER IF EXISTS update_channel_instances_updated_at ON channel_instances');

        // Recreate old triggers on still-renamed tables (will be renamed back below)
        $this->addSql(<<<'SQL'
            CREATE TRIGGER update_telegram_chats_updated_at
            BEFORE UPDATE ON channel_conversations
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TRIGGER update_telegram_bots_updated_at
            BEFORE UPDATE ON channel_instances
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
        SQL);

        // Reverse column renames
        $this->addSql('ALTER TABLE channel_instances RENAME COLUMN credential_encrypted TO bot_token_encrypted');
        $this->addSql('ALTER TABLE channel_instances RENAME COLUMN channel_username TO bot_username');

        // Reverse table renames
        $this->addSql('ALTER TABLE channel_conversations RENAME TO telegram_chats');
        $this->addSql('ALTER TABLE channel_instances RENAME TO telegram_bots');
    }
}
