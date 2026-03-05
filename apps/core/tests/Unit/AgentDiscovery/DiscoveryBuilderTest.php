<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentDiscovery;

use App\AgentDiscovery\DiscoveryBuilder;
use App\AgentRegistry\AgentRegistryInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class DiscoveryBuilderTest extends Unit
{
    private AgentRegistryInterface&MockObject $registry;
    private DiscoveryBuilder $builder;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistryInterface::class);
        $this->builder = new DiscoveryBuilder($this->registry);
    }

    public function testConfigDescriptionOverridesCapabilityDescription(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'test-agent',
                'manifest' => json_encode([
                    'description' => 'Manifest description',
                    'capabilities' => ['test.action'],
                    'capability_schemas' => [
                        'test.action' => [
                            'description' => 'Schema description',
                            'input_schema' => ['type' => 'object'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => json_encode(['description' => 'Custom config description'], JSON_THROW_ON_ERROR),
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertCount(1, $result['tools']);
        $this->assertSame('Custom config description', $result['tools'][0]['description']);
    }

    public function testCapabilitySchemaDescriptionUsedWhenNoConfig(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'test-agent',
                'manifest' => json_encode([
                    'description' => 'Manifest description',
                    'capabilities' => ['test.action'],
                    'capability_schemas' => [
                        'test.action' => [
                            'description' => 'Schema description',
                            'input_schema' => ['type' => 'object'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => json_encode([], JSON_THROW_ON_ERROR),
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertSame('Schema description', $result['tools'][0]['description']);
    }

    public function testManifestDescriptionAsFallback(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'test-agent',
                'manifest' => json_encode([
                    'description' => 'Manifest description',
                    'capabilities' => ['test.action'],
                    'capability_schemas' => [
                        'test.action' => [
                            'input_schema' => ['type' => 'object'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertSame('Manifest description', $result['tools'][0]['description']);
    }

    public function testEmptyAgentsReturnsEmptyTools(): void
    {
        $this->registry->method('findEnabled')->willReturn([]);

        $result = $this->builder->build();

        $this->assertSame([], $result['tools']);
        $this->assertSame('0.1.0', $result['platform_version']);
        $this->assertArrayHasKey('generated_at', $result);
    }

    public function testToolContainsAgentNameAndInputSchema(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'hello-agent',
                'manifest' => json_encode([
                    'description' => 'Hello agent',
                    'capabilities' => ['hello.greet'],
                    'capability_schemas' => [
                        'hello.greet' => [
                            'description' => 'Greet a user',
                            'input_schema' => [
                                'type' => 'object',
                                'properties' => ['name' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();
        $tool = $result['tools'][0];

        $this->assertSame('hello.greet', $tool['name']);
        $this->assertSame('hello-agent', $tool['agent']);
        $this->assertSame('Greet a user', $tool['description']);
        $this->assertArrayHasKey('properties', $tool['input_schema']);
    }
}
