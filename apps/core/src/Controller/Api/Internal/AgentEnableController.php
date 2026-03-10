<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\A2AGateway\SkillCatalogSyncService;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use App\Scheduler\SchedulerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentEnableController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryRepository $registry,
        private readonly AgentRegistryAuditLogger $audit,
        private readonly SkillCatalogSyncService $syncService,
        private readonly SchedulerService $schedulerService,
    ) {
    }

    #[Route('/api/v1/internal/agents/{name}/enable', name: 'api_internal_agents_enable', methods: ['POST'])]
    public function __invoke(string $name): JsonResponse
    {
        $actor = $this->getUser()?->getUserIdentifier() ?? 'unknown';

        $agent = $this->registry->findByName($name);

        if (null === $agent) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        if (null === ($agent['installed_at'] ?? null)) {
            return $this->json([
                'error' => sprintf('Agent "%s" is not installed. Install it before enabling.', $name),
            ], Response::HTTP_CONFLICT);
        }

        $updated = $this->registry->enable($name, $actor);

        if (!$updated) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        $this->schedulerService->enableByAgent($name);
        $this->audit->log($name, 'enabled', $actor);
        $this->syncService->pushDiscovery();

        return $this->json([
            'status' => 'enabled',
            'name' => $name,
            'provisioned' => [],
        ]);
    }
}
