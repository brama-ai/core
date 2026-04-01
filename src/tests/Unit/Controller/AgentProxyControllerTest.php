<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\AgentRegistry\AgentPublicEndpoint;
use App\AgentRegistry\AgentPublicEndpointRepositoryInterface;
use App\AgentRegistry\AgentRegistryInterface;
use App\Controller\Api\AgentProxyController;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class AgentProxyControllerTest extends Unit
{
    private AgentRegistryInterface&MockObject $registry;
    private AgentPublicEndpointRepositoryInterface&MockObject $endpointRepository;
    private HttpClientInterface&MockObject $httpClient;
    private AgentProxyController $controller;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistryInterface::class);
        $this->endpointRepository = $this->createMock(AgentPublicEndpointRepositoryInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->controller = new AgentProxyController(
            $this->registry,
            $this->endpointRepository,
            $this->httpClient,
            30,
        );
    }

    public function testReturns404WhenAgentNotFound(): void
    {
        $this->registry->method('findByName')->willReturn(null);

        $request = Request::create('/api/agents/nonexistent/webhook', 'POST');
        $response = $this->controller->__invoke($request, 'nonexistent', 'webhook');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertStringContainsString('Agent not found', (string) $response->getContent());
    }

    public function testReturns503WhenAgentIsDisabled(): void
    {
        $this->registry->method('findByName')->willReturn([
            'name' => 'disabled-agent',
            'enabled' => false,
        ]);

        $request = Request::create('/api/agents/disabled-agent/webhook', 'POST');
        $response = $this->controller->__invoke($request, 'disabled-agent', 'webhook');

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertStringContainsString('Agent is disabled', (string) $response->getContent());
    }

    public function testReturns403WhenEndpointNotDeclared(): void
    {
        $this->registry->method('findByName')->willReturn([
            'name' => 'hello',
            'enabled' => true,
        ]);
        $this->endpointRepository->method('findByAgentNameAndPath')->willReturn(null);

        $request = Request::create('/api/agents/hello/webhook', 'POST');
        $response = $this->controller->__invoke($request, 'hello', 'webhook');

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('Endpoint not exposed', (string) $response->getContent());
    }

    public function testReturns405WhenMethodNotAllowed(): void
    {
        $this->registry->method('findByName')->willReturn([
            'name' => 'test-agent',
            'enabled' => true,
        ]);

        $endpoint = $this->buildEndpoint('/webhook/telegram', ['POST']);
        $this->endpointRepository->method('findByAgentNameAndPath')->willReturn($endpoint);

        $request = Request::create('/api/agents/test-agent/webhook/telegram', 'GET');
        $response = $this->controller->__invoke($request, 'test-agent', 'webhook/telegram');

        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }

    public function testProxiesRequestToAgentAndReturnsResponse(): void
    {
        $this->registry->method('findByName')->willReturn([
            'name' => 'telegram-channel-agent',
            'enabled' => true,
        ]);

        $endpoint = $this->buildEndpoint('/webhook/telegram', ['POST']);
        $this->endpointRepository->method('findByAgentNameAndPath')->willReturn($endpoint);

        $agentResponse = $this->createMock(ResponseInterface::class);
        $agentResponse->method('getStatusCode')->willReturn(200);
        $agentResponse->method('getContent')->willReturn('{"ok":true}');
        $agentResponse->method('getHeaders')->willReturn(['content-type' => ['application/json']]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'http://telegram-channel-agent/webhook/telegram', $this->anything())
            ->willReturn($agentResponse);

        $request = Request::create('/api/agents/telegram-channel-agent/webhook/telegram', 'POST', [], [], [], [], '{"update_id":1}');
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->__invoke($request, 'telegram-channel-agent', 'webhook/telegram');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ok":true}', $response->getContent());
    }

    public function testReturns502WhenAgentUnreachable(): void
    {
        $this->registry->method('findByName')->willReturn([
            'name' => 'test-agent',
            'enabled' => true,
        ]);

        $endpoint = $this->buildEndpoint('/webhook', ['POST']);
        $this->endpointRepository->method('findByAgentNameAndPath')->willReturn($endpoint);

        $this->httpClient->method('request')
            ->willThrowException(new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused'));

        $request = Request::create('/api/agents/test-agent/webhook', 'POST');
        $response = $this->controller->__invoke($request, 'test-agent', 'webhook');

        $this->assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        $this->assertStringContainsString('Agent unreachable', (string) $response->getContent());
    }

    public function testReturns504WhenAgentTimesOut(): void
    {
        $this->registry->method('findByName')->willReturn([
            'name' => 'slow-agent',
            'enabled' => true,
        ]);

        $endpoint = $this->buildEndpoint('/webhook', ['POST']);
        $this->endpointRepository->method('findByAgentNameAndPath')->willReturn($endpoint);

        $this->httpClient->method('request')
            ->willThrowException(new \Symfony\Component\HttpClient\Exception\TimeoutException());

        $request = Request::create('/api/agents/slow-agent/webhook', 'POST');
        $response = $this->controller->__invoke($request, 'slow-agent', 'webhook');

        $this->assertSame(Response::HTTP_GATEWAY_TIMEOUT, $response->getStatusCode());
        $this->assertStringContainsString('Agent request timed out', (string) $response->getContent());
    }

    private function buildEndpoint(string $path, array $methods): AgentPublicEndpoint
    {
        return new AgentPublicEndpoint(
            id: 1,
            agentId: 'uuid-test',
            agentName: 'test-agent',
            path: $path,
            methods: $methods,
            description: null,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00',
        );
    }
}
