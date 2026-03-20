<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260318100002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Evolve admin_users into users table with UUID, email, and timestamps';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to admin_users before rename
        $this->addSql(<<<'SQL'
            ALTER TABLE admin_users
                ADD COLUMN uuid UUID DEFAULT gen_random_uuid(),
                ADD COLUMN email VARCHAR(180) DEFAULT NULL,
                ADD COLUMN created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                ADD COLUMN updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        SQL);

        // Backfill email from username for existing rows
        $this->addSql(<<<'SQL'
            UPDATE admin_users SET email = username || '@localhost' WHERE email IS NULL
        SQL);

        // Make email NOT NULL and unique
        $this->addSql('ALTER TABLE admin_users ALTER COLUMN email SET NOT NULL');
        $this->addSql('ALTER TABLE admin_users ADD CONSTRAINT admin_users_email_unique UNIQUE (email)');

        // Make uuid NOT NULL
        $this->addSql('ALTER TABLE admin_users ALTER COLUMN uuid SET NOT NULL');
        $this->addSql('ALTER TABLE admin_users ADD CONSTRAINT admin_users_uuid_unique UNIQUE (uuid)');

        // Update default roles to ROLE_SUPER_ADMIN for existing admin
        $this->addSql(<<<'SQL'
            UPDATE admin_users SET roles = '["ROLE_SUPER_ADMIN"]' WHERE username = 'admin'
        SQL);

        // Rename table
        $this->addSql('ALTER TABLE admin_users RENAME TO users');
        $this->addSql('ALTER TABLE users RENAME CONSTRAINT admin_users_username_unique TO users_username_unique');
        $this->addSql('ALTER TABLE users RENAME CONSTRAINT admin_users_email_unique TO users_email_unique');
        $this->addSql('ALTER TABLE users RENAME CONSTRAINT admin_users_uuid_unique TO users_uuid_unique');

        // Change default roles for new users
        $this->addSql(<<<'SQL'
            ALTER TABLE users ALTER COLUMN roles SET DEFAULT '["ROLE_USER"]'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users ALTER COLUMN roles SET DEFAULT '["ROLE_ADMIN"]'
        SQL);
        $this->addSql('ALTER TABLE users RENAME TO admin_users');
        $this->addSql('ALTER TABLE admin_users RENAME CONSTRAINT users_username_unique TO admin_users_username_unique');
        $this->addSql('ALTER TABLE admin_users RENAME CONSTRAINT users_email_unique TO admin_users_email_unique');
        $this->addSql('ALTER TABLE admin_users RENAME CONSTRAINT users_uuid_unique TO admin_users_uuid_unique');
        $this->addSql('ALTER TABLE admin_users DROP CONSTRAINT admin_users_email_unique');
        $this->addSql('ALTER TABLE admin_users DROP COLUMN email');
        $this->addSql('ALTER TABLE admin_users DROP COLUMN uuid');
        $this->addSql('ALTER TABLE admin_users DROP COLUMN created_at');
        $this->addSql('ALTER TABLE admin_users DROP COLUMN updated_at');
    }
}
