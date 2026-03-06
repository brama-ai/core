<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * Inbound A2A request sent to an agent.
 *
 * Contains the intent (skill) to invoke, the input payload, and trace context
 * for distributed tracing across the platform.
 */
final readonly class A2ARequest
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        /** @var string The skill/intent being invoked (e.g. "hello.greet", "knowledge.search") */
        public string $intent,
        /** @var array<string, mixed> Input payload specific to the invoked skill */
        public array $payload = [],
        /** @var string Unique request identifier for correlation */
        public string $requestId = '',
        /** @var string Distributed trace identifier (W3C Trace Context compatible) */
        public string $traceId = '',
        /** @var string|null Optional system prompt override for LLM-backed skills */
        public ?string $systemPrompt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            intent: (string) ($data['intent'] ?? ''),
            payload: \is_array($data['payload'] ?? null) ? $data['payload'] : [],
            requestId: (string) ($data['request_id'] ?? ''),
            traceId: (string) ($data['trace_id'] ?? ''),
            systemPrompt: isset($data['system_prompt']) ? (string) $data['system_prompt'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'intent' => $this->intent,
            'payload' => $this->payload,
            'request_id' => $this->requestId,
            'trace_id' => $this->traceId,
        ];

        if (null !== $this->systemPrompt) {
            $result['system_prompt'] = $this->systemPrompt;
        }

        return $result;
    }
}
