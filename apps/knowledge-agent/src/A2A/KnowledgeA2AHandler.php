<?php

declare(strict_types=1);

namespace App\A2A;

use App\OpenSearch\KnowledgeRepository;
use App\RabbitMQ\RabbitMQPublisher;
use App\Repository\SourceMessageRepository;
use App\Service\EmbeddingService;
use App\Service\KnowledgeTreeBuilder;
use App\Service\MessageChunker;

final class KnowledgeA2AHandler
{
    public function __construct(
        private readonly KnowledgeRepository $repository,
        private readonly KnowledgeTreeBuilder $treeBuilder,
        private readonly MessageChunker $chunker,
        private readonly RabbitMQPublisher $publisher,
        private readonly EmbeddingService $embeddingService,
        private readonly SourceMessageRepository $sourceMessageRepository,
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

        /** @var array<string, mixed> $payload */
        $payload = $request['payload'] ?? [];

        return match ($intent) {
            'search_knowledge', 'knowledge.search' => $this->handleSearch($payload, $requestId),
            'extract_from_messages', 'knowledge.upload' => $this->handleExtract($payload, $requestId),
            'get_tree', 'knowledge.get_tree' => $this->handleGetTree($requestId),
            'knowledge.store_message', 'store_message' => $this->handleStoreMessage($payload, $requestId, $traceId),
            default => [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => "Unknown intent: {$intent}",
            ],
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function handleSearch(array $payload, string $requestId): array
    {
        $query = (string) ($payload['query'] ?? '');
        $mode = (string) ($payload['mode'] ?? 'hybrid');
        $size = (int) ($payload['size'] ?? 10);

        if ('' === $query) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'query is required',
            ];
        }

        $options = ['size' => $size];
        if (\in_array($mode, ['hybrid', 'vector'], true)) {
            $options['embedding'] = $this->embeddingService->embed($query);
        }

        $results = $this->repository->search($query, $mode, $options);

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'result' => [
                'query' => $query,
                'mode' => $mode,
                'total' => \count($results),
                'entries' => $results,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function handleExtract(array $payload, string $requestId): array
    {
        /** @var list<array<string, mixed>> $messages */
        $messages = $payload['messages'] ?? [];

        if ([] === $messages) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'messages array is required',
            ];
        }

        /** @var array<string, mixed> $meta */
        $meta = $payload['meta'] ?? [];
        $chunks = $this->chunker->chunk($messages);

        foreach ($chunks as $chunk) {
            $chunk['meta'] = $meta;
            $this->publisher->publishChunk($chunk);
        }

        return [
            'status' => 'queued',
            'request_id' => $requestId,
            'result' => ['chunks_queued' => \count($chunks)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGetTree(string $requestId): array
    {
        $tree = $this->treeBuilder->build();

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'result' => ['tree' => $tree],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function handleStoreMessage(array $payload, string $requestId, string $traceId): array
    {
        if ([] === $payload) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'message payload is required',
            ];
        }

        $id = $this->sourceMessageRepository->upsert($payload, $requestId, '' === $traceId ? null : $traceId);

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'result' => [
                'stored' => true,
                'id' => $id,
            ],
        ];
    }
}
