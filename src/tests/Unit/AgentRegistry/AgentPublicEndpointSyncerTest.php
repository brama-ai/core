<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentRegistry;

use App\AgentRegistry\AgentPublicEndpointRepositoryInterface;
use App\AgentRegistry\AgentPublicEndpointSyncer;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class AgentPublicEndpointSyncerTest extends Unit
{
    private AgentPublicEndpointRepositoryInterface&MockObject $repository;
    private Connection&MockObject $connection;
    private LoggerInterface&MockObject $logger;
    private AgentPublicEndpointSyncer $syncer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AgentPublicEndpointRepositoryInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->syncer = new AgentPublicEndpointSyncer(
            $this->repository,
            $this->connection,
            $this->logger,
        );
    }

    public function testSyncFromManifestWithPublicEndpoints(): void
    {
        $this->connection->method('fetchOne')->willReturn('uuid-1234');

        $this->repository->expects($this->once())
            ->method('syncForAgent')
            ->with('uuid-1234', [
                ['path' => '/webhook/telegram', 'methods' => ['POST'], 'description' => 'Telegram webhook'],
            ]);

        $manifest = [
            'name' => 'test-agent',
            'version' => '1.0.0',
            'public_endpoints' => [
                ['path' => '/webhook/telegram', 'methods' => ['POST'], 'description' => 'Telegram webhook'],
            ],
        ];

        $this->syncer->syncFromManifest('test-agent', $manifest);
    }

    public function testSyncFromManifestWithNoPublicEndpoints(): void
    {
        $this->connection->method('fetchOne')->willReturn('uuid-1234');

        $this->repository->expects($this->once())
            ->method('syncForAgent')
            ->with('uuid-1234', []);

        $manifest = [
            'name' => 'test-agent',
            'version' => '1.0.0',
        ];

        $this->syncer->syncFromManifest('test-agent', $manifest);
    }

    public function testSyncFromManifestWithEmptyPublicEndpoints(): void
    {
        $this->connection->method('fetchOne')->willReturn('uuid-1234');

        $this->repository->expects($this->once())
            ->method('syncForAgent')
            ->with('uuid-1234', []);

        $manifest = [
            'name' => 'test-agent',
            'version' => '1.0.0',
            'public_endpoints' => [],
        ];

        $this->syncer->syncFromManifest('test-agent', $manifest);
    }

    public function testSyncFromManifestSkipsInvalidEndpoints(): void
    {
        $this->connection->method('fetchOne')->willReturn('uuid-1234');

        $this->repository->expects($this->once())
            ->method('syncForAgent')
            ->with('uuid-1234', [
                ['path' => '/valid', 'methods' => ['GET'], 'description' => null],
            ]);

        $manifest = [
            'name' => 'test-agent',
            'version' => '1.0.0',
            'public_endpoints' => [
                'not-an-array',
                ['path' => '/valid', 'methods' => ['GET']],
                ['path' => '', 'methods' => ['POST']],
                ['path' => '/no-methods', 'methods' => []],
            ],
        ];

        $this->syncer->syncFromManifest('test-agent', $manifest);
    }

    public function testSyncFromManifestLogsWarningWhenAgentNotFound(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        $this->repository->expects($this->never())->method('syncForAgent');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('AgentPublicEndpointSyncer: agent not found in registry', $this->anything());

        $this->syncer->syncFromManifest('unknown-agent', ['name' => 'unknown-agent', 'version' => '1.0.0']);
    }

    public function testSyncFromManifestWithMalformedPublicEndpointsField(): void
    {
        $this->connection->method('fetchOne')->willReturn('uuid-1234');

        $this->repository->expects($this->once())
            ->method('syncForAgent')
            ->with('uuid-1234', []);

        $manifest = [
            'name' => 'test-agent',
            'version' => '1.0.0',
            'public_endpoints' => 'not-an-array',
        ];

        $this->syncer->syncFromManifest('test-agent', $manifest);
    }
}
