<?php

declare(strict_types=1);

namespace App\Llm;

use App\Logging\TraceContext;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;
use NeuronAI\HttpClient\StreamInterface;

final class TracingHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly TraceContext $traceContext,
        private readonly string $serviceName = 'knowledge-agent',
    ) {
    }

    public function request(HttpRequest $request): HttpResponse
    {
        return $this->inner->request($this->decorateRequest($request));
    }

    public function stream(HttpRequest $request): StreamInterface
    {
        return $this->inner->stream($this->decorateRequest($request));
    }

    public function withBaseUri(string $baseUri): HttpClientInterface
    {
        return new self(
            $this->inner->withBaseUri($baseUri),
            $this->traceContext,
            $this->serviceName,
        );
    }

    public function withHeaders(array $headers): HttpClientInterface
    {
        return new self(
            $this->inner->withHeaders($headers),
            $this->traceContext,
            $this->serviceName,
        );
    }

    public function withTimeout(float $timeout): HttpClientInterface
    {
        return new self(
            $this->inner->withTimeout($timeout),
            $this->traceContext,
            $this->serviceName,
        );
    }

    private function decorateRequest(HttpRequest $request): HttpRequest
    {
        $requestId = $this->traceContext->getRequestId();
        if ('' === $requestId) {
            $requestId = uniqid('llm_', true);
        }

        $featureName = $this->detectFeatureName($request);
        $traceId = $this->traceContext->getTraceId();
        $headers = [
            'X-Request-Id' => $requestId,
            'X-Service-Name' => $this->serviceName,
            'X-Agent-Name' => $this->serviceName,
            'X-Feature-Name' => $featureName,
        ];

        if ('' !== $traceId) {
            $headers['X-Trace-Id'] = $traceId;
        }

        $enriched = $request->withHeaders($headers);
        $body = $enriched->body;

        if (\is_array($body)) {
            if (!isset($body['user'])) {
                $body['user'] = \sprintf(
                    'service=%s;feature=%s;request_id=%s',
                    $this->serviceName,
                    $featureName,
                    $requestId,
                );
            }

            $metadata = [];
            if (isset($body['metadata']) && \is_array($body['metadata'])) {
                $metadata = $body['metadata'];
            }

            $body['metadata'] = [
                ...$metadata,
                'request_id' => $requestId,
                'service_name' => $this->serviceName,
                'agent_name' => $this->serviceName,
                'feature_name' => $featureName,
                'trace_id' => $traceId,
            ];
        }

        return new HttpRequest(
            method: $enriched->method,
            uri: $enriched->uri,
            headers: $enriched->headers,
            body: $body,
        );
    }

    private function detectFeatureName(HttpRequest $request): string
    {
        $uri = strtolower($request->uri);

        if (str_contains($uri, 'embeddings')) {
            return 'knowledge.embedding';
        }

        if (!\is_array($request->body)) {
            return 'knowledge.llm.call';
        }

        $schemaName = strtolower((string) ($request->body['response_format']['json_schema']['name'] ?? ''));

        return match ($schemaName) {
            'analysisresult' => 'knowledge.analyze_messages',
            'extractedknowledge' => 'knowledge.extract_knowledge',
            default => 'knowledge.llm.call',
        };
    }
}
