<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\A2AGateway\SkillCatalogSyncService;
use App\AgentInstaller\AgentInstallerService;
use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\AgentMigrationTrigger;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use Psr\Log\LoggerInterface;
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
        private readonly AgentInstallerService $installer,
        private readonly AgentMigrationTrigger $migrationTrigger,
        private readonly LoggerInterface $logger,
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

        $manifest = is_string($agent['manifest'] ?? null)
            ? (array) json_decode($agent['manifest'], true)
            : (is_array($agent['manifest'] ?? null) ? $agent['manifest'] : []);

        $actions = [];

        if (null === ($agent['installed_at'] ?? null) && isset($manifest['storage'])) {
            try {
                $actions = $this->installer->install($manifest);

                if (isset($manifest['storage']['postgres'])) {
                    $this->migrationTrigger->triggerMigrations($manifest);
                    $this->logger->info('Agent migrations triggered', ['agent' => $name]);
                }

                $this->registry->markInstalled($name);
                $this->logger->info('Agent storage provisioned', ['agent' => $name, 'actions' => $actions]);
            } catch (AgentInstallException $e) {
                $this->logger->error('Agent provisioning failed', ['agent' => $name, 'error' => $e->getMessage()]);

                return $this->json([
                    'error' => sprintf('Storage provisioning failed: %s', $e->getMessage()),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $updated = $this->registry->enable($name, $actor);

        if (!$updated) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        $this->audit->log($name, 'enabled', $actor, ['provisioned_actions' => $actions]);
        $this->syncService->pushDiscovery();

        return $this->json([
            'status' => 'enabled',
            'name' => $name,
            'provisioned' => $actions,
        ]);
    }
}
