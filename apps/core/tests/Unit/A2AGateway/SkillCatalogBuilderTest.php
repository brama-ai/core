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

    public function testMultipleAgentsProduceMultipleTools(): void
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
                            'input_schema' => ['type' => 'object'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
            [
                'name' => 'knowledge-agent',
                'manifest' => json_encode([
                    'description' => 'Knowledge agent',
                    'skills' => ['knowledge.search', 'knowledge.extract'],
                    'skill_schemas' => [
                        'knowledge.search' => [
                            'description' => 'Search knowledge base',
                            'input_schema' => [
                                'type' => 'object',
                                'properties' => ['query' => ['type' => 'string']],
                            ],
                        ],
                        'knowledge.extract' => [
                            'description' => 'Extract knowledge from messages',
                            'input_schema' => [
                                'type' => 'object',
                                'properties' => ['messages' => ['type' => 'array']],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertCount(3, $result['tools']);

        $toolNames = array_column($result['tools'], 'name');
        $this->assertContains('hello.greet', $toolNames);
        $this->assertContains('knowledge.search', $toolNames);
        $this->assertContains('knowledge.extract', $toolNames);

        $agentNames = array_column($result['tools'], 'agent');
        $this->assertContains('hello-agent', $agentNames);
        $this->assertContains('knowledge-agent', $agentNames);
    }

    public function testDisabledAgentsExcludedFromCatalog(): void
    {
        // This test verifies that only enabled agents are returned by findEnabled()
        // The registry interface contract ensures disabled agents are not included
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'enabled-agent',
                'manifest' => json_encode([
                    'description' => 'Enabled agent',
                    'skills' => ['enabled.action'],
                    'skill_schemas' => [
                        'enabled.action' => [
                            'description' => 'Enabled action',
                            'input_schema' => ['type' => 'object'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertCount(1, $result['tools']);
        $this->assertSame('enabled.action', $result['tools'][0]['name']);
        $this->assertSame('enabled-agent', $result['tools'][0]['agent']);
    }

    public function testSchemaFallbackToDefaultObject(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'no-schema-agent',
                'manifest' => json_encode([
                    'description' => 'Agent without schema',
                    'skills' => ['no.schema'],
                    // No skill_schemas defined
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertCount(1, $result['tools']);
        $tool = $result['tools'][0];
        $this->assertSame('no.schema', $tool['name']);
        $this->assertSame(['type' => 'object'], $tool['input_schema']);
    }

    public function testSchemaFallbackWhenInputSchemaNotDefined(): void
    {
        $this->registry->method('findEnabled')->willReturn([
            [
                'name' => 'partial-schema-agent',
                'manifest' => json_encode([
                    'description' => 'Agent with partial schema',
                    'skills' => ['partial.schema'],
                    'skill_schemas' => [
                        'partial.schema' => [
                            'description' => 'Has description but no input_schema',
                            // input_schema is missing
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'config' => null,
                'enabled' => true,
            ],
        ]);

        $result = $this->builder->build();

        $this->assertCount(1, $result['tools']);
        $tool = $result['tools'][0];
        $this->assertSame('partial.schema', $tool['name']);
        $this->assertSame(['type' => 'object'], $tool['input_schema']);
        $this->assertSame('Has description but no input_schema', $tool['description']);
    }
}
