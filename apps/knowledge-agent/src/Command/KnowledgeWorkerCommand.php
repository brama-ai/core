<?php

declare(strict_types=1);

namespace App\Command;

use App\OpenSearch\KnowledgeRepository;
use App\Service\EmbeddingService;
use App\Service\TokenBucketRateLimiter;
use App\Service\WorkflowMonitoringService;
use App\Workflow\KnowledgeExtractionAgent;
use App\Workflow\KnowledgeExtractionWorkflow;
use NeuronAI\Observability\InspectorObserver;
use Doctrine\DBAL\Connection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'knowledge:worker',
    description: 'Long-running worker: consume and process knowledge chunks from RabbitMQ',
)]
final class KnowledgeWorkerCommand extends Command
{
    private const EXCHANGE = 'knowledge.direct';
    private const QUEUE = 'knowledge.chunks';
    private const DLQ = 'knowledge.dlq';
    private const DLX = 'knowledge.dlx';
    private const MAX_RETRIES = 3;

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly string $rabbitmqUrl,
        private readonly KnowledgeExtractionAgent $agent,
        private readonly KnowledgeRepository $knowledgeRepository,
        private readonly EmbeddingService $embeddingService,
        private readonly Connection $dbal,
        private readonly TokenBucketRateLimiter $rateLimiter,
        private readonly int $concurrency = 1,
        private readonly ?InspectorObserver $inspector = null,
        private readonly ?WorkflowMonitoringService $monitoring = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info('Knowledge worker started. Waiting for messages...');

        $this->connection = $this->createConnection();
        $channel = $this->connection->channel();
        \assert(null !== $channel);
        $this->channel = $channel;

        $this->declareTopology($channel);

        // Set prefetch count to concurrency level for better distribution
        $channel->basic_qos(0, $this->concurrency, false);
        
        $io->info("Starting {$this->concurrency} concurrent consumers...");
        
