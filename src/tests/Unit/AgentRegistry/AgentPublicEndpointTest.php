<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentRegistry;

use App\AgentRegistry\AgentPublicEndpoint;
use Codeception\Test\Unit;

final class AgentPublicEndpointTest extends Unit
{
    public function testFromRowParsesJsonMethods(): void
    {
        $row = [
            'id' => '1',
            'agent_id' => 'uuid-1234',
            'agent_name' => 'test-agent',
            'path' => '/webhook/telegram',
            'methods' => '["POST","GET"]',
            'description' => 'Telegram webhook',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];

        $endpoint = AgentPublicEndpoint::fromRow($row);

        $this->assertSame(1, $endpoint->id);
        $this->assertSame('uuid-1234', $endpoint->agentId);
        $this->assertSame('test-agent', $endpoint->agentName);
        $this->assertSame('/webhook/telegram', $endpoint->path);
        $this->assertSame(['POST', 'GET'], $endpoint->methods);
        $this->assertSame('Telegram webhook', $endpoint->description);
    }

    public function testFromRowHandlesArrayMethods(): void
    {
        $row = [
            'id' => '2',
            'agent_id' => 'uuid-5678',
            'agent_name' => 'other-agent',
            'path' => '/callback',
            'methods' => ['POST'],
            'description' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];

        $endpoint = AgentPublicEndpoint::fromRow($row);

        $this->assertSame(['POST'], $endpoint->methods);
        $this->assertNull($endpoint->description);
    }

    public function testFromRowHandlesNullDescription(): void
    {
        $row = [
            'id' => '3',
            'agent_id' => 'uuid-9999',
            'agent_name' => 'agent',
            'path' => '/health',
            'methods' => '["GET"]',
            'description' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];

        $endpoint = AgentPublicEndpoint::fromRow($row);

        $this->assertNull($endpoint->description);
    }

    public function testFromRowCastsIdToInt(): void
    {
        $row = [
            'id' => '42',
            'agent_id' => 'uuid-42',
            'agent_name' => 'agent',
            'path' => '/test',
            'methods' => '["DELETE"]',
            'description' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];

        $endpoint = AgentPublicEndpoint::fromRow($row);

        $this->assertSame(42, $endpoint->id);
    }
}
