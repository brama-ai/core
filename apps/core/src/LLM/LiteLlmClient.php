<?php

declare(strict_types=1);

namespace App\LLM;

final class LiteLlmClient
{
    private const TIMEOUT = 60;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $tools
     *
     * @return array<string, mixed>
     */
    public function chatCompletion(array $messages, array $tools = [], ?LlmRequestContext $context = null): array
    {
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => 4096,
        ];

        if ([] !== $tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        if (null !== $context) {
            $body['tags'] = $context->tags();
            $body['metadata'] = $context->metadata();
            $body['user'] = $context->userTag();
        }

        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->apiKey,
            'Content-Length: '.strlen($json),
        ];

        if (null !== $context) {
            $headers[] = 'X-Request-Id: '.$context->requestId;
            $headers[] = 'X-Service-Name: '.$context->agentName;
            $headers[] = 'X-Agent-Name: '.$context->agentName;
            $headers[] = 'X-Feature-Name: '.$context->featureName;
            if ('' !== $context->traceId) {
                $headers[] = 'X-Trace-Id: '.$context->traceId;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers)."\r\n",
                'content' => $json,
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $endpoint = rtrim($this->baseUrl, '/').'/v1/chat/completions';

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($endpoint, false, $context);
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            throw new \RuntimeException('LiteLLM request failed: could not connect to '.$endpoint);
        }

        // Sanitize potentially malformed UTF-8 from LLM response
        $result = iconv('UTF-8', 'UTF-8//IGNORE', $result);

        /** @var array<string, mixed> $data */
        $data = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }
}
