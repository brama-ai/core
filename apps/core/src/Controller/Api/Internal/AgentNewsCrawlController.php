<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\AgentAction\AgentActionException;
use App\AgentAction\NewsCrawlTrigger;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentNewsCrawlController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryRepository $registry,
        private readonly AgentRegistryAuditLogger $audit,
        private readonly NewsCrawlTrigger $crawlTrigger,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/internal/agents/{name}/crawl', name: 'api_internal_agents_news_crawl', methods: ['POST'])]
    public function __invoke(string $name): JsonResponse
    {
        $agent = $this->registry->findByName($name);

        if (null === $agent) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        if ('news-maker-agent' !== $name) {
            return $this->json([
                'error' => 'Manual crawl trigger is supported only for news-maker-agent.',
            ], Response::HTTP_CONFLICT);
        }

        if (null === ($agent['installed_at'] ?? null)) {
            return $this->json([
                'error' => sprintf('Agent "%s" is not installed. Install it before triggering crawl.', $name),
            ], Response::HTTP_CONFLICT);
        }

        $manifest = $this->decodeManifest($agent);

        try {
            $this->crawlTrigger->trigger($manifest);

            $actor = $this->getUser()?->getUserIdentifier() ?? 'unknown';
            $this->audit->log($name, 'crawl_triggered', $actor);

            $this->logger->info('Agent crawl triggered from admin', [
                'agent' => $name,
                'actor' => $actor,
            ]);

            return $this->json([
                'status' => 'queued',
                'name' => $name,
                'message' => 'News parsing pipeline triggered.',
            ]);
        } catch (AgentActionException $e) {
            $this->logger->warning('Agent crawl trigger failed', [
                'agent' => $name,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => sprintf('Crawl trigger failed: %s', $e->getMessage()),
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