        $channel->basic_consume(
            self::QUEUE,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $msg) use ($io): void {
                $this->processMessage($msg, $io);
            },
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return Command::SUCCESS;
    }

    private function processMessage(AMQPMessage $msg, SymfonyStyle $io): void
    {
        try {
            /** @var array<string, mixed> $chunk */
            $chunk = json_decode($msg->getBody(), true, 512, \JSON_THROW_ON_ERROR);
            $chunkHash = (string) ($chunk['chunk_hash'] ?? '');

            if ($this->isDuplicate($chunkHash)) {
                $io->text("Skipping duplicate chunk: {$chunkHash}");
                $msg->ack();

                return;
            }

            $attemptCount = $this->getAttemptCount($chunkHash);

            if ($attemptCount >= self::MAX_RETRIES) {
                $io->warning("Chunk {$chunkHash} exceeded max retries — sending to DLQ");
                $msg->nack(false, false);

                return;
            }

            $this->incrementAttemptCount($chunkHash);

            /** @var list<array<string, mixed>> $messages */
            $messages = $chunk['messages'] ?? [];

            /** @var array<string, mixed> $chunkMeta */
            $chunkMeta = $chunk['meta'] ?? [];

            $io->text("Processing chunk {$chunkHash} ({$attemptCount} attempts)...");

            // Check rate limit before processing (2 tokens: 1 for analysis, 1 for extraction)
            if (!$this->rateLimiter->consume('llm_calls', 2)) {
                $waitTime = $this->rateLimiter->getTimeUntilNextToken('llm_calls');
                $io->warning("Rate limit exceeded. Waiting {$waitTime} seconds before retrying...");
                sleep($waitTime);
                
                // Try again after waiting
                if (!$this->rateLimiter->consume('llm_calls', 2)) {
                    $io->warning("Still rate limited after waiting. Requeuing message.");
                    $msg->nack(false, true); // requeue
                    return;
                }
            }

            $workflow = new KnowledgeExtractionWorkflow($this->agent, $messages, $chunkMeta, $this->inspector, $this->monitoring);
            iterator_to_array($workflow->run());

            $knowledge = $workflow->getKnowledge();

            if (null === $knowledge) {
                $io->text("Chunk {$chunkHash} — not valuable, skipping.");
                $this->markComplete($chunkHash);
                $msg->ack();

                return;
            }

            // Generate embedding for the knowledge entry
            $embeddingText = $knowledge['title'].' '.$knowledge['body'];
            $knowledge['embedding'] = $this->embeddingService->embed($embeddingText);

            $this->knowledgeRepository->index($knowledge);
            $this->markComplete($chunkHash);

            $io->success("Chunk {$chunkHash} processed and indexed.");
            $msg->ack();
        } catch (\Throwable $e) {
            $io->error("Error processing chunk: {$e->getMessage()}");
            $msg->nack(false, true); // requeue
        }
    }

    private function isDuplicate(string $chunkHash): bool
    {
        if ('' === $chunkHash) {
            return false;
        }

        $row = $this->dbal->fetchOne(
            "SELECT status FROM processed_chunks WHERE chunk_hash = :hash AND status = 'completed'",
            ['hash' => $chunkHash],
        );

        return false !== $row;
    }

    private function getAttemptCount(string $chunkHash): int
    {
        if ('' === $chunkHash) {
            return 0;
        }

        $row = $this->dbal->fetchOne(
            'SELECT attempt_count FROM processed_chunks WHERE chunk_hash = :hash',
            ['hash' => $chunkHash],
        );

        return false === $row ? 0 : (int) $row;
    }

    private function incrementAttemptCount(string $chunkHash): void
    {
        if ('' === $chunkHash) {
            return;
        }

        $existing = $this->dbal->fetchOne(
            'SELECT id FROM processed_chunks WHERE chunk_hash = :hash',
            ['hash' => $chunkHash],
        );

        if (false === $existing) {
            $this->dbal->executeStatement(
                "INSERT INTO processed_chunks (chunk_hash, status, attempt_count, created_at) VALUES (:hash, 'processing', 1, now())",
                ['hash' => $chunkHash],
            );
        } else {
            $this->dbal->executeStatement(
                'UPDATE processed_chunks SET attempt_count = attempt_count + 1 WHERE chunk_hash = :hash',
                ['hash' => $chunkHash],
            );
        }
    }

    private function markComplete(string $chunkHash): void
    {
        if ('' === $chunkHash) {
            return;
        }

        $this->dbal->executeStatement(
            "UPDATE processed_chunks SET status = 'completed', processed_at = now() WHERE chunk_hash = :hash",
            ['hash' => $chunkHash],
        );
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $channel->exchange_declare(self::DLX, 'direct', false, true, false);
        $channel->queue_declare(self::DLQ, false, true, false, false);
        $channel->queue_bind(self::DLQ, self::DLX, self::DLQ);

        $channel->exchange_declare(self::EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare(self::QUEUE, false, true, false, false, false, [
            'x-dead-letter-exchange' => ['S', self::DLX],
            'x-dead-letter-routing-key' => ['S', self::DLQ],
        ]);
        $channel->queue_bind(self::QUEUE, self::EXCHANGE, self::QUEUE);
    }

    private function createConnection(): AMQPStreamConnection
    {
        $parsed = parse_url($this->rabbitmqUrl);
        \assert(false !== $parsed);

        return new AMQPStreamConnection(
            host: $parsed['host'] ?? 'rabbitmq',
            port: (int) ($parsed['port'] ?? 5672),
            user: urldecode($parsed['user'] ?? 'guest'),
            password: urldecode($parsed['pass'] ?? 'guest'),
            vhost: urldecode(ltrim($parsed['path'] ?? '/', '/')) ?: '/',
        );
    }

    public function __destruct()
    {
        $this->channel?->close();
        $this->connection?->close();
    }
}
