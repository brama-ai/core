<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\A2AGateway\SkillCatalogSyncService;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentDeleteController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryRepository $registry,
        private readonly AgentRegistryAuditLogger $audit,
        private readonly SkillCatalogSyncService $syncService,
    ) {
    }

    #[Route('/api/v1/internal/agents/{name}', name: 'api_internal_agents_delete', methods: ['DELETE'])]
    public function __invoke(string $name): JsonResponse
    {
        $agent = $this->registry->findByName($name);

        if (null === $agent) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        if ($agent['enabled']) {
            return $this->json(
                ['error' => sprintf('Agent "%s" is enabled. Disable it before deleting.', $name)],
                Response::HTTP_CONFLICT,
            );
        }

        $actor = $this->getUser()?->getUserIdentifier() ?? 'unknown';

        $this->registry->delete($name);
        $this->audit->log($name, 'deleted', $actor);
        $this->syncService->pushDiscovery();

        return $this->json(['status' => 'deleted', 'name' => $name]);
    }
}
