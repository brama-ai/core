<?php

declare(strict_types=1);

namespace App\Service;

use App\Logging\TraceContext;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EmbeddingService
{
    private const SERVICE_NAME = 'knowledge-agent';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $litellmBaseUrl,
        private readonly string $litellmApiKey,
        private readonly string $embeddingModel,
        private readonly TraceContext $traceContext,
    ) {
    }

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $requestId = $this->traceContext->getRequestId();
        if ('' === $requestId) {
            $requestId = uniqid('llm_embed_', true);
        }

        $featureName = $this->resolveFeatureName();
        $traceId = $this->traceContext->getTraceId();
        $headers = [
            'Authorization' => 'Bearer '.$this->litellmApiKey,
            'Content-Type' => 'application/json',
            'X-Request-Id' => $requestId,
            'X-Service-Name' => self::SERVICE_NAME,
            'X-Agent-Name' => self::SERVICE_NAME,
            'X-Feature-Name' => $featureName,
        ];
        if ('' !== $traceId) {
            $headers['X-Trace-Id'] = $traceId;
        }

        $userTag = \sprintf(
            'service=%s;feature=%s;request_id=%s',
            self::SERVICE_NAME,
            $featureName,
            $requestId,
        );

        $response = $this->httpClient->request('POST', $this->litellmBaseUrl.'/v1/embeddings', [
            'headers' => $headers,
            'json' => [
                'model' => $this->embeddingModel,
                'input' => $text,
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
            ],
        ]);

        /** @var array{data: list<array{embedding: list<float>}>} $data */
        $data = $response->toArray();

        return $data['data'][0]['embedding'] ?? [];
    }

    private function resolveFeatureName(): string
    {
        $frames = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 6);

        foreach ($frames as $frame) {
            $class = (string) ($frame['class'] ?? '');
            if ('' === $class || self::class === $class) {
                continue;
            }

            $shortClass = str_contains($class, '\\')
                ? substr($class, (int) strrpos($class, '\\') + 1)
                : $class;
            $method = $frame['function'];

            return strtolower(\sprintf('embedding.%s.%s', $shortClass, $method));
        }

        return 'embedding.unknown';
    }
}
