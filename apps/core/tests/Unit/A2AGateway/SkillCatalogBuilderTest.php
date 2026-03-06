<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway;

use App\A2AGateway\SkillCatalogBuilder;
use App\AgentRegistry\AgentRegistryInterface;
use App\AgentRegistry\ManifestValidator;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class SkillCatalogBuilderTest extends Unit
{
    private AgentRegistryInterface&MockObject $registry;
    private SkillCatalogBuilder $builder;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(AgentRegistryInterface::class);
        $this->builder = new SkillCatalogBuilder($this->registry, new ManifestValidator());
    }

    public function testConfigDescriptionOverridesSkillDescription(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'test-agent',
                'manifest' => json_encode([
                    'description' => 'Manifest description',
                    'skills' => ['test.action'],
                    'skill_schemas' => [
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

    public function testSkillSchemaDescriptionUsedWhenNoConfig(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'test-agent',
                'manifest' => json_encode([
                    'description' => 'Manifest description',
                    'skills' => ['test.action'],
                    'skill_schemas' => [
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
                    'skills' => ['test.action'],
                    'skill_schemas' => [
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
                    'skills' => ['hello.greet'],
                    'skill_schemas' => [
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

    public function testStructuredSkillsProduceCorrectTools(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'hello-agent',
                'manifest' => json_encode([
                    'description' => 'Hello agent',
                    'skills' => [
                        [
                            'id' => 'hello.greet',
                            'name' => 'Hello Greet',
                            'description' => 'Greet a user by name',
                            'tags' => ['greeting'],
                        ],
                    ],
                    'skill_schemas' => [
                        'hello.greet' => [
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
        $this->assertSame('Greet a user by name', $tool['description']);
        $this->assertSame(['greeting'], $tool['tags']);
        $this->assertArrayHasKey('properties', $tool['input_schema']);
    }
}
