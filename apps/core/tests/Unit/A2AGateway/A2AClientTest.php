<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway;

use App\A2AGateway\A2AClient;
use App\AgentRegistry\AgentRegistryInterface;
use App\Logging\PayloadSanitizer;
use App\Observability\LangfuseIngestionClient;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class A2AClientTest extends Unit
{
    private AgentRegistryInterface&MockObject $registry;
    private Connection&MockObject $dbal;
    private LoggerInterface&MockObject $logger;
    private A2AClient $client;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistryInterface::class);
        $this->dbal = $this->createMock(Connection::class);
        $langfuse = new LangfuseIngestionClient(false, '', '', '', 'test', new NullLogger());
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->client = new A2AClient($this->registry, $this->dbal, $langfuse, new PayloadSanitizer(), $this->logger);
    }

    public function testInvokeReturnsFailedForUnknownTool(): void
    {
        $this->registry->method('findEnabled')->willReturn([]);
        $this->registry->method('findAll')->willReturn([]);

        $result = $this->client->invoke('nonexistent.tool', [], 'trace-1', 'req-1');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('unknown_tool', $result['reason']);
    }

    public function testInvokeReturnsFailedForDisabledAgent(): void
    {
        $agent = $this->buildAgent('disabled-agent', ['test.action'], false);

        $this->registry->method('findEnabled')->willReturn([]);
        $this->registry->method('findAll')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO a2a_message_audit'));

        $result = $this->client->invoke('test.action', [], 'trace-2', 'req-2');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('agent_disabled', $result['reason']);
    }

    public function testInvokeReturnsFailedForAgentWithoutUrl(): void
    {
        $agent = $this->buildAgent('no-endpoint-agent', ['test.action'], true, '');

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO a2a_message_audit'));

        $result = $this->client->invoke('test.action', [], 'trace-3', 'req-3');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('no_a2a_endpoint', $result['reason']);
    }

    public function testInvokeResolvesSkillFromStructuredSkills(): void
    {
        $agent = $this->buildAgentWithStructuredSkills('struct-agent', [
            ['id' => 'hello.greet', 'name' => 'Greet', 'description' => 'Greet a user'],
        ], true, '');

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO a2a_message_audit'));

        $result = $this->client->invoke('hello.greet', [], 'trace-5', 'req-5');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('no_a2a_endpoint', $result['reason']);
    }

    public function testInvokeResolvesSkillWithLegacyA2aEndpoint(): void
    {
        $agent = [
            'name' => 'legacy-agent',
            'manifest' => json_encode([
                'description' => 'Legacy agent',
                'skills' => ['legacy.action'],
                'a2a_endpoint' => '',
            ], JSON_THROW_ON_ERROR),
            'config' => null,
            'enabled' => true,
        ];

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO a2a_message_audit'));

        $result = $this->client->invoke('legacy.action', [], 'trace-6', 'req-6');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('no_a2a_endpoint', $result['reason']);
    }

    public function testInvokeLogsWarningForDisabledAgent(): void
    {
        $agent = $this->buildAgent('warn-agent', ['warn.tool'], false);

        $this->registry->method('findEnabled')->willReturn([]);
        $this->registry->method('findAll')->willReturn([$agent]);

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('Tool found on disabled agent', $this->callback(
                fn (array $ctx): bool => 'warn.tool' === ($ctx['tool'] ?? null)
                    && ('warn-agent' === ($ctx['agent'] ?? null) || 'warn-agent' === ($ctx['target_app'] ?? null)),
            ));

        $this->client->invoke('warn.tool', [], 'trace-4', 'req-4');
    }

    /**
     * @param list<string> $skills
     *
     * @return array<string, mixed>
     */
    private function buildAgent(
        string $name,
        array $skills,
        bool $enabled,
        string $url = 'http://example.com/a2a',
    ): array {
        return [
            'name' => $name,
            'manifest' => json_encode([
                'description' => 'Test agent',
                'skills' => $skills,
                'url' => $url,
            ], JSON_THROW_ON_ERROR),
            'config' => null,
            'enabled' => $enabled,
        ];
    }

    /**
     * @param list<array<string, mixed>> $skills
     *
     * @return array<string, mixed>
     */
    private function buildAgentWithStructuredSkills(
        string $name,
        array $skills,
        bool $enabled,
        string $url = 'http://example.com/a2a',
    ): array {
        return [
            'name' => $name,
            'manifest' => json_encode([
                'description' => 'Test agent',
                'skills' => $skills,
                'url' => $url,
            ], JSON_THROW_ON_ERROR),
            'config' => null,
            'enabled' => $enabled,
        ];
    }
}
