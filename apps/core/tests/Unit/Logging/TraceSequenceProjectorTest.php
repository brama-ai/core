<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\TraceSequenceProjector;
use Codeception\Test\Unit;

final class TraceSequenceProjectorTest extends Unit
{
    public function testProjectBuildsSequenceEventsAndParticipants(): void
    {
        $projector = new TraceSequenceProjector();

        $projection = $projector->project([
            [
                '@timestamp' => '2026-03-06T09:59:59Z',
                'event_name' => 'core.invoke.tool_resolved',
                'step' => 'tool_resolve',
                'source_app' => 'core',
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'status' => 'completed',
                'trace_id' => 'trace-1',
                'request_id' => 'req-1',
            ],
            [
                '@timestamp' => '2026-03-06T10:00:00Z',
                'event_name' => 'core.a2a.outbound.started',
                'step' => 'a2a_outbound',
                'source_app' => 'core',
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'status' => 'started',
                'trace_id' => 'trace-1',
                'request_id' => 'req-1',
                'context' => [
                    'step_input' => ['name' => 'Dima'],
                ],
            ],
            [
                '@timestamp' => '2026-03-06T10:00:01Z',
                'message' => 'Non-structured log',
            ],
        ]);

        $this->assertCount(2, $projection['events']);
        $this->assertSame('tool_resolve', $projection['events'][0]['step']);
        $this->assertSame('a2a_outbound', $projection['events'][1]['step']);
        $this->assertSame(['core', 'hello-agent'], $projection['participants']);
        $this->assertCount(1, $projection['call_events']);
        $this->assertSame('a2a_outbound', $projection['call_events'][0]['step']);
        $this->assertSame(['core', 'hello-agent'], $projection['call_participants']);
    }

    public function testAgentCardFetchIsCallStep(): void
    {
        $projector = new TraceSequenceProjector();

        $projection = $projector->project([
            [
                '@timestamp' => '2026-03-06T10:00:00Z',
                'event_name' => 'core.agent_card.fetch_completed',
                'step' => 'agent_card_fetch',
                'source_app' => 'core',
                'target_app' => 'hello-agent',
                'status' => 'completed',
                'duration_ms' => 42,
                'trace_id' => 'trace-1',
                'request_id' => 'req-1',
                'http_status_code' => 200,
                'task_id' => 'task-abc',
            ],
        ]);

        $this->assertCount(1, $projection['call_events']);
        $this->assertSame('agent_card_fetch', $projection['call_events'][0]['step']);
        $this->assertSame(200, $projection['call_events'][0]['details']['http_status_code']);
        $this->assertSame('task-abc', $projection['call_events'][0]['details']['task_id']);
    }
}
