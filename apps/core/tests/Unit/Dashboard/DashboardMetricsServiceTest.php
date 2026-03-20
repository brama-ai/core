<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\DashboardMetricsService;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class DashboardMetricsServiceTest extends Unit
{
    private Connection&MockObject $connection;
    private CacheItemPoolInterface&MockObject $cache;
    private DashboardMetricsService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new DashboardMetricsService($this->connection, $this->cache);
    }

    public function testGetMetricsReturnsAllThreeSections(): void
    {
        // Set up cache misses for all three sections
        $a2aItem = $this->createCacheMissItem();
        $agentItem = $this->createCacheMissItem();
        $schedulerItem = $this->createCacheMissItem();

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });

        $this->cache->expects($this->exactly(3))
            ->method('save')
            ->willReturn(true);

        // Mock DB calls for a2a stats (fetchOne x3, fetchAllAssociative x1)
        $this->connection->method('fetchOne')
            ->willReturn(0);
        $this->connection->method('fetchAssociative')
            ->willReturn(['active_jobs' => 0, 'paused_jobs' => 0]);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $metrics = $this->service->getMetrics();

        $this->assertArrayHasKey('a2a', $metrics);
        $this->assertArrayHasKey('agents', $metrics);
        $this->assertArrayHasKey('scheduler', $metrics);
    }

    public function testGetMetricsUsesCacheWhenAvailable(): void
    {
        $cachedA2A = [
            'calls_24h' => 100,
            'calls_7d' => 500,
            'avg_response_time_ms' => 250,
            'success_rate' => 95.5,
            'top_skills' => [['skill' => 'chat.send', 'count' => 50]],
        ];
        $cachedAgents = [
            'active_agents_24h' => 3,
            'agents' => [['agent' => 'bot-a', 'call_count' => 30]],
        ];
        $cachedScheduler = [
            'active_jobs' => 5,
            'paused_jobs' => 2,
            'recent_executions' => [],
        ];

        $a2aItem = $this->createCacheHitItem($cachedA2A);
        $agentItem = $this->createCacheHitItem($cachedAgents);
        $schedulerItem = $this->createCacheHitItem($cachedScheduler);

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });

        // No DB queries should be made when cache hits
        $this->connection->expects($this->never())->method('fetchOne');
        $this->connection->expects($this->never())->method('fetchAssociative');
        $this->connection->expects($this->never())->method('fetchAllAssociative');

        $metrics = $this->service->getMetrics();

        $this->assertSame($cachedA2A, $metrics['a2a']);
        $this->assertSame($cachedAgents, $metrics['agents']);
        $this->assertSame($cachedScheduler, $metrics['scheduler']);
    }

    public function testA2AStatsQueriesCorrectData(): void
    {
        $a2aItem = $this->createCacheMissItem();
        $agentItem = $this->createCacheHitItem(['active_agents_24h' => 0, 'agents' => []]);
        $schedulerItem = $this->createCacheHitItem(['active_jobs' => 0, 'paused_jobs' => 0, 'recent_executions' => []]);

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });

        $this->cache->method('save')->willReturn(true);

        // fetchOne is called 4 times: calls_24h, calls_7d, avg_response_time, success_rate
        $this->connection->expects($this->exactly(4))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(42, 200, 150, 98.5);

        $topSkills = [
            ['skill' => 'chat.send', 'count' => 20],
            ['skill' => 'data.query', 'count' => 10],
        ];
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($topSkills);

        $metrics = $this->service->getMetrics();
        $a2a = $metrics['a2a'];

        $this->assertSame(42, $a2a['calls_24h']);
        $this->assertSame(200, $a2a['calls_7d']);
        $this->assertSame(150, $a2a['avg_response_time_ms']);
        $this->assertSame(98.5, $a2a['success_rate']);
        $this->assertSame($topSkills, $a2a['top_skills']);
    }

    public function testA2AStatsHandlesNullNumericValues(): void
    {
        $a2aItem = $this->createCacheMissItem();
        $agentItem = $this->createCacheHitItem(['active_agents_24h' => 0, 'agents' => []]);
        $schedulerItem = $this->createCacheHitItem(['active_jobs' => 0, 'paused_jobs' => 0, 'recent_executions' => []]);

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });
        $this->cache->method('save')->willReturn(true);

        // avg_response_time and success_rate return null (no data)
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(0, 0, null, null);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $metrics = $this->service->getMetrics();
        $a2a = $metrics['a2a'];

        $this->assertSame(0, $a2a['calls_24h']);
        $this->assertNull($a2a['avg_response_time_ms']);
        $this->assertNull($a2a['success_rate']);
    }

    public function testAgentActivityStructure(): void
    {
        $a2aItem = $this->createCacheHitItem([
            'calls_24h' => 0, 'calls_7d' => 0, 'avg_response_time_ms' => null,
            'success_rate' => null, 'top_skills' => [],
        ]);
        $agentItem = $this->createCacheMissItem();
        $schedulerItem = $this->createCacheHitItem(['active_jobs' => 0, 'paused_jobs' => 0, 'recent_executions' => []]);

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });
        $this->cache->method('save')->willReturn(true);

        $activeAgents = [
            ['agent' => 'bot-alpha', 'call_count' => 55],
            ['agent' => 'bot-beta', 'call_count' => 30],
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($activeAgents);

        $metrics = $this->service->getMetrics();
        $agents = $metrics['agents'];

        $this->assertArrayHasKey('active_agents_24h', $agents);
        $this->assertArrayHasKey('agents', $agents);
        $this->assertSame(2, $agents['active_agents_24h']);
        $this->assertSame($activeAgents, $agents['agents']);
    }

    public function testSchedulerStatsStructure(): void
    {
        $a2aItem = $this->createCacheHitItem([
            'calls_24h' => 0, 'calls_7d' => 0, 'avg_response_time_ms' => null,
            'success_rate' => null, 'top_skills' => [],
        ]);
        $agentItem = $this->createCacheHitItem(['active_agents_24h' => 0, 'agents' => []]);
        $schedulerItem = $this->createCacheMissItem();

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });
        $this->cache->method('save')->willReturn(true);

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['active_jobs' => '8', 'paused_jobs' => '3']);

        $recentExecutions = [
            [
                'id' => 'log-1',
                'job_id' => 'job-1',
                'agent_name' => 'bot-a',
                'skill_id' => 'sync.run',
                'job_name' => 'daily-sync',
                'status' => 'completed',
                'started_at' => '2026-03-20 09:00:00',
                'finished_at' => '2026-03-20 09:00:05',
                'cron_expression' => '0 9 * * *',
            ],
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($recentExecutions);

        $metrics = $this->service->getMetrics();
        $scheduler = $metrics['scheduler'];

        $this->assertArrayHasKey('active_jobs', $scheduler);
        $this->assertArrayHasKey('paused_jobs', $scheduler);
        $this->assertArrayHasKey('recent_executions', $scheduler);
        $this->assertSame(8, $scheduler['active_jobs']);
        $this->assertSame(3, $scheduler['paused_jobs']);
        $this->assertSame($recentExecutions, $scheduler['recent_executions']);
    }

    public function testSchedulerStatsHandlesNullJobStats(): void
    {
        $a2aItem = $this->createCacheHitItem([
            'calls_24h' => 0, 'calls_7d' => 0, 'avg_response_time_ms' => null,
            'success_rate' => null, 'top_skills' => [],
        ]);
        $agentItem = $this->createCacheHitItem(['active_agents_24h' => 0, 'agents' => []]);
        $schedulerItem = $this->createCacheMissItem();

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });
        $this->cache->method('save')->willReturn(true);

        // fetchAssociative returns false when no rows
        $this->connection->method('fetchAssociative')
            ->willReturn(false);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $metrics = $this->service->getMetrics();
        $scheduler = $metrics['scheduler'];

        $this->assertSame(0, $scheduler['active_jobs']);
        $this->assertSame(0, $scheduler['paused_jobs']);
        $this->assertSame([], $scheduler['recent_executions']);
    }

    public function testCacheIsSetAfterFreshQuery(): void
    {
        $a2aItem = $this->createCacheMissItem();
        $agentItem = $this->createCacheHitItem(['active_agents_24h' => 0, 'agents' => []]);
        $schedulerItem = $this->createCacheHitItem(['active_jobs' => 0, 'paused_jobs' => 0, 'recent_executions' => []]);

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });

        $this->connection->method('fetchOne')->willReturn(0);
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        // Verify that set, expiresAfter, and save are called for the a2a cache item
        $a2aItem->expects($this->once())
            ->method('set')
            ->with($this->callback(fn (mixed $v): bool => \is_array($v)));

        $a2aItem->expects($this->once())
            ->method('expiresAfter')
            ->with(300); // CACHE_TTL = 300

        $this->cache->expects($this->once())
            ->method('save')
            ->with($a2aItem)
            ->willReturn(true);

        $this->service->getMetrics();
    }

    public function testEachSectionCachesIndependently(): void
    {
        // a2a is cached, but agents is not — agents should query DB
        $cachedA2A = [
            'calls_24h' => 10, 'calls_7d' => 50, 'avg_response_time_ms' => 100,
            'success_rate' => 99.0, 'top_skills' => [],
        ];
        $a2aItem = $this->createCacheHitItem($cachedA2A);
        $agentItem = $this->createCacheMissItem();
        $schedulerItem = $this->createCacheHitItem(['active_jobs' => 1, 'paused_jobs' => 0, 'recent_executions' => []]);

        $this->cache->method('getItem')
            ->willReturnCallback(fn (string $key): CacheItemInterface => match ($key) {
                'dashboard_metrics.a2a_stats' => $a2aItem,
                'dashboard_metrics.agent_activity' => $agentItem,
                'dashboard_metrics.scheduler_stats' => $schedulerItem,
                default => throw new \LogicException('Unexpected cache key: '.$key),
            });
        $this->cache->method('save')->willReturn(true);

        // Only agent activity should query DB (fetchAllAssociative once)
        $this->connection->expects($this->never())->method('fetchOne');
        $this->connection->expects($this->never())->method('fetchAssociative');
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $metrics = $this->service->getMetrics();

        $this->assertSame($cachedA2A, $metrics['a2a']);
        $this->assertSame(['active_agents_24h' => 0, 'agents' => []], $metrics['agents']);
    }

    /**
     * Creates a mock CacheItemInterface that simulates a cache miss.
     */
    private function createCacheMissItem(): CacheItemInterface&MockObject
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();

        return $item;
    }

    /**
     * Creates a mock CacheItemInterface that simulates a cache hit with given data.
     */
    private function createCacheHitItem(mixed $data): CacheItemInterface&MockObject
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($data);

        return $item;
    }
}
