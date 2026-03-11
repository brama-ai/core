<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\AgentRegistry\AgentRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentSkillsController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
    ) {
    }

    #[Route('/api/v1/internal/agents/{name}/skills', name: 'api_internal_agent_skills', methods: ['GET'])]
    public function __invoke(string $name): JsonResponse
    {
        $agent = $this->registry->findByName($name);

        if (null === $agent) {
            return $this->json(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        $manifest = is_string($agent['manifest'] ?? null)
            ? (array) json_decode($agent['manifest'], true)
            : (array) ($agent['manifest'] ?? []);

        $skills = [];
        foreach ((array) ($manifest['skills'] ?? []) as $skill) {
            if (is_array($skill) && isset($skill['id'])) {
                $skills[] = [
                    'id' => (string) $skill['id'],
                    'name' => (string) ($skill['name'] ?? $skill['id']),
                    'description' => (string) ($skill['description'] ?? ''),
                ];
            }
        }

        return $this->json(['agent' => $name, 'skills' => $skills]);
    }
}
