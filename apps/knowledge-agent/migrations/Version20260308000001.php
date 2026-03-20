<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rate_limiter_buckets table for token bucket rate limiting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_limiter_buckets (
                bucket_key   VARCHAR(128) PRIMARY KEY,
                tokens       INTEGER NOT NULL DEFAULT 0,
                last_refill  INTEGER NOT NULL,
                created_at   INTEGER NOT NULL
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_rate_limiter_buckets_last_refill ON rate_limiter_buckets (last_refill)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS rate_limiter_buckets');
    }
}