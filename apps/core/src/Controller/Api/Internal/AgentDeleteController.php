<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\A2AGateway\SkillCatalogSyncService;
use App\AgentInstaller\AgentInstallerService;
use App\AgentInstaller\AgentInstallException;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use App\Scheduler\SchedulerService;
use Psr\Log\LoggerInterface;
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
        private readonly AgentInstallerService $installer,
        private readonly SchedulerService $schedulerService,
        private readonly LoggerInterface $logger,
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

        $manifest = $this->decodeManifest($agent);

        try {
            $actions = $this->installer->uninstall($manifest);
            $this->schedulerService->removeByAgent($name);
            $this->registry->markUninstalled($name);
            $this->audit->log($name, 'uninstalled', $actor, ['deprovisioned_actions' => $actions]);
            $this->syncService->pushDiscovery();
        } catch (AgentInstallException $e) {
            $this->logger->error('Agent uninstall failed', [
                'agent' => $name,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => sprintf('Storage deprovision failed: %s', $e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'uninstalled', 'name' => $name]);
    }

    /**
     * @param array<string, mixed> $agentRow
     *
     * @return array<string, mixed>
     */
    private function decodeManifest(array $agentRow): array
    {
        if (is_array($agentRow['manifest'] ?? null)) {
            return $agentRow['manifest'];
        }

        if (is_string($agentRow['manifest'] ?? null)) {
            try {
                $decoded = json_decode((string) $agentRow['manifest'], true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return [];
            }
        }

        return [];
    }
}
