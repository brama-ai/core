<?php

declare(strict_types=1);

namespace App\A2AGateway;

use App\AgentRegistry\AgentRegistryInterface;
use App\AgentRegistry\ManifestValidator;
use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use App\Observability\LangfuseIngestionClient;
use App\Observability\TraceContext;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class A2AClient implements A2AClientInterface
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly Connection $dbal,
        private readonly LangfuseIngestionClient $langfuse,
        private readonly PayloadSanitizer $payloadSanitizer,
        private readonly LoggerInterface $logger,
        private readonly string $internalToken = '',
    ) {
    }

    /**
     * Invoke an agent skill via the A2A gateway.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function invoke(string $tool, array $input, string $traceId, string $requestId, string $actor = 'openclaw'): array
    {
        foreach ($this->registry->findEnabled() as $agent) {
            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'])
                ? json_decode((string) $agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : $agent['manifest'];

            $skillIds = ManifestValidator::extractSkillIds($manifest);

            if (in_array($tool, $skillIds, true)) {
                $this->logger->info(
                    'Skill resolved to enabled agent',
                    TraceEvent::build('core.invoke.tool_resolved', 'tool_resolve', 'core', 'completed', [
                        'target_app' => (string) $agent['name'],
                        'tool' => $tool,
                        'trace_id' => $traceId,
                        'request_id' => $requestId,
                    ]),
                );

                return $this->callAgent($agent, $manifest, $tool, $input, $traceId, $requestId, $actor);
            }
        }

        // Check if skill exists on a disabled agent
        foreach ($this->registry->findAll() as $agent) {
            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'])
                ? json_decode((string) $agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : $agent['manifest'];

            $skillIds = ManifestValidator::extractSkillIds($manifest);

            if (in_array($tool, $skillIds, true)) {
                $this->logger->warning(
                    'Tool found on disabled agent',
                    TraceEvent::build('core.invoke.tool_disabled', 'tool_resolve', 'core', 'failed', [
                        'target_app' => (string) $agent['name'],
                        'tool' => $tool,
                        'trace_id' => $traceId,
                        'request_id' => $requestId,
                        'error_code' => 'agent_disabled',
                    ]),
                );
                $this->auditLog($tool, (string) $agent['name'], $traceId, $requestId, 0, 'failed', 0, 'agent_disabled', $actor);

                return ['status' => 'failed', 'reason' => 'agent_disabled'];
            }
        }

        $this->logger->warning(
            'Unknown tool requested',
            TraceEvent::build('core.invoke.unknown_tool', 'tool_resolve', 'core', 'failed', [
                'target_app' => 'unknown',
                'tool' => $tool,
                'trace_id' => $traceId,
                'request_id' => $requestId,
                'error_code' => 'unknown_tool',
            ]),
        );

        return ['status' => 'failed', 'reason' => 'unknown_tool'];
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function callAgent(
        array $agent,
        array $manifest,
        string $tool,
        array $input,
        string $traceId,
        string $requestId,
        string $actor = 'openclaw',
    ): array {
        $a2aEndpoint = ManifestValidator::resolveUrl($manifest);
        $agentName = (string) $agent['name'];

        if ('' === $a2aEndpoint) {
            $this->logger->warning(
                'Agent has no A2A endpoint',
                TraceEvent::build('core.a2a.endpoint_missing', 'tool_resolve', 'core', 'failed', [
                    'target_app' => $agentName,
                    'tool' => $tool,
                    'trace_id' => $traceId,
                    'request_id' => $requestId,
                    'error_code' => 'no_a2a_endpoint',
                ]),
            );
            $this->auditLog($tool, $agentName, $traceId, $requestId, 0, 'failed', 0, 'no_a2a_endpoint', $actor);

            return ['status' => 'failed', 'reason' => 'no_a2a_endpoint'];
        }

        /** @var array<string, mixed> $config */
        $config = is_string($agent['config'] ?? null)
            ? (array) json_decode((string) $agent['config'], true)
            : (array) ($agent['config'] ?? []);

        $start = microtime(true);
        $payload = [
            'intent' => $tool,
            'payload' => $input,
            'request_id' => $requestId,
            'trace_id' => $traceId,
        ];

        $systemPrompt = (string) ($config['system_prompt'] ?? '');
        if ('' !== $systemPrompt) {
            $payload['system_prompt'] = $systemPrompt;
        }
        $agentRunId = 'run_'.bin2hex(random_bytes(8));
        $payload['agent_run_id'] = $agentRunId;
        $payload['hop'] = 1;
        $headers = [
            'traceparent' => TraceContext::buildTraceparent($traceId),
            'x-request-id' => $requestId,
            'x-agent-run-id' => $agentRunId,
            'x-a2a-hop' => '1',
        ];
        if ('' !== $this->internalToken) {
            $headers['X-Platform-Internal-Token'] = $this->internalToken;
        }
        $sanitizedInput = $this->payloadSanitizer->sanitize($payload);
        $sanitizedHeaders = $this->payloadSanitizer->sanitize($headers);
        $this->logger->info(
            'A2A outbound started',
            TraceEvent::build('core.a2a.outbound.started', 'a2a_outbound', 'core', 'started', [
                'target_app' => $agentName,
                'tool' => $tool,
                'intent' => $tool,
                'trace_id' => $traceId,
                'request_id' => $requestId,
                'agent_run_id' => $agentRunId,
                'step_input' => $sanitizedInput['data'],
                'request_headers' => $sanitizedHeaders['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );
        $httpStatusCode = 0;

        $errorCode = null;

        try {
            $response = $this->postJson($a2aEndpoint, $payload, $headers);
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $httpStatusCode = $response['status_code'];

            /** @var array<string, mixed> $result */
            $result = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            $status = (string) ($result['status'] ?? 'unknown');
            $taskId = isset($result['task_id']) ? (string) $result['task_id'] : null;
            $errorCode = 'failed' === $status ? (string) ($result['reason'] ?? 'a2a_failed') : null;
            $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
            $logLevel = 'failed' === $status ? 'warning' : 'info';
            $this->logger->$logLevel(
                'A2A outbound completed',
                TraceEvent::build('core.a2a.outbound.completed', 'a2a_outbound', 'core', $status, [
                    'target_app' => $agentName,
                    'tool' => $tool,
                    'intent' => $tool,
                    'trace_id' => $traceId,
                    'request_id' => $requestId,
                    'agent_run_id' => $agentRunId,
                    'task_id' => $taskId,
                    'duration_ms' => $durationMs,
                    'http_status_code' => $httpStatusCode,
                    'step_output' => $sanitizedOutput['data'],
                    'capture_meta' => $sanitizedOutput['capture_meta'],
                    'error_code' => $errorCode,
                ]),
            );
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $status = 'failed';
            $errorCode = 'a2a_error';
            $result = ['status' => 'failed', 'reason' => 'a2a_error', 'error' => $e->getMessage()];
            $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
            $this->logger->error(
                'A2A outbound failed',
                TraceEvent::build('core.a2a.outbound.failed', 'a2a_outbound', 'core', 'failed', [
                    'target_app' => $agentName,
                    'tool' => $tool,
                    'intent' => $tool,
                    'trace_id' => $traceId,
                    'request_id' => $requestId,
                    'agent_run_id' => $agentRunId,
                    'duration_ms' => $durationMs,
                    'http_status_code' => $httpStatusCode,
                    'step_output' => $sanitizedOutput['data'],
                    'capture_meta' => $sanitizedOutput['capture_meta'],
                    'error_code' => $errorCode,
                    'exception' => $e,
                ]),
            );
        }

        $this->auditLog($tool, $agentName, $traceId, $requestId, $durationMs, $status, $httpStatusCode, $errorCode, $actor);
        $this->langfuse->recordA2ACall(
            $traceId,
            $requestId,
            $tool,
            $agentName,
            $durationMs,
            $status,
            $httpStatusCode,
            $input,
            $result,
        );

        return array_merge($result, [
            'agent' => $agentName,
            'tool' => $tool,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $extraHeaders
     *
     * @return array{body: string, status_code: int}
     */
    private function postJson(string $url, array $payload, array $extraHeaders = []): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $headerLines = [
            'Content-Type: application/json',
            'Content-Length: '.strlen($body),
        ];
        foreach ($extraHeaders as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines)."\r\n",
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            throw new \RuntimeException("Failed to connect to A2A endpoint: {$url}");
        }

        return [
            'body' => $result,
            'status_code' => $this->extractStatusCode(),
        ];
    }

    private function extractStatusCode(): int
    {
        $headers = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : null;
        if (!is_array($headers) || [] === $headers) {
            return 0;
        }

        if (1 !== preg_match('/HTTP\\/\\d+(?:\\.\\d+)?\\s+(\\d{3})/', (string) $headers[0], $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function auditLog(
        string $skill,
        string $agent,
        string $traceId,
        string $requestId,
        int $durationMs,
        string $status,
        int $httpStatusCode = 0,
        ?string $errorCode = null,
        string $actor = 'openclaw',
    ): void {
        $this->dbal->executeStatement(
            <<<'SQL'
            INSERT INTO a2a_message_audit
                (skill, agent, trace_id, request_id, duration_ms, status, http_status_code, error_code, actor, created_at)
            VALUES
                (:skill, :agent, :traceId, :requestId, :durationMs, :status, :httpStatusCode, :errorCode, :actor, now())
            SQL,
            [
                'skill' => $skill,
                'agent' => $agent,
                'traceId' => $traceId ?: null,
                'requestId' => $requestId ?: null,
                'durationMs' => $durationMs,
                'status' => $status,
                'httpStatusCode' => $httpStatusCode > 0 ? $httpStatusCode : null,
                'errorCode' => $errorCode,
                'actor' => $actor,
            ],
        );
    }
}
