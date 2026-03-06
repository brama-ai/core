<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentRegistry\DTO;

use App\A2A\DTO\AgentCard;
use App\AgentRegistry\DTO\HealthStatus;
use App\AgentRegistry\DTO\RegisteredAgent;
use Codeception\Test\Unit;

final class RegisteredAgentTest extends Unit
{
    public function testFromDatabaseRowWithJsonStrings(): void
    {
        $row = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'hello-agent',
            'version' => '1.0.0',
            'manifest' => json_encode([
                'name' => 'hello-agent',
                'version' => '1.0.0',
                'description' => 'A hello world agent',
                'url' => 'http://hello-agent/api/v1/a2a',
            ]),
            'config' => json_encode(['timeout' => 30]),
            'violations' => json_encode(['Missing health_url']),
            'enabled' => true,
            'health_status' => 'healthy',
            'health_check_failures' => 0,
            'registered_at' => '2026-01-15T10:00:00+00:00',
            'updated_at' => '2026-03-01T12:00:00+00:00',
            'enabled_at' => '2026-01-15T10:05:00+00:00',
            'disabled_at' => null,
            'enabled_by' => 'admin',
            'installed_at' => '2026-01-15T10:01:00+00:00',
        ];

        $agent = RegisteredAgent::fromDatabaseRow($row);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $agent->id);
        $this->assertSame('hello-agent', $agent->name);
        $this->assertSame('1.0.0', $agent->version);
        $this->assertInstanceOf(AgentCard::class, $agent->manifest);
        $this->assertSame('hello-agent', $agent->manifest->name);
        $this->assertSame('http://hello-agent/api/v1/a2a', $agent->manifest->url);
        $this->assertSame(['timeout' => 30], $agent->config);
        $this->assertSame(['Missing health_url'], $agent->violations);
        $this->assertTrue($agent->enabled);
        $this->assertSame(HealthStatus::Healthy, $agent->healthStatus);
        $this->assertSame(0, $agent->healthCheckFailures);
        $this->assertInstanceOf(\DateTimeImmutable::class, $agent->registeredAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $agent->updatedAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $agent->enabledAt);
        $this->assertNull($agent->disabledAt);
        $this->assertSame('admin', $agent->enabledBy);
        $this->assertInstanceOf(\DateTimeImmutable::class, $agent->installedAt);
    }

    public function testFromDatabaseRowWithDecodedArrays(): void
    {
        $row = [
            'id' => 'test-id',
            'name' => 'test-agent',
            'version' => '0.1.0',
            'manifest' => ['name' => 'test-agent', 'version' => '0.1.0'],
            'config' => ['key' => 'value'],
            'violations' => [],
            'enabled' => false,
        ];

        $agent = RegisteredAgent::fromDatabaseRow($row);

        $this->assertSame('test-agent', $agent->manifest->name);
        $this->assertSame(['key' => 'value'], $agent->config);
        $this->assertSame([], $agent->violations);
        $this->assertFalse($agent->enabled);
    }

    public function testFromDatabaseRowWithMinimalData(): void
    {
        $agent = RegisteredAgent::fromDatabaseRow([]);

        $this->assertSame('', $agent->id);
        $this->assertSame('', $agent->name);
        $this->assertSame('', $agent->version);
        $this->assertInstanceOf(AgentCard::class, $agent->manifest);
        $this->assertSame([], $agent->config);
        $this->assertSame([], $agent->violations);
        $this->assertFalse($agent->enabled);
        $this->assertSame(HealthStatus::Unknown, $agent->healthStatus);
        $this->assertSame(0, $agent->healthCheckFailures);
        $this->assertNull($agent->registeredAt);
    }

    public function testFromDatabaseRowWithUnknownHealthStatus(): void
    {
        $agent = RegisteredAgent::fromDatabaseRow(['health_status' => 'nonexistent']);

        $this->assertSame(HealthStatus::Unknown, $agent->healthStatus);
    }

    public function testToArrayRoundtrip(): void
    {
        $row = [
            'id' => 'roundtrip-id',
            'name' => 'test-agent',
            'version' => '2.0.0',
            'manifest' => json_encode(['name' => 'test-agent', 'version' => '2.0.0']),
            'config' => json_encode([]),
            'violations' => json_encode([]),
            'enabled' => true,
            'health_status' => 'degraded',
            'health_check_failures' => 2,
            'registered_at' => '2026-02-01T00:00:00+00:00',
        ];

        $agent = RegisteredAgent::fromDatabaseRow($row);
        $output = $agent->toArray();

        $this->assertSame('roundtrip-id', $output['id']);
        $this->assertSame('test-agent', $output['name']);
        $this->assertSame('2.0.0', $output['version']);
        $this->assertIsArray($output['manifest']);
        $this->assertTrue($output['enabled']);
        $this->assertSame('degraded', $output['health_status']);
        $this->assertSame(2, $output['health_check_failures']);
        $this->assertSame('2026-02-01T00:00:00+00:00', $output['registered_at']);
    }
}
