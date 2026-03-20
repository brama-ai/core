<?php

declare(strict_types=1);

namespace App\Tests\Integration\Logging;

use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use Codeception\Test\Unit;

/**
 * Integration coverage for discovery snapshot event fields (task 5.3).
 *
 * Verifies that TraceEvent::build() + PayloadSanitizer produce the canonical
 * field structure emitted by DiscoveryController for the discovery_response step.
 */
final class DiscoverySnapshotEventFieldsTest extends Unit
{
    private PayloadSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new PayloadSanitizer();
    }

    public function testDiscoverySnapshotEventHasRequiredFields(): void
    {
        $tools = [
            [
                'name' => 'hello.greet',
                'agent' => 'hello-agent',
                'description' => 'Greet a user',
                'input_schema_fingerprint' => hash('sha256', '{"type":"object"}'),
            ],
            [
                'name' => 'hello.farewell',
                'agent' => 'hello-agent',
                'description' => 'Say farewell',
                'input_schema_fingerprint' => hash('sha256', '{"type":"object"}'),
            ],
        ];

        $sanitized = $this->sanitizer->sanitize([
            'generated_at' => '2026-03-20T10:00:00+00:00',
            'tool_count' => \count($tools),
            'tools' => $tools,
        ]);

        $event = TraceEvent::build(
            'core.discovery.snapshot',
            'discovery_response',
            'core',
            'completed',
            [
                'target_app' => 'openclaw',
                'tool_count' => \count($tools),
                'step_output' => $sanitized['data'],
                'capture_meta' => $sanitized['capture_meta'],
            ],
        );

        // Canonical fields
        $this->assertSame('core.discovery.snapshot', $event['event_name']);
        $this->assertSame('discovery_response', $event['step']);
        $this->assertSame('core', $event['source_app']);
        $this->assertSame('completed', $event['status']);
        $this->assertSame('openclaw', $event['target_app']);
        $this->assertIsInt($event['sequence_order']);
        $this->assertGreaterThan(0, $event['sequence_order']);

        // Snapshot-specific fields
        $this->assertSame(2, $event['tool_count']);
        $this->assertIsArray($event['step_output']);
        $this->assertArrayHasKey('tool_count', $event['step_output']);
        $this->assertArrayHasKey('tools', $event['step_output']);
        $this->assertArrayHasKey('generated_at', $event['step_output']);

        // Capture metadata
        $this->assertIsArray($event['capture_meta']);
        $this->assertArrayHasKey('is_truncated', $event['capture_meta']);
        $this->assertArrayHasKey('original_size_bytes', $event['capture_meta']);
        $this->assertArrayHasKey('captured_size_bytes', $event['capture_meta']);
        $this->assertArrayHasKey('redacted_fields_count', $event['capture_meta']);
        $this->assertArrayHasKey('truncated_values_count', $event['capture_meta']);
        $this->assertFalse($event['capture_meta']['is_truncated']);
        $this->assertSame(0, $event['capture_meta']['redacted_fields_count']);
    }

    public function testDiscoveryCacheHitEventHasRequiredFields(): void
    {
        $tools = [
            [
                'name' => 'knowledge.search',
                'agent' => 'knowledge-agent',
                'description' => 'Search knowledge base',
                'input_schema_fingerprint' => hash('sha256', '{"type":"object"}'),
            ],
        ];

        $sanitized = $this->sanitizer->sanitize([
            'generated_at' => '2026-03-20T09:00:00+00:00',
            'tool_count' => \count($tools),
            'tools' => $tools,
        ]);

        $event = TraceEvent::build(
            'core.discovery.cache_hit',
            'discovery_fetch',
            'core',
            'completed',
            [
                'target_app' => 'openclaw',
                'tool_count' => \count($tools),
                'step_output' => $sanitized['data'],
                'capture_meta' => $sanitized['capture_meta'],
            ],
        );

        $this->assertSame('core.discovery.cache_hit', $event['event_name']);
        $this->assertSame('discovery_fetch', $event['step']);
        $this->assertSame('core', $event['source_app']);
        $this->assertSame('completed', $event['status']);
        $this->assertSame(1, $event['tool_count']);
        $this->assertIsArray($event['step_output']);
        $this->assertIsArray($event['capture_meta']);
    }

    public function testDiscoverySnapshotSanitizesToolDescriptions(): void
    {
        // Tool descriptions should pass through without redaction
        $tools = [
            [
                'name' => 'secure.tool',
                'agent' => 'secure-agent',
                'description' => 'A tool with no secrets',
                'input_schema_fingerprint' => hash('sha256', '{}'),
            ],
        ];

        $sanitized = $this->sanitizer->sanitize([
            'generated_at' => '2026-03-20T10:00:00+00:00',
            'tool_count' => 1,
            'tools' => $tools,
        ]);

        $this->assertSame(0, $sanitized['capture_meta']['redacted_fields_count']);
        $this->assertIsArray($sanitized['data']['tools']);
        $this->assertSame('secure.tool', $sanitized['data']['tools'][0]['name']);
        $this->assertSame('A tool with no secrets', $sanitized['data']['tools'][0]['description']);
    }

    public function testDiscoverySnapshotWithEmptyToolList(): void
    {
        $sanitized = $this->sanitizer->sanitize([
            'generated_at' => '2026-03-20T10:00:00+00:00',
            'tool_count' => 0,
            'tools' => [],
        ]);

        $event = TraceEvent::build(
            'core.discovery.snapshot',
            'discovery_response',
            'core',
            'completed',
            [
                'target_app' => 'openclaw',
                'tool_count' => 0,
                'step_output' => $sanitized['data'],
                'capture_meta' => $sanitized['capture_meta'],
            ],
        );

        $this->assertSame(0, $event['tool_count']);
        $this->assertSame([], $event['step_output']['tools']);
        $this->assertFalse($event['capture_meta']['is_truncated']);
    }
}
