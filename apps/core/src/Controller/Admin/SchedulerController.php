<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\AgentRegistry\AgentRegistryInterface;
use App\Scheduler\ScheduledJobRepositoryInterface;
use App\Security\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SchedulerController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $repository,
        private readonly AgentRegistryInterface $agentRegistry,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/admin/scheduler', name: 'admin_scheduler')]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $jobs = $this->repository->findAll();
        $allAgents = $this->agentRegistry->findAll();

        $agentNames = [];
        $agentSkillMap = [];

        foreach ($allAgents as $agent) {
            $name = (string) $agent['name'];
            $agentNames[] = $name;

            $manifest = is_string($agent['manifest'] ?? null)
                ? (array) json_decode($agent['manifest'], true)
                : (array) ($agent['manifest'] ?? []);

            $skillIds = [];
            foreach ((array) ($manifest['skills'] ?? []) as $skill) {
                if (is_array($skill) && isset($skill['id'])) {
                    $skillIds[] = (string) $skill['id'];
                }
            }
            $agentSkillMap[$name] = $skillIds;
        }

        // Compute stale status for each job
        foreach ($jobs as &$job) {
            $agentName = (string) $job['agent_name'];
            $skillId = (string) $job['skill_id'];

            if (!isset($agentSkillMap[$agentName])) {
                $job['_stale'] = 'agent_missing';
                $job['_stale_reason'] = sprintf('Агент "%s" не знайдено в реєстрі', $agentName);
            } elseif (!\in_array($skillId, $agentSkillMap[$agentName], true)) {
                $job['_stale'] = 'skill_missing';
                $job['_stale_reason'] = sprintf('Скіл "%s" не знайдено в маніфесті агента "%s"', $skillId, $agentName);
            } else {
                $job['_stale'] = null;
                $job['_stale_reason'] = null;
            }
        }
        unset($job);

        /** @var list<array<string, mixed>> $jobs */
        $stats = $this->computeStats($jobs);

        return $this->render('admin/scheduler/index.html.twig', [
            'jobs' => $jobs,
            'agents' => $agentNames,
            'agent_skill_map' => $agentSkillMap,
            'username' => $user->getUserIdentifier(),
            'stats' => $stats,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $jobs
     *
     * @return array<string, mixed>
     */
    private function computeStats(array $jobs): array
    {
        $total = \count($jobs);
        $enabled = 0;
        $failed = 0;
        $stale = 0;

        foreach ($jobs as $job) {
            if (!empty($job['enabled'])) {
                ++$enabled;
            }
            if ('failed' === ($job['last_status'] ?? null) || 'dead_letter' === ($job['last_status'] ?? null)) {
                ++$failed;
            }
            if (null !== ($job['_stale'] ?? null)) {
                ++$stale;
            }
        }

        // Recent execution stats from logs (last 24h)
        $logStats = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT
                COUNT(*) AS total_runs,
                COUNT(*) FILTER (WHERE status = 'completed') AS completed,
                COUNT(*) FILTER (WHERE status = 'failed') AS failed,
                MAX(started_at) AS last_run
            FROM scheduler_job_logs
            WHERE created_at >= now() - INTERVAL '24 hours'
            SQL,
        );

        return [
            'total_jobs' => $total,
            'enabled_jobs' => $enabled,
            'disabled_jobs' => $total - $enabled,
            'failed_jobs' => $failed,
            'stale_jobs' => $stale,
            'runs_24h' => (int) ($logStats['total_runs'] ?? 0),
            'completed_24h' => (int) ($logStats['completed'] ?? 0),
            'failed_24h' => (int) ($logStats['failed'] ?? 0),
            'last_run' => $logStats['last_run'] ?? null,
        ];
    }
}
