<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class TokenBucketRateLimiter
{
    private const TABLE_NAME = 'rate_limiter_buckets';

    public function __construct(
        private readonly Connection $connection,
        private readonly int $maxTokens = 60,
        private readonly int $refillRate = 60, // tokens per minute
    ) {
    }

    /**
     * Try to consume tokens from the bucket.
     * Returns true if tokens were available, false if rate limited.
     */
    public function consume(string $bucketKey, int $tokens = 1): bool
    {
        $now = time();
        
        // Get or create bucket
        $bucket = $this->getBucket($bucketKey, $now);
        
        // Calculate tokens to add based on time elapsed
        $tokensToAdd = (int) floor(($now - $bucket['last_refill']) * $this->refillRate / 60);
        $newTokenCount = min($this->maxTokens, $bucket['tokens'] + $tokensToAdd);
        
        // Check if we have enough tokens
        if ($newTokenCount < $tokens) {
            // Update bucket state even if we can't consume
            $this->updateBucket($bucketKey, $newTokenCount, $now);
            return false;
        }
        
        // Consume tokens
        $remainingTokens = $newTokenCount - $tokens;
        $this->updateBucket($bucketKey, $remainingTokens, $now);
        
        return true;
    }

    /**
     * Get the current token count for a bucket (for monitoring).
     */
    public function getTokenCount(string $bucketKey): int
    {
        $now = time();
        $bucket = $this->getBucket($bucketKey, $now);
        
        $tokensToAdd = (int) floor(($now - $bucket['last_refill']) * $this->refillRate / 60);
        return min($this->maxTokens, $bucket['tokens'] + $tokensToAdd);
    }

    /**
     * Get time until next token is available (in seconds).
     */
    public function getTimeUntilNextToken(string $bucketKey): int
    {
        $currentTokens = $this->getTokenCount($bucketKey);
        
        if ($currentTokens > 0) {
            return 0;
        }
        
        // Calculate time needed to refill one token
        return (int) ceil(60 / $this->refillRate);
    }

    /**
     * @return array{tokens: int, last_refill: int}
     */
    private function getBucket(string $bucketKey, int $now): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT tokens, last_refill FROM ' . self::TABLE_NAME . ' WHERE bucket_key = :key',
            ['key' => $bucketKey]
        );

        if (false === $row) {
            // Create new bucket with full tokens
            $this->connection->executeStatement(
                'INSERT INTO ' . self::TABLE_NAME . ' (bucket_key, tokens, last_refill, created_at) VALUES (:key, :tokens, :now, :now)',
                [
                    'key' => $bucketKey,
                    'tokens' => $this->maxTokens,
                    'now' => $now,
                ]
            );
            
            return ['tokens' => $this->maxTokens, 'last_refill' => $now];
        }

        return [
            'tokens' => (int) $row['tokens'],
            'last_refill' => (int) $row['last_refill'],
        ];
    }

    private function updateBucket(string $bucketKey, int $tokens, int $now): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . self::TABLE_NAME . ' SET tokens = :tokens, last_refill = :now WHERE bucket_key = :key',
            [
                'key' => $bucketKey,
                'tokens' => $tokens,
                'now' => $now,
            ]
        );
    }
}