<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * Response from an A2A agent after processing a request.
 *
 * Contains the task status, an optional result payload (on success),
 * error information (on failure), and a task ID for async operations.
 */
final readonly class A2AResponse
{
    /**
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        /** @var A2AResponseStatus Task execution status */
        public A2AResponseStatus $status,
        /** @var string Request identifier echoed back for correlation */
        public string $requestId = '',
        /** @var array<string, mixed>|null Successful result payload (null if failed) */
        public ?array $result = null,
        /** @var string|null Error message if status is failed */
        public ?string $error = null,
        /** @var string|null Task identifier for async task tracking */
        public ?string $taskId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusValue = (string) ($data['status'] ?? 'failed');
        $status = A2AResponseStatus::tryFrom($statusValue) ?? A2AResponseStatus::Failed;

        return new self(
            status: $status,
            requestId: (string) ($data['request_id'] ?? ''),
            result: \is_array($data['result'] ?? null) ? $data['result'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
            taskId: isset($data['task_id']) ? (string) $data['task_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'status' => $this->status->value,
            'request_id' => $this->requestId,
        ];

        if (null !== $this->result) {
            $result['result'] = $this->result;
        }
        if (null !== $this->error) {
            $result['error'] = $this->error;
        }
        if (null !== $this->taskId) {
            $result['task_id'] = $this->taskId;
        }

        return $result;
    }
}
