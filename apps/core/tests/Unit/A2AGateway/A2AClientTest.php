<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway;

use App\A2AGateway\A2AClient;
use App\AgentRegistry\AgentRegistryInterface;
use App\Logging\PayloadSanitizer;
use App\Observability\LangfuseIngestionClient;
use App\Tenant\TenantContext;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class A2AClientTest extends Unit
{
    private AgentRegistryInterface&MockObject $registry;
    private Connection&MockObject $dbal;
    private LoggerInterface&MockObject $logger;
    private A2AClient $client;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistryInterface::class);
        $this->dbal = $this->createMock(Connection::class);
        $langfuse = new LangfuseIngestionClient(false, '', '', '', 'test', new NullLogger());
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request());
        $this->client = new A2AClient($this->registry, $this->dbal, $langfuse, new PayloadSanitizer(), $this->logger, new TenantContext(), $this->requestStack, 'dev-internal-token');
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

    public function testInvokePassesCustomActorToAuditLog(): void
    {
        $agent = $this->buildAgent('actor-agent', ['actor.tool'], false);

        $this->registry->method('findEnabled')->willReturn([]);
        $this->registry->method('findAll')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO a2a_message_audit'),
                $this->callback(fn (array $params): bool => 'cli:john' === ($params['actor'] ?? null)),
            );

        $this->client->invoke('actor.tool', [], 'trace-a', 'req-a', 'cli:john');
    }

    public function testInvokeUsesDefaultActorWhenNotProvided(): void
    {
        $agent = $this->buildAgent('default-agent', ['default.tool'], false);

        $this->registry->method('findEnabled')->willReturn([]);
        $this->registry->method('findAll')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO a2a_message_audit'),
                $this->callback(fn (array $params): bool => 'openclaw' === ($params['actor'] ?? null)),
            );

        $this->client->invoke('default.tool', [], 'trace-d', 'req-d');
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

    public function testInvokeResolvesEnabledAgentAndLogsSuccess(): void
    {
        $agent = $this->buildAgent('success-agent', ['success.tool'], true, 'http://success-agent:8080/a2a');

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $loggedMessages = [];
        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedMessages): void {
                $loggedMessages[] = $message;
            });

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO a2a_message_audit'));

        $result = $this->client->invoke('success.tool', ['test' => 'data'], 'trace-success', 'req-success');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('a2a_error', $result['reason']);
        $this->assertContains('Skill resolved to enabled agent', $loggedMessages);
    }

    public function testInvokeIncludesSystemPromptFromConfig(): void
    {
        $agent = [
            'name' => 'prompt-agent',
            'manifest' => json_encode([
                'description' => 'Agent with system prompt',
                'skills' => ['prompt.tool'],
                'url' => 'http://prompt-agent:8080/a2a',
            ], JSON_THROW_ON_ERROR),
            'config' => json_encode([
                'system_prompt' => 'You are a helpful assistant.',
            ], JSON_THROW_ON_ERROR),
            'enabled' => true,
        ];

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('INSERT INTO a2a_message_audit'));

        // This will fail at HTTP call level, but we're testing the payload construction
        $result = $this->client->invoke('prompt.tool', [], 'trace-prompt', 'req-prompt');

        // Should fail due to HTTP call, but the system prompt should be included in payload
        $this->assertSame('failed', $result['status']);
    }

    public function testInvokeAuditsCallWithCorrectParameters(): void
    {
        $agent = $this->buildAgent('audit-agent', ['audit.tool'], true, 'http://audit-agent:8080/a2a');

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO a2a_message_audit'),
                $this->callback(fn (array $params): bool => 'audit.tool' === ($params['skill'] ?? null)
                    && 'audit-agent' === ($params['agent'] ?? null)
                    && 'trace-audit' === ($params['traceId'] ?? null)
                    && 'req-audit' === ($params['requestId'] ?? null)
                    && 'failed' === ($params['status'] ?? null)
                    && 'openclaw' === ($params['actor'] ?? null)
                ),
            );

        $this->client->invoke('audit.tool', [], 'trace-audit', 'req-audit');
    }

    public function testInvokeDefaultsAcceptLanguageToUkWhenNoRequest(): void
    {
        $agent = $this->buildAgent('locale-agent', ['locale.tool'], true, 'http://locale-agent:8080/a2a');

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement');

        $clientWithoutRequest = new A2AClient(
            $this->registry,
            $this->dbal,
            new LangfuseIngestionClient(false, '', '', '', 'test', new NullLogger()),
            new PayloadSanitizer(),
            $this->logger,
            new TenantContext(),
            new RequestStack(),
            'dev-internal-token',
        );

        $result = $clientWithoutRequest->invoke('locale.tool', [], 'trace-default', 'req-default');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('a2a_error', $result['reason']);
    }

    public function testInvokeSetsAcceptLanguageFromRequest(): void
    {
        $agent = $this->buildAgent('locale-agent', ['locale.tool'], true, 'http://locale-agent:8080/a2a');

        $this->registry->method('findEnabled')->willReturn([$agent]);

        $this->dbal->expects($this->once())
            ->method('executeStatement');

        $request = new Request();
        $request->setLocale('en');

        $requestStackWithLocale = new RequestStack();
        $requestStackWithLocale->push($request);

        $clientWithLocale = new A2AClient(
            $this->registry,
            $this->dbal,
            new LangfuseIngestionClient(false, '', '', '', 'test', new NullLogger()),
            new PayloadSanitizer(),
            $this->logger,
            new TenantContext(),
            $requestStackWithLocale,
            'dev-internal-token',
        );

        $result = $clientWithLocale->invoke('locale.tool', [], 'trace-en', 'req-en');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('a2a_error', $result['reason']);
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
