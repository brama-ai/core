<?php

declare(strict_types=1);

namespace App\Tests\Integration\Logging;

use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use Codeception\Test\Unit;

/**
 * Integration coverage for invoke step event fields (task 5.3).
 *
 * Verifies that TraceEvent::build() + PayloadSanitizer produce the canonical
 * field structure emitted by SendMessageController and A2AClient for each
 * invoke step: received, tool_resolved, a2a_outbound, invoke_complete.
 */
final class InvokeStepEventFieldsTest extends Unit
{
    private PayloadSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new PayloadSanitizer();
    }

    public function testInvokeReceivedEventHasRequiredFields(): void
    {
        $input = ['name' => 'Dima', 'language' => 'uk'];
        $sanitizedInput = $this->sanitizer->sanitize($input);

        $event = TraceEvent::build(
            'core.invoke.received',
            'invoke_receive',
            'core',
            'started',
            [
                'target_app' => 'openclaw',
                'tool' => 'hello.greet',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ],
        );

        $this->assertSame('core.invoke.received', $event['event_name']);
        $this->assertSame('invoke_receive', $event['step']);
        $this->assertSame('core', $event['source_app']);
        $this->assertSame('started', $event['status']);
        $this->assertSame('openclaw', $event['target_app']);
        $this->assertSame('hello.greet', $event['tool']);
        $this->assertSame('trace-abc-001', $event['trace_id']);
        $this->assertSame('req-xyz-001', $event['request_id']);
        $this->assertIsInt($event['sequence_order']);
        $this->assertGreaterThan(0, $event['sequence_order']);

        // Step input captured and sanitized
        $this->assertIsArray($event['step_input']);
        $this->assertSame('Dima', $event['step_input']['name']);

        // Capture metadata present
        $this->assertIsArray($event['capture_meta']);
        $this->assertArrayHasKey('is_truncated', $event['capture_meta']);
        $this->assertArrayHasKey('redacted_fields_count', $event['capture_meta']);
        $this->assertSame(0, $event['capture_meta']['redacted_fields_count']);
    }

    public function testInvokeReceivedEventRedactsSensitiveInput(): void
    {
        $input = [
            'name' => 'Dima',
            'token' => 'secret-bearer-token',
            'api_key' => 'sk-1234567890',
        ];
        $sanitizedInput = $this->sanitizer->sanitize($input);

        $event = TraceEvent::build(
            'core.invoke.received',
            'invoke_receive',
            'core',
            'started',
            [
                'target_app' => 'openclaw',
                'tool' => 'secure.action',
                'trace_id' => 'trace-sec-001',
                'request_id' => 'req-sec-001',
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ],
        );

        $this->assertSame('[REDACTED]', $event['step_input']['token']);
        $this->assertSame('[REDACTED]', $event['step_input']['api_key']);
        $this->assertSame('Dima', $event['step_input']['name']);
        $this->assertSame(2, $event['capture_meta']['redacted_fields_count']);
    }

    public function testToolResolvedEventHasRequiredFields(): void
    {
        $event = TraceEvent::build(
            'core.invoke.tool_resolved',
            'tool_resolve',
            'core',
            'completed',
            [
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
            ],
        );

        $this->assertSame('core.invoke.tool_resolved', $event['event_name']);
        $this->assertSame('tool_resolve', $event['step']);
        $this->assertSame('core', $event['source_app']);
        $this->assertSame('completed', $event['status']);
        $this->assertSame('hello-agent', $event['target_app']);
        $this->assertSame('hello.greet', $event['tool']);
        $this->assertSame('trace-abc-001', $event['trace_id']);
        $this->assertSame('req-xyz-001', $event['request_id']);
        $this->assertIsInt($event['sequence_order']);
    }

    public function testUnknownToolEventHasErrorCode(): void
    {
        $event = TraceEvent::build(
            'core.invoke.unknown_tool',
            'tool_resolve',
            'core',
            'failed',
            [
                'target_app' => 'unknown',
                'tool' => 'nonexistent.tool',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'error_code' => 'unknown_tool',
            ],
        );

        $this->assertSame('core.invoke.unknown_tool', $event['event_name']);
        $this->assertSame('tool_resolve', $event['step']);
        $this->assertSame('failed', $event['status']);
        $this->assertSame('unknown_tool', $event['error_code']);
        $this->assertSame('unknown', $event['target_app']);
    }

    public function testDisabledToolEventHasErrorCode(): void
    {
        $event = TraceEvent::build(
            'core.invoke.tool_disabled',
            'tool_resolve',
            'core',
            'failed',
            [
                'target_app' => 'disabled-agent',
                'tool' => 'disabled.skill',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'error_code' => 'agent_disabled',
            ],
        );

        $this->assertSame('core.invoke.tool_disabled', $event['event_name']);
        $this->assertSame('failed', $event['status']);
        $this->assertSame('agent_disabled', $event['error_code']);
    }

    public function testA2AOutboundStartedEventHasRequiredFields(): void
    {
        $payload = [
            'intent' => 'hello.greet',
            'payload' => ['name' => 'Dima'],
            'request_id' => 'req-xyz-001',
            'trace_id' => 'trace-abc-001',
            'agent_run_id' => 'run_abc123',
        ];
        $headers = [
            'traceparent' => '00-trace-abc-001-01',
            'x-request-id' => 'req-xyz-001',
            'x-agent-run-id' => 'run_abc123',
        ];

        $sanitizedInput = $this->sanitizer->sanitize($payload);
        $sanitizedHeaders = $this->sanitizer->sanitize($headers);

        $event = TraceEvent::build(
            'core.a2a.outbound.started',
            'a2a_outbound',
            'core',
            'started',
            [
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'intent' => 'hello.greet',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'agent_run_id' => 'run_abc123',
                'step_input' => $sanitizedInput['data'],
                'request_headers' => $sanitizedHeaders['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ],
        );

        $this->assertSame('core.a2a.outbound.started', $event['event_name']);
        $this->assertSame('a2a_outbound', $event['step']);
        $this->assertSame('core', $event['source_app']);
        $this->assertSame('started', $event['status']);
        $this->assertSame('hello-agent', $event['target_app']);
        $this->assertSame('hello.greet', $event['tool']);
        $this->assertSame('hello.greet', $event['intent']);
        $this->assertSame('trace-abc-001', $event['trace_id']);
        $this->assertSame('req-xyz-001', $event['request_id']);
        $this->assertSame('run_abc123', $event['agent_run_id']);
        $this->assertIsArray($event['step_input']);
        $this->assertIsArray($event['request_headers']);
        $this->assertIsArray($event['capture_meta']);
    }

    public function testA2AOutboundStartedRedactsInternalToken(): void
    {
        $headers = [
            'traceparent' => '00-trace-001-01',
            'x-request-id' => 'req-001',
            'X-Platform-Internal-Token' => 'super-secret-internal-token',
        ];

        $sanitizedHeaders = $this->sanitizer->sanitize($headers);

        // The internal token key contains "token" — should be redacted
        $this->assertSame('[REDACTED]', $sanitizedHeaders['data']['X-Platform-Internal-Token']);
        $this->assertGreaterThanOrEqual(1, $sanitizedHeaders['capture_meta']['redacted_fields_count']);
    }

    public function testA2AOutboundCompletedEventHasRequiredFields(): void
    {
        $result = ['status' => 'completed', 'output' => 'Hello, Dima!'];
        $sanitizedOutput = $this->sanitizer->sanitize($result);

        $event = TraceEvent::build(
            'core.a2a.outbound.completed',
            'a2a_outbound',
            'core',
            'completed',
            [
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'intent' => 'hello.greet',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'agent_run_id' => 'run_abc123',
                'task_id' => null,
                'duration_ms' => 142,
                'http_status_code' => 200,
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
                'error_code' => null,
            ],
        );

        $this->assertSame('core.a2a.outbound.completed', $event['event_name']);
        $this->assertSame('a2a_outbound', $event['step']);
        $this->assertSame('completed', $event['status']);
        $this->assertSame(142, $event['duration_ms']);
        $this->assertSame(200, $event['http_status_code']);
        $this->assertNull($event['error_code']);
        $this->assertIsArray($event['step_output']);
        $this->assertIsArray($event['capture_meta']);
    }

    public function testA2AOutboundFailedEventHasErrorFields(): void
    {
        $result = ['status' => 'failed', 'reason' => 'a2a_error', 'error' => 'Connection refused'];
        $sanitizedOutput = $this->sanitizer->sanitize($result);

        $event = TraceEvent::build(
            'core.a2a.outbound.failed',
            'a2a_outbound',
            'core',
            'failed',
            [
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'intent' => 'hello.greet',
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'agent_run_id' => 'run_abc123',
                'duration_ms' => 30001,
                'http_status_code' => 0,
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
                'error_code' => 'a2a_error',
            ],
        );

        $this->assertSame('core.a2a.outbound.failed', $event['event_name']);
        $this->assertSame('a2a_outbound', $event['step']);
        $this->assertSame('failed', $event['status']);
        $this->assertSame('a2a_error', $event['error_code']);
        $this->assertSame(0, $event['http_status_code']);
        $this->assertIsArray($event['step_output']);
        $this->assertSame('failed', $event['step_output']['status']);
    }

    public function testInvokeCompletedEventHasRequiredFields(): void
    {
        $result = ['status' => 'completed', 'output' => 'Hello, World!'];
        $sanitizedOutput = $this->sanitizer->sanitize($result);

        $event = TraceEvent::build(
            'core.invoke.completed',
            'invoke_complete',
            'core',
            'completed',
            [
                'target_app' => 'openclaw',
                'tool' => 'hello.greet',
                'duration_ms' => 200,
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
                'error_code' => null,
            ],
        );

        $this->assertSame('core.invoke.completed', $event['event_name']);
        $this->assertSame('invoke_complete', $event['step']);
        $this->assertSame('core', $event['source_app']);
        $this->assertSame('completed', $event['status']);
        $this->assertSame(200, $event['duration_ms']);
        $this->assertNull($event['error_code']);
        $this->assertIsArray($event['step_output']);
        $this->assertIsArray($event['capture_meta']);
    }

    public function testInvokeCompletedFailedEventHasErrorCode(): void
    {
        $result = ['status' => 'failed', 'reason' => 'unknown_tool'];
        $sanitizedOutput = $this->sanitizer->sanitize($result);

        $event = TraceEvent::build(
            'core.invoke.completed',
            'invoke_complete',
            'core',
            'failed',
            [
                'target_app' => 'openclaw',
                'tool' => 'nonexistent.tool',
                'duration_ms' => 5,
                'trace_id' => 'trace-abc-001',
                'request_id' => 'req-xyz-001',
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
                'error_code' => 'unknown_tool',
            ],
        );

        $this->assertSame('failed', $event['status']);
        $this->assertSame('unknown_tool', $event['error_code']);
        $this->assertSame('failed', $event['step_output']['status']);
    }

    public function testSequenceOrderIsMonotonicallyIncreasing(): void
    {
        $event1 = TraceEvent::build('core.invoke.received', 'invoke_receive', 'core', 'started');
        usleep(1000); // 1ms gap
        $event2 = TraceEvent::build('core.invoke.completed', 'invoke_complete', 'core', 'completed');

        $this->assertGreaterThan($event1['sequence_order'], $event2['sequence_order']);
    }

    public function testAllCanonicalFieldsPresent(): void
    {
        $event = TraceEvent::build(
            'core.invoke.received',
            'invoke_receive',
            'core',
            'started',
            [
                'target_app' => 'openclaw',
                'tool' => 'hello.greet',
                'trace_id' => 'trace-001',
                'request_id' => 'req-001',
            ],
        );

        // All canonical fields from spec 1.1
        $this->assertArrayHasKey('event_name', $event);
        $this->assertArrayHasKey('step', $event);
        $this->assertArrayHasKey('source_app', $event);
        $this->assertArrayHasKey('target_app', $event);
        $this->assertArrayHasKey('tool', $event);
        $this->assertArrayHasKey('trace_id', $event);
        $this->assertArrayHasKey('request_id', $event);
        $this->assertArrayHasKey('status', $event);
        $this->assertArrayHasKey('sequence_order', $event);
    }
}
