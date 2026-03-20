<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\AgentRegistry\AgentRegistryInterface;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AgentsController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
    ) {
    }

    #[Route('/admin/agents', name: 'admin_agents')]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $agents = array_map([$this, 'normalizeAgentRow'], $this->registry->findAll());

        $installedAgents = array_values(array_filter(
            $agents,
            static fn (array $agent): bool => true === ($agent['is_installed'] ?? false),
        ));

        $marketplaceAgents = array_values(array_filter(
            $agents,
            static fn (array $agent): bool => false === ($agent['is_installed'] ?? false),
        ));

        return $this->render('admin/agents.html.twig', [
            'agents' => $agents,
            'installed_agents' => $installedAgents,
            'marketplace_agents' => $marketplaceAgents,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    /**
     * @param array<string, mixed> $agent
     *
     * @return array<string, mixed>
     */
    private function normalizeAgentRow(array $agent): array
    {
        $manifest = $this->decodeJsonAssoc($agent['manifest'] ?? []);
        $violations = $this->decodeJsonList($agent['violations'] ?? []);
        $configDecoded = $this->decodeJsonAssoc($agent['config'] ?? []);

        $agent['manifest'] = $manifest;
        $agent['violations'] = $violations;
        $agent['config_decoded'] = $configDecoded;
        $agent['is_installed'] = null !== ($agent['installed_at'] ?? null);

        return $agent;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonAssoc(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || '' === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function decodeJsonList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        if (!is_string($value) || '' === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_map(static fn (mixed $item): string => (string) $item, $decoded));
        } catch (\JsonException) {
            return [];
        }
    }
}
