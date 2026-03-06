<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\A2AGateway\SkillCatalogBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public A2A discovery endpoint per RFC 8615 well-known URI convention.
 *
 * Returns a platform-level AgentCard describing Core as an A2A Gateway,
 * with aggregated skills from all enabled agents.
 */
final class WellKnownAgentCardController extends AbstractController
{
    public function __construct(
        private readonly SkillCatalogBuilder $catalogBuilder,
    ) {
    }

    #[Route('/.well-known/agent-card.json', name: 'well_known_agent_card', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $catalog = $this->catalogBuilder->build();

        $skills = [];
        foreach ((array) ($catalog['tools'] ?? []) as $tool) {
            /** @var array<string, mixed> $tool */
            $skill = [
                'id' => (string) ($tool['name'] ?? ''),
                'name' => (string) ($tool['name'] ?? ''),
                'description' => (string) ($tool['description'] ?? ''),
                'tags' => (array) ($tool['tags'] ?? []),
            ];
            $skills[] = $skill;
        }

        return $this->json([
            'name' => 'ai-community-platform',
            'version' => (string) ($catalog['platform_version'] ?? '0.1.0'),
            'description' => 'AI Community Platform — A2A Gateway aggregating agent skills',
            'url' => '/api/v1/a2a/send-message',
            'provider' => [
                'organization' => 'AI Community Platform',
            ],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
                'stateTransitionHistory' => false,
            ],
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'skills' => $skills,
        ]);
    }
}
