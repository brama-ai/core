<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\AgentInstaller\AgentInstallerService;
use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\AgentMigrationTrigger;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use App\Scheduler\SchedulerService;
use App\Tenant\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentInstallController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryRepository $registry,
        private readonly AgentRegistryAuditLogger $audit,
        private readonly AgentInstallerService $installer,
        private readonly AgentMigrationTrigger $migrationTrigger,
        private readonly SchedulerService $schedulerService,
        private readonly LoggerInterface $logger,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route('/api/v1/internal/agents/{name}/install', name: 'api_internal_agents_install', methods: ['POST'])]
    public function __invoke(string $name): JsonResponse
    {
        $agent = $this->registry->findByName($name);
        if (null === $agent) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        if (null !== ($agent['installed_at'] ?? null)) {
            return $this->json([
                'status' => 'installed',
                'name' => $name,
                'provisioned' => [],
            ]);
        }

        // Enforce shared/dedicated agent constraint
        $isShared = (bool) ($agent['shared'] ?? false);
        if (!$isShared && $this->tenantContext->isSet()) {
            $tenantId = $this->tenantContext->requireTenantId();
            if ($this->registry->isAgentInstalledInOtherTenant($name, $tenantId)) {
                return $this->json([
                    'error' => sprintf(
                        'Agent "%s" is already installed in another tenant and is not marked as shared. Create a new agent instance instead.',
                        $name,
                    ),
                ], Response::HTTP_CONFLICT);
            }
        }

        $manifest = $this->decodeManifest($agent);

        try {
            $actions = $this->installer->install($manifest);
            $warnings = [];

            if (isset($manifest['storage']['postgres'])) {
                try {
                    $this->migrationTrigger->triggerMigrations($manifest);
                    $this->logger->info('Agent migrations triggered', ['agent' => $name]);
                } catch (AgentInstallException $e) {
                    $warning = sprintf('Migration trigger failed (best effort): %s', $e->getMessage());
                    $warnings[] = $warning;

                    $this->logger->warning('Agent migration trigger failed; continuing install', [
                        'agent' => $name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->registry->markInstalled($name);

            $scheduledCount = $this->schedulerService->registerFromManifest($name, $manifest);
            if ($scheduledCount > 0) {
                $this->logger->info('Scheduled jobs registered', ['agent' => $name, 'count' => $scheduledCount]);
            }

            $actor = $this->getUser()?->getUserIdentifier() ?? 'unknown';
            $this->audit->log($name, 'installed', $actor, [
                'provisioned_actions' => $actions,
                'warnings' => $warnings,
            ]);

            $this->logger->info('Agent installed', [
                'agent' => $name,
                'actions' => $actions,
                'warnings' => $warnings,
            ]);

            $response = [
                'status' => 'installed',
                'name' => $name,
                'provisioned' => $actions,
            ];

            if ([] !== $warnings) {
                $response['warnings'] = $warnings;
            }

            return $this->json($response);
        } catch (AgentInstallException $e) {
            $this->logger->error('Agent install failed', [
                'agent' => $name,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => sprintf('Storage provisioning failed: %s', $e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
