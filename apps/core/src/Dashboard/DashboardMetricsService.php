<?php

declare(strict_types=1);

namespace App\Dashboard;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;

final class DashboardMetricsService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_KEY_PREFIX = 'dashboard_metrics.';

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'a2a' => $this->getA2AStats(),
            'agents' => $this->getAgentActivity(),
            'scheduler' => $this->getSchedulerStats(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getA2AStats(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX.'a2a_stats';
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            /** @var array<string, mixed> $cached */
            $cached = $item->get();

            return $cached;
        }

        // Calls in last 24 hours
        $calls24h = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM a2a_message_audit WHERE created_at >= now() - INTERVAL \'24 hours\'',
        );

        // Calls in last 7 days
        $calls7d = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM a2a_message_audit WHERE created_at >= now() - INTERVAL \'7 days\'',
        );

        // Average response time (duration_ms) in last 24 hours
        $avgResponseTime = $this->connection->fetchOne(
            'SELECT ROUND(AVG(duration_ms))::INT FROM a2a_message_audit WHERE created_at >= now() - INTERVAL \'24 hours\' AND duration_ms IS NOT NULL',
        );

        // Success rate in last 24 hours
        $successRate = $this->connection->fetchOne(
            <<<'SQL'
            SELECT ROUND(100.0 * COUNT(*) FILTER (WHERE status = 'completed') / NULLIF(COUNT(*), 0), 1)
            FROM a2a_message_audit
            WHERE created_at >= now() - INTERVAL '24 hours'
            SQL,
        );

        // Top 5 skills in last 24 hours
        $topSkills = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT skill, COUNT(*) as count
            FROM a2a_message_audit
            WHERE created_at >= now() - INTERVAL '24 hours' AND skill IS NOT NULL
            GROUP BY skill
            ORDER BY count DESC
            LIMIT 5
            SQL,
        );

        $stats = [
            'calls_24h' => $calls24h,
            'calls_7d' => $calls7d,
            'avg_response_time_ms' => is_numeric($avgResponseTime) ? (int) $avgResponseTime : null,
            'success_rate' => is_numeric($successRate) ? (float) $successRate : null,
            'top_skills' => $topSkills,
        ];

        $item->set($stats);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function getAgentActivity(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX.'agent_activity';
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            /** @var array<string, mixed> $cached */
            $cached = $item->get();

            return $cached;
        }

        // Active agents in last 24 hours (agents that have made A2A calls)
        $activeAgents = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT agent, COUNT(*) as call_count
            FROM a2a_message_audit
            WHERE created_at >= now() - INTERVAL '24 hours'
            GROUP BY agent
            ORDER BY call_count DESC
            SQL,
        );

        $stats = [
            'active_agents_24h' => count($activeAgents),
            'agents' => $activeAgents,
        ];

        $item->set($stats);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSchedulerStats(): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX.'scheduler_stats';
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            /** @var array<string, mixed> $cached */
            $cached = $item->get();

            return $cached;
        }

        // Active and paused jobs count
        $jobStats = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE enabled = TRUE) AS active_jobs,
                COUNT(*) FILTER (WHERE enabled = FALSE) AS paused_jobs
            FROM scheduled_jobs
            SQL,
        );

        // Last 5 job executions from scheduler_job_logs
        $recentExecutions = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                jl.id,
                jl.job_id,
                jl.agent_name,
                jl.skill_id,
                jl.job_name,
                jl.status,
                jl.started_at,
                jl.finished_at,
                sj.cron_expression
            FROM scheduler_job_logs jl
            LEFT JOIN scheduled_jobs sj ON sj.id = jl.job_id
            ORDER BY jl.created_at DESC
            LIMIT 5
            SQL,
        );

        $stats = [
            'active_jobs' => (int) ($jobStats['active_jobs'] ?? 0),
            'paused_jobs' => (int) ($jobStats['paused_jobs'] ?? 0),
            'recent_executions' => $recentExecutions,
        ];

        $item->set($stats);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $stats;
    }
}
