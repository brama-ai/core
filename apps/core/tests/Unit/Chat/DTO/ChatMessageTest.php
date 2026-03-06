<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\DTO;

use App\Chat\DTO\ChatMessage;
use Codeception\Test\Unit;

final class ChatMessageTest extends Unit
{
    public function testInboundMessage(): void
    {
        $msg = new ChatMessage(
            direction: 'inbound',
            timestamp: '2026-03-06T12:00:00Z',
            eventName: 'openclaw.message.received',
            traceId: 'trace_123',
            sender: 'user_456',
            recipient: null,
            tool: null,
            status: 'completed',
            durationMs: null,
            payload: ['text' => 'Hello'],
        );

        $this->assertSame('inbound', $msg->direction);
        $this->assertSame('openclaw.message.received', $msg->eventName);
        $this->assertSame('trace_123', $msg->traceId);
        $this->assertSame('user_456', $msg->sender);
        $this->assertNull($msg->recipient);
        $this->assertNull($msg->tool);
        $this->assertSame(['text' => 'Hello'], $msg->payload);
    }

    public function testToolCallMessage(): void
    {
        $msg = new ChatMessage(
            direction: 'tool_call',
            timestamp: '2026-03-06T12:00:01Z',
            eventName: 'openclaw.tool.execute.started',
            traceId: 'trace_789',
            sender: null,
            recipient: null,
            tool: 'hello.greet',
            status: 'started',
            durationMs: null,
            payload: ['name' => 'World'],
        );

        $this->assertSame('tool_call', $msg->direction);
        $this->assertSame('hello.greet', $msg->tool);
        $this->assertSame('started', $msg->status);
    }

    public function testToolResultMessage(): void
    {
        $msg = new ChatMessage(
            direction: 'tool_result',
            timestamp: '2026-03-06T12:00:02Z',
            eventName: 'openclaw.tool.execute.completed',
            traceId: 'trace_789',
            sender: null,
            recipient: null,
            tool: 'hello.greet',
            status: 'completed',
            durationMs: 150,
            payload: ['greeting' => 'Hello, World!'],
        );

        $this->assertSame('tool_result', $msg->direction);
        $this->assertSame(150, $msg->durationMs);
        $this->assertSame('completed', $msg->status);
    }

    public function testEmptyPayload(): void
    {
        $msg = new ChatMessage(
            direction: 'outbound',
            timestamp: '2026-03-06T12:00:03Z',
            eventName: 'openclaw.message.sent',
            traceId: null,
            sender: null,
            recipient: 'user_456',
            tool: null,
            status: null,
            durationMs: null,
            payload: [],
        );

        $this->assertSame([], $msg->payload);
        $this->assertNull($msg->traceId);
    }
}
