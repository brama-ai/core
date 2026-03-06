<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging\DTO;

use App\Logging\DTO\EventDetails;
use App\Logging\DTO\SequenceEvent;
use Codeception\Test\Unit;

final class SequenceEventTest extends Unit
{
    public function testFromArrayWithFullData(): void
    {
        $event = SequenceEvent::fromArray([
            'id' => 'evt-001',
            'event_name' => 'core.a2a.outbound.started',
            'step' => 'a2a_outbound',
            'operation' => 'hello.greet',
            'from' => 'core',
            'to' => 'hello-agent',
            'status' => 'completed',
            'duration_ms' => 150,
            'timestamp' => '2026-03-06T10:00:00+00:00',
            'trace_id' => 'trace-abc',
            'request_id' => 'req-123',
            'details' => [
                'input' => ['name' => 'World'],
                'output' => ['greeting' => 'Hello, World!'],
                'http_status_code' => 200,
            ],
        ]);

        $this->assertSame('evt-001', $event->id);
        $this->assertSame('core.a2a.outbound.started', $event->eventName);
        $this->assertSame('a2a_outbound', $event->step);
        $this->assertSame('hello.greet', $event->operation);
        $this->assertSame('core', $event->from);
        $this->assertSame('hello-agent', $event->to);
        $this->assertSame('completed', $event->status);
        $this->assertSame(150, $event->durationMs);
        $this->assertSame('trace-abc', $event->traceId);
        $this->assertSame('req-123', $event->requestId);
        $this->assertInstanceOf(EventDetails::class, $event->details);
        $this->assertSame(['name' => 'World'], $event->details->input);
        $this->assertSame(200, $event->details->httpStatusCode);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $event = SequenceEvent::fromArray([]);

        $this->assertSame('', $event->id);
        $this->assertSame('', $event->eventName);
        $this->assertSame('', $event->from);
        $this->assertSame('', $event->to);
        $this->assertSame('', $event->status);
        $this->assertSame(0, $event->durationMs);
        $this->assertInstanceOf(EventDetails::class, $event->details);
    }

    public function testFromArrayWithNonArrayDetails(): void
    {
        $event = SequenceEvent::fromArray([
            'id' => 'evt-002',
            'event_name' => 'test',
            'step' => 'test',
            'operation' => 'test',
            'from' => 'a',
            'to' => 'b',
            'status' => 'started',
            'details' => 'not-an-array',
        ]);

        $this->assertInstanceOf(EventDetails::class, $event->details);
        $this->assertNull($event->details->input);
    }

    public function testToArrayRoundtrip(): void
    {
        $input = [
            'id' => 'evt-003',
            'event_name' => 'core.llm.call.completed',
            'step' => 'llm_call',
            'operation' => 'chat',
            'from' => 'hello-agent',
            'to' => 'openai',
            'status' => 'completed',
            'duration_ms' => 500,
            'timestamp' => '2026-03-06T12:00:00+00:00',
            'trace_id' => 'trace-xyz',
            'request_id' => 'req-456',
        ];

        $event = SequenceEvent::fromArray($input);
        $output = $event->toArray();

        $this->assertSame($input['id'], $output['id']);
        $this->assertSame($input['event_name'], $output['event_name']);
        $this->assertSame($input['step'], $output['step']);
        $this->assertSame($input['operation'], $output['operation']);
        $this->assertSame($input['from'], $output['from']);
        $this->assertSame($input['to'], $output['to']);
        $this->assertSame($input['status'], $output['status']);
        $this->assertSame($input['duration_ms'], $output['duration_ms']);
        $this->assertSame($input['trace_id'], $output['trace_id']);
        $this->assertSame($input['request_id'], $output['request_id']);
        $this->assertIsArray($output['details']);
    }
}
