<?php

declare(strict_types=1);

namespace App\Channel;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Stores channel_type → agent_name mapping.
 * Reads from channel_instances table (channel_type + agent_name columns).
 * In-memory cache with TTL.
 */
final class ChannelRegistry
{
    /** @var array<string, string> channel_type => agent_name */
    private array $cache = [];
    private ?\DateTimeImmutable $cacheExpiry = null;
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve the agent name responsible for the given channel type.
     *
     * @throws \RuntimeException when no agent is registered for the channel type
     */
    public function resolveAgent(string $channelType): string
    {
        $this->ensureCacheLoaded();

        $agentName = $this->cache[$channelType] ?? null;

        if (null === $agentName) {
            throw new \RuntimeException(sprintf('No agent registered for channel type "%s"', $channelType));
        }

        return $agentName;
    }

    /**
     * Register (or update) a channel_type → agent_name mapping in memory.
     * Does not persist to DB — use migrations or admin UI for persistence.
     */
    public function register(string $channelType, string $agentName): void
    {
        $this->cache[$channelType] = $agentName;

        $this->logger->info('Channel registered in registry', [
            'channel_type' => $channelType,
            'agent_name' => $agentName,
        ]);
    }

    /**
     * @return array<string, string> channel_type => agent_name
     */
    public function listChannels(): array
    {
        $this->ensureCacheLoaded();

        return $this->cache;
    }

    /**
     * Force cache invalidation on next access.
     */
    public function invalidate(): void
    {
        $this->cache = [];
        $this->cacheExpiry = null;
    }

    private function ensureCacheLoaded(): void
    {
        if ($this->isCacheValid()) {
            return;
        }

        $this->loadFromDatabase();
    }

    private function isCacheValid(): bool
    {
        return [] !== $this->cache
            && null !== $this->cacheExpiry
            && $this->cacheExpiry > new \DateTimeImmutable();
    }

    private function loadFromDatabase(): void
    {
        $sql = <<<'SQL'
            SELECT DISTINCT channel_type, agent_name
            FROM channel_instances
            WHERE enabled = true
              AND channel_type IS NOT NULL
              AND agent_name IS NOT NULL
            ORDER BY channel_type
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql);

        $this->cache = [];
        foreach ($rows as $row) {
            $channelType = (string) $row['channel_type'];
            $agentName = (string) $row['agent_name'];
            // Last row wins if multiple bots share the same channel_type
            $this->cache[$channelType] = $agentName;
        }

        $this->cacheExpiry = (new \DateTimeImmutable())->modify('+'.self::CACHE_TTL_SECONDS.' seconds');

        $this->logger->debug('ChannelRegistry cache refreshed', [
            'channel_count' => count($this->cache),
            'channels' => array_keys($this->cache),
        ]);
    }
}
