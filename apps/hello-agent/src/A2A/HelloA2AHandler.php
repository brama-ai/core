<?php

declare(strict_types=1);

namespace App\A2A;

use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use Psr\Log\LoggerInterface;

final class HelloA2AHandler
{
    private const DEFAULT_SYSTEM_PROMPT = 'You are a friendly greeter. Respond with a warm, creative greeting.';
    private const SERVICE_NAME = 'hello-agent';
    private const FEATURE_GREET = 'a2a.hello.greet';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PayloadSanitizer $payloadSanitizer,
        private readonly string $liteLlmBaseUrl,
        private readonly string $liteLlmApiKey,
        private readonly string $llmModel,
    ) {
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        $intent = (string) ($request['intent'] ?? '');
        $requestId = (string) ($request['request_id'] ?? uniqid('a2a_', true));
        $traceId = (string) ($request['trace_id'] ?? '');
        $systemPrompt = (string) ($request['system_prompt'] ?? '');

        /** @var array<string, mixed> $payload */
        $payload = $request['payload'] ?? [];

        $logCtx = ['intent' => $intent, 'request_id' => $requestId, 'trace_id' => $traceId];

        return match ($intent) {
            'hello.greet' => $this->handleGreet($payload, $requestId, $systemPrompt, $logCtx),
            default => $this->handleUnknown($intent, $requestId, $logCtx),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleGreet(array $payload, string $requestId, string $systemPrompt, array $logCtx): array
    {
        $name = (string) ($payload['name'] ?? 'World');

        $sanitizedInput = $this->payloadSanitizer->sanitize($payload);
        $this->logger->info(
            'Intent hello.greet started',
            TraceEvent::build('hello.intent.greet.started', 'intent_handle', self::SERVICE_NAME, 'started', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'hello.greet',
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );
        $this->logger->info('Greeting requested', $logCtx + ['name' => $name]);

        $greeting = $this->generateGreeting($name, $systemPrompt, $logCtx);

        $this->logger->info('Greeting processed', $logCtx + [
            'name' => $name,
            'via_llm' => '' !== $this->liteLlmApiKey,
        ]);

        $result = [
            'status' => 'completed',
            'request_id' => $requestId,
            'result' => [
                'greeting' => $greeting,
            ],
        ];
        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
        $this->logger->info(
            'Intent hello.greet completed',
            TraceEvent::build('hello.intent.greet.completed', 'intent_handle', self::SERVICE_NAME, 'completed', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'hello.greet',
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
            ]),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleUnknown(string $intent, string $requestId, array $logCtx): array
    {
        $this->logger->warning('Unknown intent received', $logCtx);

        $result = [
            'status' => 'failed',
            'request_id' => $requestId,
            'error' => "Unknown intent: {$intent}",
        ];
        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
        $this->logger->warning(
            'Unknown intent received',
            TraceEvent::build('hello.intent.unknown', 'intent_handle', self::SERVICE_NAME, 'failed', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => $intent,
                'error_code' => 'unknown_intent',
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
            ]),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $logCtx
     */
    private function generateGreeting(string $name, string $systemPrompt, array $logCtx): string
    {
        if ('' === $this->liteLlmApiKey) {
            $this->logger->debug('No API key, using fallback greeting', $logCtx + ['name' => $name]);

            return "Hello, {$name}!";
        }

        $system = '' !== $systemPrompt ? $systemPrompt : self::DEFAULT_SYSTEM_PROMPT;

        $this->logger->debug('Calling LLM', $logCtx + [
            'model' => $this->llmModel,
            'has_custom_prompt' => '' !== $systemPrompt,
        ]);
        $llmInput = $this->payloadSanitizer->sanitize([
            'model' => $this->llmModel,
            'name' => $name,
            'has_custom_prompt' => '' !== $systemPrompt,
        ]);
        $this->logger->info(
            'LLM call started',
            TraceEvent::build('hello.llm.call.started', 'llm_call', self::SERVICE_NAME, 'started', $logCtx + [
                'target_app' => 'litellm',
                'intent' => 'hello.greet',
                'step_input' => $llmInput['data'],
                'capture_meta' => $llmInput['capture_meta'],
            ]),
        );

        $start = microtime(true);

        try {
            $result = $this->callLlm(
                $system,
                "Привітай користувача {$name}",
                (string) ($logCtx['request_id'] ?? ''),
                (string) ($logCtx['trace_id'] ?? ''),
                self::FEATURE_GREET,
            );
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $this->logger->info('LLM call succeeded', $logCtx + [
                'model' => $this->llmModel,
                'duration_ms' => $durationMs,
            ]);
            $llmOutput = $this->payloadSanitizer->sanitize(['greeting' => $result]);
            $this->logger->info(
                'LLM call completed',
                TraceEvent::build('hello.llm.call.completed', 'llm_call', self::SERVICE_NAME, 'completed', $logCtx + [
                    'target_app' => 'litellm',
                    'intent' => 'hello.greet',
                    'duration_ms' => $durationMs,
                    'step_output' => $llmOutput['data'],
                    'capture_meta' => $llmOutput['capture_meta'],
                ]),
            );

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            $this->logger->warning('LLM call failed, using fallback', $logCtx + [
                'error' => $e->getMessage(),
                'model' => $this->llmModel,
                'duration_ms' => $durationMs,
            ]);
            $llmOutput = $this->payloadSanitizer->sanitize([
                'error' => $e->getMessage(),
                'fallback' => "Hello, {$name}!",
            ]);
            $this->logger->warning(
                'LLM call failed',
                TraceEvent::build('hello.llm.call.failed', 'llm_call', self::SERVICE_NAME, 'failed', $logCtx + [
                    'target_app' => 'litellm',
                    'intent' => 'hello.greet',
                    'duration_ms' => $durationMs,
                    'error_code' => 'llm_call_failed',
                    'step_output' => $llmOutput['data'],
                    'capture_meta' => $llmOutput['capture_meta'],
                ]),
            );

            return "Hello, {$name}!";
        }
    }

    private function callLlm(
        string $systemPrompt,
        string $userMessage,
        string $requestId,
        string $traceId,
        string $featureName,
    ): string {
        $userTag = \sprintf(
            'service=%s;feature=%s;request_id=%s',
            self::SERVICE_NAME,
            $featureName,
            $requestId,
        );
        $body = json_encode([
            'model' => $this->llmModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'user' => $userTag,
            'metadata' => [
                'trace_id' => $traceId,
                'trace_name' => self::SERVICE_NAME.'.'.$featureName,
                'session_id' => $requestId,
                'generation_name' => $featureName,
                'tags' => [
                    'agent:'.self::SERVICE_NAME,
                    'method:'.$featureName,
                ],
                'trace_user_id' => $userTag,
                'trace_metadata' => [
                    'request_id' => $requestId,
                    'agent_name' => self::SERVICE_NAME,
                    'feature_name' => $featureName,
                ],
            ],
            'tags' => [
                'agent:'.self::SERVICE_NAME,
                'method:'.$featureName,
            ],
            'max_tokens' => 200,
        ], \JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->liteLlmApiKey,
            'Content-Length: '.\strlen($body),
            'X-Request-Id: '.$requestId,
            'X-Service-Name: '.self::SERVICE_NAME,
            'X-Agent-Name: '.self::SERVICE_NAME,
            'X-Feature-Name: '.$featureName,
        ];
        if ('' !== $traceId) {
            $headers[] = 'X-Trace-Id: '.$traceId;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers)."\r\n",
                'content' => $body,
                'timeout' => 25,
                'ignore_errors' => true,
            ],
        ]);

        $endpoint = rtrim($this->liteLlmBaseUrl, '/').'/v1/chat/completions';
        $result = @file_get_contents($endpoint, false, $context);

        if (false === $result) {
            throw new \RuntimeException('LiteLLM API request failed');
        }

        /** @var array{choices?: list<array{message?: array{content?: string}}>} $data */
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        $content = (string) ($data['choices'][0]['message']['content'] ?? '');

        if ('' === $content) {
            throw new \RuntimeException('Empty LLM response');
        }

        return $content;
    }
}
