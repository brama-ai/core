<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\A2AGateway\AgentCardFetcher;
use App\A2AGateway\AgentConventionVerifier;
use App\A2AGateway\AgentDiscoveryService;
use App\AgentRegistry\AgentRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentRunDiscoveryController extends AbstractController
{
    public function __construct(
        private readonly AgentDiscoveryService $discoveryService,
        private readonly AgentCardFetcher $agentCardFetcher,
        private readonly AgentConventionVerifier $conventionVerifier,
        private readonly AgentRegistryInterface $registry,
    ) {
    }

    #[Route('/admin/agents/discover', name: 'admin_agents_discover', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $agents = $this->discoveryService->discoverAgents();
        $results = [];

        foreach ($agents as ['hostname' => $hostname, 'port' => $port]) {
            $manifest = $this->agentCardFetcher->fetch($hostname, $port);
            $result = $this->conventionVerifier->verify($manifest);

            $name = is_string($manifest['name'] ?? null) && '' !== $manifest['name']
                ? (string) $manifest['name']
                : $hostname;

            $this->registry->upsertFromDiscovery($name, $manifest, $result->status, $result->violations);

            $results[] = [
                'name' => $name,
                'status' => $result->status,
                'violations' => $result->violations,
            ];
        }

        return $this->json([
            'discovered' => count($agents),
            'results' => $results,
        ]);
    }
}
