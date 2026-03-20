<?php

declare(strict_types=1);

namespace App\Controller\Api\A2AGateway;

use App\A2AGateway\SkillCatalogBuilderInterface;
use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscoveryController extends AbstractController
{
    private const CACHE_KEY = 'a2a_discovery_payload';
    private const CACHE_TTL = 30;

    public function __construct(
        private readonly SkillCatalogBuilderInterface $catalogBuilder,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly PayloadSanitizer $payloadSanitizer,
        private readonly string $gatewayToken,
    ) {
    }

    #[Route('/api/v1/a2a/discovery', name: 'api_a2a_discovery', methods: ['GET'])]
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
            $cachedTools = [];
            foreach ((array) ($cached['tools'] ?? []) as $tool) {
                /** @var array<string, mixed> $tool */
                $schema = $tool['input_schema'] ?? [];
                $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $cachedTools[] = [
                    'name' => (string) ($tool['name'] ?? ''),
                    'agent' => (string) ($tool['agent'] ?? ''),
                    'description' => (string) ($tool['description'] ?? ''),
                    'input_schema_fingerprint' => hash('sha256', false === $schemaJson ? '' : $schemaJson),
                ];
            }
            $cachedSanitized = $this->payloadSanitizer->sanitize([
                'generated_at' => $cached['generated_at'] ?? null,
                'tool_count' => \count((array) ($cached['tools'] ?? [])),
                'tools' => $cachedTools,
            ]);
            $this->logger->info(
                'Discovery served from cache',
                TraceEvent::build('core.discovery.cache_hit', 'discovery_fetch', 'core', 'completed', [
                    'target_app' => 'openclaw',
                    'tool_count' => \count((array) ($cached['tools'] ?? [])),
                    'step_output' => $cachedSanitized['data'],
                    'capture_meta' => $cachedSanitized['capture_meta'],
                ]),
            );
            $this->logger->debug('Discovery served from cache');

            return $this->json($cached);
        }

        $payload = $this->catalogBuilder->build();

        $item->set($payload);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        $toolCount = \count($payload['tools'] ?? []);
        $tools = [];
        foreach ((array) ($payload['tools'] ?? []) as $tool) {
            /** @var array<string, mixed> $tool */
            $schema = $tool['input_schema'] ?? [];
            $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $tools[] = [
                'name' => (string) ($tool['name'] ?? ''),
                'agent' => (string) ($tool['agent'] ?? ''),
                'description' => (string) ($tool['description'] ?? ''),
                'input_schema_fingerprint' => hash('sha256', false === $schemaJson ? '' : $schemaJson),
            ];
        }
        $sanitized = $this->payloadSanitizer->sanitize([
            'generated_at' => $payload['generated_at'] ?? null,
            'tool_count' => $toolCount,
            'tools' => $tools,
        ]);
        $this->logger->info(
            'Discovery payload built',
            TraceEvent::build('core.discovery.snapshot', 'discovery_response', 'core', 'completed', [
                'target_app' => 'openclaw',
                'tool_count' => $toolCount,
                'step_output' => $sanitized['data'],
                'capture_meta' => $sanitized['capture_meta'],
            ]),
        );
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
