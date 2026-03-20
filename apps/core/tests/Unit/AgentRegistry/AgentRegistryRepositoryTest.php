<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentRegistry;

use App\AgentRegistry\AgentRegistryRepository;
use App\Tenant\Tenant;
use App\Tenant\TenantContext;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class AgentRegistryRepositoryTest extends Unit
{
    private Connection&MockObject $connection;
    private CacheItemPoolInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;
    private TenantContext $tenantContext;
    private AgentRegistryRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tenantContext = new TenantContext();
        $this->tenantContext->set(new Tenant('test-tenant-id', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable()));
        $this->repository = new AgentRegistryRepository($this->connection, $this->cache, $this->logger, $this->tenantContext);
    }

    public function testRegisterInsertsNewAgentAndInvalidatesCache(): void
    {
        $manifest = $this->validManifest('insert-agent');

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT id FROM agent_registry'))
            ->willReturn(false);

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO agent_registry'));

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('agent_registry.enabled');

        $this->repository->register($manifest);
    }

    public function testRegisterUpdatesExistingAgentAndInvalidatesCache(): void
    {
        $manifest = $this->validManifest('update-agent');

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('existing-id');

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('UPDATE agent_registry'));

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('agent_registry.enabled');

        $this->repository->register($manifest);
    }

    public function testEnableReturnsTrueAndInvalidatesCacheWhenUpdated(): void
    {
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('SET enabled = TRUE'))
            ->willReturn(1);

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('agent_registry.enabled');

        $this->assertTrue($this->repository->enable('enabled-agent', 'admin'));
    }

    public function testEnableReturnsFalseWhenAgentMissing(): void
    {
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $this->assertFalse($this->repository->enable('missing-agent', 'admin'));
    }

    public function testFindEnabledReturnsCachedValueOnHit(): void
    {
        $cachedRows = [['name' => 'cached-agent', 'enabled' => true]];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedRows);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('agent_registry.enabled')
            ->willReturn($cacheItem);

        $this->connection->expects($this->never())
            ->method('fetchAllAssociative');

        $result = $this->repository->findEnabled();

        $this->assertSame($cachedRows, $result);
    }

    public function testFindEnabledQueriesDatabaseAndSavesCacheOnMiss(): void
    {
        $rows = [['name' => 'db-agent', 'enabled' => true]];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($rows)
            ->willReturnSelf();
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(10)
            ->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('agent_registry.enabled')
            ->willReturn($cacheItem);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('WHERE enabled = TRUE'))
            ->willReturn($rows);

        $result = $this->repository->findEnabled();

        $this->assertSame($rows, $result);
    }

    public function testDeleteStaleMarketplaceAgentsDeletesEligibleAgents(): void
    {
        $staleAgents = [['name' => 'stale-agent-1', 'tenant_id' => 'test-tenant-id'], ['name' => 'stale-agent-2', 'tenant_id' => 'test-tenant-id']];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('installed_at IS NULL AND health_check_failures >= :threshold'),
                ['threshold' => 5],
            )
            ->willReturn($staleAgents);

        $this->connection->expects($this->exactly(4))
            ->method('executeStatement')
            ->willReturn(1);

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('agent_registry.enabled');

        $result = $this->repository->deleteStaleMarketplaceAgents(5);

        $this->assertSame(2, $result);
    }

    public function testDeleteStaleMarketplaceAgentsDoesNotDeleteInstalledAgents(): void
    {
        // Installed agents have installed_at IS NOT NULL, so they won't appear in the SELECT
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('installed_at IS NULL'))
            ->willReturn([]);

        $this->connection->expects($this->never())
            ->method('executeStatement');

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $result = $this->repository->deleteStaleMarketplaceAgents(5);

        $this->assertSame(0, $result);
    }

    public function testDeleteStaleMarketplaceAgentsPreservesAgentsBelowThreshold(): void
    {
        // Agents below threshold won't appear in the SELECT query
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('health_check_failures >= :threshold'))
            ->willReturn([]);

        $this->connection->expects($this->never())
            ->method('executeStatement');

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $result = $this->repository->deleteStaleMarketplaceAgents(5);

        $this->assertSame(0, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function validManifest(string $name): array
    {
        return [
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'test',
            'permissions' => ['admin'],
            'commands' => ['/test'],
            'events' => ['message.created'],
            'a2a_endpoint' => sprintf('http://%s/a2a', $name),
        ];
    }
}
