<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A\DTO;

use App\A2A\DTO\AgentCapabilities;
use App\A2A\DTO\AgentCard;
use App\A2A\DTO\AgentProvider;
use Codeception\Test\Unit;

final class AgentCardTest extends Unit
{
    public function testFromArrayWithFullManifest(): void
    {
        $data = [
            'name' => 'hello-agent',
            'version' => '1.0.0',
            'description' => 'Simple hello-world agent',
            'url' => 'http://hello-agent/api/v1/a2a',
            'provider' => ['organization' => 'ACP', 'url' => 'https://example.com'],
            'capabilities' => ['streaming' => false, 'pushNotifications' => false],
            'skills' => [
                ['id' => 'hello.greet', 'name' => 'Hello Greet', 'description' => 'Greet a user', 'tags' => ['greeting']],
            ],
            'skill_schemas' => [
                'hello.greet' => ['description' => 'Greet', 'input_schema' => ['type' => 'object']],
            ],
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'permissions' => [],
            'commands' => ['/hello'],
            'events' => [],
            'health_url' => 'http://hello-agent/health',
        ];

        $card = AgentCard::fromArray($data);

        $this->assertSame('hello-agent', $card->name);
        $this->assertSame('1.0.0', $card->version);
        $this->assertSame('Simple hello-world agent', $card->description);
        $this->assertSame('http://hello-agent/api/v1/a2a', $card->url);
        $this->assertInstanceOf(AgentProvider::class, $card->provider);
        $this->assertSame('ACP', $card->provider->organization);
        $this->assertInstanceOf(AgentCapabilities::class, $card->capabilities);
        $this->assertFalse($card->capabilities->streaming);
        $this->assertCount(1, $card->skills);
        $this->assertSame('hello.greet', $card->skills[0]->id);
        $this->assertSame(['text'], $card->defaultInputModes);
        $this->assertSame(['/hello'], $card->commands);
        $this->assertSame('http://hello-agent/health', $card->healthUrl);
    }

    public function testFromArrayWithMinimalManifest(): void
    {
        $card = AgentCard::fromArray(['name' => 'test', 'version' => '0.1.0']);

        $this->assertSame('test', $card->name);
        $this->assertSame('0.1.0', $card->version);
        $this->assertSame('', $card->description);
        $this->assertSame('', $card->url);
        $this->assertNull($card->provider);
        $this->assertNull($card->capabilities);
        $this->assertSame([], $card->skills);
        $this->assertSame([], $card->commands);
    }

    public function testFromArrayNormalizesLegacyA2aEndpoint(): void
    {
        $card = AgentCard::fromArray([
            'name' => 'legacy',
            'version' => '1.0.0',
            'a2a_endpoint' => 'http://legacy/a2a',
        ]);

        $this->assertSame('http://legacy/a2a', $card->url);
        $this->assertSame('http://legacy/a2a', $card->resolvedUrl());
        $this->assertSame('http://legacy/a2a', $card->extra['a2a_endpoint']);
    }

    public function testFromArrayPrefersUrlOverA2aEndpoint(): void
    {
        $card = AgentCard::fromArray([
            'name' => 'both',
            'version' => '1.0.0',
            'url' => 'http://new/a2a',
            'a2a_endpoint' => 'http://old/a2a',
        ]);

        $this->assertSame('http://new/a2a', $card->url);
        $this->assertSame('http://new/a2a', $card->resolvedUrl());
    }

    public function testFromArrayConvertsStringSkills(): void
    {
        $card = AgentCard::fromArray([
            'name' => 'test',
            'version' => '1.0.0',
            'skills' => ['hello.greet', 'hello.farewell'],
            'skill_schemas' => [
                'hello.greet' => ['description' => 'Greet someone'],
            ],
        ]);

        $this->assertCount(2, $card->skills);
        $this->assertSame('hello.greet', $card->skills[0]->id);
        $this->assertSame('Greet someone', $card->skills[0]->description);
        $this->assertSame('hello.farewell', $card->skills[1]->id);
        $this->assertSame('', $card->skills[1]->description);
    }

    public function testFromArrayHandlesMixedSkills(): void
    {
        $card = AgentCard::fromArray([
            'name' => 'test',
            'version' => '1.0.0',
            'skills' => [
                'legacy.skill',
                ['id' => 'new.skill', 'name' => 'New Skill', 'description' => 'A new skill'],
            ],
        ]);

        $this->assertCount(2, $card->skills);
        $this->assertSame('legacy.skill', $card->skills[0]->id);
        $this->assertSame('new.skill', $card->skills[1]->id);
        $this->assertSame('A new skill', $card->skills[1]->description);
    }

    public function testSkillIdsExtractsAllIds(): void
    {
        $card = AgentCard::fromArray([
            'name' => 'test',
            'version' => '1.0.0',
            'skills' => [
                ['id' => 'a.one', 'name' => 'A', 'description' => 'A'],
                ['id' => 'b.two', 'name' => 'B', 'description' => 'B'],
            ],
        ]);

        $this->assertSame(['a.one', 'b.two'], $card->skillIds());
    }

    public function testToArrayRoundtrip(): void
    {
        $data = [
            'name' => 'hello-agent',
            'version' => '1.0.0',
            'description' => 'Test agent',
            'url' => 'http://hello-agent/a2a',
            'provider' => ['organization' => 'ACP', 'url' => 'https://example.com'],
            'capabilities' => ['streaming' => false, 'pushNotifications' => false, 'stateTransitionHistory' => false],
            'skills' => [
                ['id' => 'hello.greet', 'name' => 'Hello Greet', 'description' => 'Greet'],
            ],
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'permissions' => ['admin'],
            'commands' => ['/hello'],
            'events' => ['message.created'],
            'health_url' => 'http://hello-agent/health',
        ];

        $card = AgentCard::fromArray($data);
        $output = $card->toArray();

        $this->assertSame('hello-agent', $output['name']);
        $this->assertSame('1.0.0', $output['version']);
        $this->assertSame('http://hello-agent/a2a', $output['url']);
        $this->assertSame('ACP', $output['provider']['organization']);
        $this->assertSame('hello.greet', $output['skills'][0]['id']);
        $this->assertSame(['/hello'], $output['commands']);
    }

    public function testExtraFieldsPreserved(): void
    {
        $card = AgentCard::fromArray([
            'name' => 'test',
            'version' => '1.0.0',
            'custom_field' => 'custom_value',
        ]);

        $this->assertSame('custom_value', $card->extra['custom_field']);
        $this->assertSame('custom_value', $card->toArray()['custom_field']);
    }
}
