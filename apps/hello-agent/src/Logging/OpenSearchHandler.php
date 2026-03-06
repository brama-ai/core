<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class OpenSearchHandler extends AbstractProcessingHandler
{
    private const INDEX_PREFIX = 'platform_logs';
    private const BULK_SIZE = 50;

    /** @var list<LogRecord> */
    private array $buffer = [];

    public function __construct(
        private readonly string $opensearchUrl,
        Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->buffer[] = $record;

        if (\count($this->buffer) >= self::BULK_SIZE) {
            $this->flush();
        }
    }

    public function close(): void
    {
        $this->flush();
        parent::close();
    }

    private function flush(): void
    {
        if ([] === $this->buffer) {
            return;
        }

        $indexName = \sprintf('%s_%s', self::INDEX_PREFIX, date('Y_m_d'));
        $body = '';

        foreach ($this->buffer as $record) {
            $body .= json_encode(['index' => ['_index' => $indexName]])."\n";
            $body .= json_encode($this->formatRecord($record))."\n";
        }

        $this->buffer = [];
        $this->sendBulk($body);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRecord(LogRecord $record): array
    {
        $data = [
            '@timestamp' => $record->datetime->format('c'),
            'level' => $record->level->value,
            'level_name' => strtoupper($record->level->name),
            'message' => $record->message,
            'channel' => $record->channel,
        ];

        $promotedKeys = [
            'trace_id',
            'request_id',
            'app_name',
            'request_uri',
            'request_method',
            'client_ip',
            'event_name',
            'step',
            'source_app',
            'target_app',
            'tool',
            'intent',
            'status',
            'duration_ms',
            'error_code',
            'agent_run_id',
            'sequence_order',
        ];
        foreach ($promotedKeys as $key) {
            if (isset($record->extra[$key])) {
                $data[$key] = $record->extra[$key];
            }
        }

        $filteredExtra = array_diff_key($record->extra, array_flip($promotedKeys));
        if ([] !== $filteredExtra) {
            $data['extra'] = $filteredExtra;
        }

        if ([] !== $record->context) {
            $context = $record->context;
            foreach ($promotedKeys as $key) {
                if (isset($context[$key])) {
                    $data[$key] = $context[$key];
                    unset($context[$key]);
                }
            }

            if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
                $e = $context['exception'];
                $data['exception'] = [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                unset($context['exception']);
            }

            if ([] !== $context) {
                $data['context'] = $context;
            }
        }

        return $data;
    }

    private function sendBulk(string $body): void
    {
        $url = rtrim($this->opensearchUrl, '/').'/_bulk';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-ndjson\r\n",
                'content' => $body,
                'timeout' => 3,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}
