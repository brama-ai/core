<?php

declare(strict_types=1);

namespace App\Controller\Api\OpenClaw;

use App\AgentDiscovery\DiscoveryBuilder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscoveryController extends AbstractController
{
    private const CACHE_KEY = 'openclaw_discovery_payload';
    private const CACHE_TTL = 30;

    public function __construct(
        private readonly DiscoveryBuilder $discoveryBuilder,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $gatewayToken,
    ) {
    }

    #[Route('/api/v1/agents/discovery', name: 'api_openclaw_discovery', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            $this->logger->warning('Unauthorized discovery request', [
                'ip' => $request->getClientIp(),
            ]);

            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            /** @var array<string, mixed> $cached */
            $cached = $item->get();
            $this->logger->debug('Discovery served from cache');

            return $this->json($cached);
        }

        $payload = $this->discoveryBuilder->build();

        $item->set($payload);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        $toolCount = \count($payload['tools'] ?? []);
        $this->logger->info('Discovery payload built', ['tool_count' => $toolCount]);

        return $this->json($payload);
    }

    private function isAuthorized(Request $request): bool
    {
        if ('' === $this->gatewayToken) {
            return false;
        }

        $header = $request->headers->get('Authorization', '');

        return 'Bearer '.$this->gatewayToken === $header;
    }
}
