<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A\DTO;

use App\A2A\DTO\AgentSkill;
use Codeception\Test\Unit;

final class AgentSkillTest extends Unit
{
    public function testFromArrayWithAllFields(): void
    {
        $skill = AgentSkill::fromArray([
            'id' => 'hello.greet',
            'name' => 'Hello Greet',
            'description' => 'Greet a user by name',
            'tags' => ['greeting', 'hello'],
            'examples' => ['Greet John', 'Say hello'],
            'inputModes' => ['text'],
            'outputModes' => ['text'],
        ]);

        $this->assertSame('hello.greet', $skill->id);
        $this->assertSame('Hello Greet', $skill->name);
        $this->assertSame('Greet a user by name', $skill->description);
        $this->assertSame(['greeting', 'hello'], $skill->tags);
        $this->assertSame(['Greet John', 'Say hello'], $skill->examples);
        $this->assertSame(['text'], $skill->inputModes);
    }

    public function testFromArrayWithMinimalFields(): void
    {
        $skill = AgentSkill::fromArray(['id' => 'test.skill']);

        $this->assertSame('test.skill', $skill->id);
        $this->assertSame('test.skill', $skill->name);
        $this->assertSame('', $skill->description);
        $this->assertSame([], $skill->tags);
    }

    public function testToArrayOmitsEmptyOptionalFields(): void
    {
        $skill = new AgentSkill(id: 'test', name: 'Test', description: 'Desc');
        $array = $skill->toArray();

        $this->assertSame('test', $array['id']);
        $this->assertSame('Test', $array['name']);
        $this->assertArrayNotHasKey('tags', $array);
        $this->assertArrayNotHasKey('examples', $array);
    }

    public function testToArrayIncludesNonEmptyOptionalFields(): void
    {
        $skill = new AgentSkill(id: 'test', name: 'Test', description: 'Desc', tags: ['a']);
        $array = $skill->toArray();

        $this->assertSame(['a'], $array['tags']);
    }

    public function testRoundtrip(): void
    {
        $data = [
            'id' => 'knowledge.search',
            'name' => 'Knowledge Search',
            'description' => 'Search the knowledge base',
            'tags' => ['search', 'knowledge'],
        ];

        $skill = AgentSkill::fromArray($data);
        $output = $skill->toArray();

        $this->assertSame($data['id'], $output['id']);
        $this->assertSame($data['name'], $output['name']);
        $this->assertSame($data['description'], $output['description']);
        $this->assertSame($data['tags'], $output['tags']);
    }
}
