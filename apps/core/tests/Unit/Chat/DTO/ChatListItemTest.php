<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat\DTO;

use App\Chat\DTO\ChatListItem;
use Codeception\Test\Unit;

final class ChatListItemTest extends Unit
{
    public function testConstructSetsAllProperties(): void
    {
        $item = new ChatListItem(
            sessionKey: 'trace_abc123',
            channel: 'openclaw',
            sender: 'hello-agent',
            messageCount: 5,
            lastMessageAt: '2026-03-06 12:00:00',
            firstMessageAt: '2026-03-06 11:00:00',
            traceIds: ['trace_abc123'],
            agent: 'hello-agent',
            skill: 'hello.greet',
            status: 'completed',
            durationMs: 150,
        );

        $this->assertSame('trace_abc123', $item->sessionKey);
        $this->assertSame('openclaw', $item->channel);
        $this->assertSame('hello-agent', $item->sender);
        $this->assertSame(5, $item->messageCount);
        $this->assertSame('2026-03-06 12:00:00', $item->lastMessageAt);
        $this->assertSame('2026-03-06 11:00:00', $item->firstMessageAt);
        $this->assertSame(['trace_abc123'], $item->traceIds);
        $this->assertSame('hello-agent', $item->agent);
        $this->assertSame('hello.greet', $item->skill);
        $this->assertSame('completed', $item->status);
        $this->assertSame(150, $item->durationMs);
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $item = new ChatListItem(
            sessionKey: 'trace_xyz',
            channel: 'openclaw',
            sender: 'unknown',
            messageCount: 0,
            lastMessageAt: '',
            firstMessageAt: '',
            traceIds: [],
        );

        $this->assertNull($item->agent);
        $this->assertNull($item->skill);
        $this->assertNull($item->status);
        $this->assertNull($item->durationMs);
        $this->assertSame([], $item->traceIds);
        $this->assertSame(0, $item->messageCount);
    }
}
