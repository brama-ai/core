<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\A2AGateway\SkillCatalogSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentSyncController extends AbstractController
{
    public function __construct(
        private readonly SkillCatalogSyncService $syncService,
    ) {
    }

    #[Route('/admin/agents/sync', name: 'admin_agents_sync', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $this->syncService->pushDiscovery();

        $status = $this->syncService->getSyncStatus();

        return $this->json([
            'pushed' => true,
            'sync_status' => $status,
        ]);
    }
}
