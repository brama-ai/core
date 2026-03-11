<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\AgentRegistry\AgentRegistryInterface;
use App\Scheduler\ScheduledJobRepositoryInterface;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SchedulerController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $repository,
        private readonly AgentRegistryInterface $agentRegistry,
    ) {
    }

    #[Route('/admin/scheduler', name: 'admin_scheduler')]
    public function __invoke(#[CurrentUser] AdminUser $user): Response
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

        return $this->render('admin/scheduler/index.html.twig', [
            'jobs' => $jobs,
            'agents' => $agentNames,
            'agent_skill_map' => $agentSkillMap,
            'username' => $user->getUserIdentifier(),
        ]);
    }
}
