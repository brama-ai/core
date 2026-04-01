<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\AgentRegistry\AgentPublicEndpointRepositoryInterface;
use App\AgentRegistry\AgentRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Catch-all reverse proxy for agent public endpoints.
 *
 * Route: GET|POST|PUT|DELETE /api/agents/{agentName}/{path}
 *
 * Validates that the agent exists, is enabled, and has declared the requested
 * path as a public endpoint before forwarding the request.
 */
final class AgentProxyController
{
    /** Headers forwarded from the incoming request to the agent. */
    private const FORWARDED_HEADERS = [
        'content-type',
        'accept',
        'accept-language',
        'x-telegram-bot-api-secret-token',
    ];

    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly AgentPublicEndpointRepositoryInterface $endpointRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly int $proxyTimeout,
    ) {
    }

    #[Route(
        '/api/agents/{agentName}/{path}',
        name: 'api_agent_proxy',
        requirements: ['path' => '.+'],
        methods: ['GET', 'POST', 'PUT', 'DELETE'],
    )]
    public function __invoke(Request $request, string $agentName, string $path): Response
    {
        // 1. Agent must exist in registry
        $agent = $this->registry->findByName($agentName);
        if (null === $agent) {
            return new JsonResponse(['error' => 'Agent not found'], Response::HTTP_NOT_FOUND);
        }

        // 2. Agent must be enabled
        if (!(bool) ($agent['enabled'] ?? false)) {
            return new JsonResponse(['error' => 'Agent is disabled'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // 3. Path must be declared as a public endpoint
        $normalizedPath = '/'.$path;
        $endpoint = $this->endpointRepository->findByAgentNameAndPath($agentName, $normalizedPath);
        if (null === $endpoint) {
            return new JsonResponse(['error' => 'Endpoint not exposed'], Response::HTTP_FORBIDDEN);
        }

        // 4. HTTP method must be allowed for this endpoint
        $method = strtoupper($request->getMethod());
        if (!in_array($method, $endpoint->methods, true)) {
            return new JsonResponse(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        // 5. Forward request to agent
        return $this->proxyRequest($request, $agentName, $normalizedPath, $method);
    }

    private function proxyRequest(
        Request $request,
        string $agentName,
        string $path,
        string $method,
    ): Response {
        $targetUrl = sprintf('http://%s%s', $agentName, $path);

        $queryString = $request->getQueryString();
        if (null !== $queryString && '' !== $queryString) {
            $targetUrl .= '?'.$queryString;
        }

        $headers = $this->buildForwardedHeaders($request);

        try {
            $response = $this->httpClient->request($method, $targetUrl, [
                'headers' => $headers,
                'body' => $request->getContent(),
                'timeout' => $this->proxyTimeout,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $responseHeaders = $response->getHeaders(false);

            $symfonyResponse = new Response($content, $statusCode);

            // Forward content-type from agent response
            if (isset($responseHeaders['content-type'][0])) {
                $symfonyResponse->headers->set('Content-Type', $responseHeaders['content-type'][0]);
            }

            return $symfonyResponse;
        } catch (TimeoutExceptionInterface) {
            return new JsonResponse(['error' => 'Agent request timed out'], Response::HTTP_GATEWAY_TIMEOUT);
        } catch (TransportExceptionInterface) {
            return new JsonResponse(['error' => 'Agent unreachable'], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildForwardedHeaders(Request $request): array
    {
        $headers = [];

        foreach (self::FORWARDED_HEADERS as $headerName) {
            $value = $request->headers->get($headerName);
            if (null !== $value && '' !== $value) {
                $headers[$headerName] = $value;
            }
        }

        // Add forwarding metadata headers
        $clientIp = $request->getClientIp() ?? '';
        if ('' !== $clientIp) {
            $headers['X-Forwarded-For'] = $clientIp;
        }

        $host = $request->getHost();
        if ('' !== $host) {
            $headers['X-Forwarded-Host'] = $host;
        }

        return $headers;
    }
}
