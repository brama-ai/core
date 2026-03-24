<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\AgentRegistry\AgentHealthChecker;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use App\AgentRegistry\ManifestValidator;
use App\Tenant\TenantContext;
use App\Tenant\TenantRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AgentRegistrationController extends AbstractController
{
    public function __construct(
        private readonly ManifestValidator $validator,
        private readonly AgentRegistryRepository $registry,
        private readonly AgentRegistryAuditLogger $audit,
        private readonly TenantContext $tenantContext,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly AgentHealthChecker $healthChecker,
    ) {
    }

    #[Route('/api/v1/internal/agents/register', name: 'api_internal_agents_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) $request->headers->get('X-Platform-Internal-Token', '');
        $param = $this->getParameter('app.internal_token');
        $expected = is_string($param) ? $param : '';

        if ('' === $expected || !hash_equals($expected, $token)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $request->getContent();
        if ('' === $content) {
            return $this->json(['error' => 'Empty request body'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $errors = $this->validator->validate($manifest);

        if ([] !== $errors) {
            return $this->json(['error' => 'Manifest validation failed', 'details' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$this->tenantContext->isSet()) {
            $defaultTenant = $this->tenantRepository->findBySlug('default');
            if (null !== $defaultTenant) {
                $this->tenantContext->set($defaultTenant);
            }
        }

        $this->registry->register($manifest);
        $this->audit->log((string) $manifest['name'], 'registered', null, ['version' => $manifest['version']]);

        // Perform inline health probe if manifest includes health_url
        $healthStatus = 'unknown';
        $healthUrl = $manifest['health_url'] ?? null;
        if (is_string($healthUrl) && '' !== $healthUrl) {
            $isHealthy = $this->healthChecker->checkInline($healthUrl);
            if ($isHealthy) {
                $healthStatus = 'healthy';
                $this->registry->updateHealthStatus((string) $manifest['name'], 'healthy');
            }
        }

        return $this->json(['status' => 'registered', 'name' => $manifest['name'], 'health_status' => $healthStatus], Response::HTTP_OK);
    }
}
