<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A\DTO;

use App\A2A\DTO\A2ARequest;
use Codeception\Test\Unit;

final class A2ARequestTest extends Unit
{
    public function testFromArrayWithFullData(): void
    {
        $request = A2ARequest::fromArray([
            'intent' => 'hello.greet',
            'payload' => ['name' => 'World'],
            'request_id' => 'req-123',
            'trace_id' => 'trace-456',
            'system_prompt' => 'Be friendly',
        ]);

        $this->assertSame('hello.greet', $request->intent);
        $this->assertSame(['name' => 'World'], $request->payload);
        $this->assertSame('req-123', $request->requestId);
        $this->assertSame('trace-456', $request->traceId);
        $this->assertSame('Be friendly', $request->systemPrompt);
    }

    public function testFromArrayWithDefaults(): void
    {
        $request = A2ARequest::fromArray(['intent' => 'test']);

        $this->assertSame('test', $request->intent);
        $this->assertSame([], $request->payload);
        $this->assertSame('', $request->requestId);
        $this->assertSame('', $request->traceId);
        $this->assertNull($request->systemPrompt);
    }

    public function testToArrayRoundtrip(): void
    {
        $data = [
            'intent' => 'hello.greet',
            'payload' => ['name' => 'Test'],
            'request_id' => 'req-1',
            'trace_id' => 'trace-1',
        ];

        $request = A2ARequest::fromArray($data);
        $output = $request->toArray();

        $this->assertSame($data['intent'], $output['intent']);
        $this->assertSame($data['payload'], $output['payload']);
        $this->assertSame($data['request_id'], $output['request_id']);
        $this->assertArrayNotHasKey('system_prompt', $output);
    }

    public function testToArrayIncludesSystemPromptWhenSet(): void
    {
        $request = A2ARequest::fromArray([
            'intent' => 'test',
            'system_prompt' => 'Custom prompt',
        ]);

        $output = $request->toArray();
        $this->assertSame('Custom prompt', $output['system_prompt']);
    }
}
