<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HealthController;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class HealthControllerTest extends Unit
{
    private Connection&MockObject $connection;
    private LoggerInterface&MockObject $logger;
    private HealthController $controller;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = new HealthController($this->connection, $this->logger);
    }

    public function testReturnsOkResponse(): void
    {
        $response = $this->controller->health();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        /** @var array{status: string, service: string, version: string, timestamp: string} $data */
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame('ok', $data['status']);
        $this->assertSame('core-platform', $data['service']);
        $this->assertSame('0.1.0', $data['version']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testResponseContentTypeIsJson(): void
    {
        $response = $this->controller->health();

        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }
}
