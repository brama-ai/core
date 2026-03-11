<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Scheduler\CronExpressionHelperInterface;
use App\Scheduler\ScheduledJobRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SchedulerCreateController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $repository,
        private readonly CronExpressionHelperInterface $cronHelper,
    ) {
    }

    #[Route('/api/v1/internal/scheduler/create', name: 'api_internal_scheduler_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $body */
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $agentName = isset($body['agent_name']) ? trim((string) $body['agent_name']) : '';
        $jobName = isset($body['job_name']) ? trim((string) $body['job_name']) : '';
        $skillId = isset($body['skill_id']) ? trim((string) $body['skill_id']) : '';
        $cronExpression = isset($body['cron_expression']) && '' !== trim((string) $body['cron_expression'])
            ? trim((string) $body['cron_expression'])
            : null;
        $timezone = isset($body['timezone']) && '' !== trim((string) $body['timezone'])
            ? trim((string) $body['timezone'])
            : 'UTC';
        $maxRetries = (int) ($body['max_retries'] ?? 3);
        $retryDelaySeconds = (int) ($body['retry_delay_seconds'] ?? 60);

        $payload = [];
        if (isset($body['payload']) && is_string($body['payload']) && '' !== trim($body['payload'])) {
            try {
                $decoded = json_decode(trim($body['payload']), true, 512, JSON_THROW_ON_ERROR);
                $payload = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return $this->json(['error' => 'Invalid payload JSON'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } elseif (isset($body['payload']) && is_array($body['payload'])) {
            $payload = $body['payload'];
        }

        if ('' === $agentName || '' === $jobName || '' === $skillId) {
            return $this->json(['error' => 'agent_name, job_name, and skill_id are required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $cronExpression && !$this->cronHelper->isValid($cronExpression)) {
            return $this->json(['error' => 'Invalid cron expression'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $nextRunAt = null !== $cronExpression
            ? $this->cronHelper->computeNextRun($cronExpression, $timezone)->format('Y-m-d H:i:sP')
            : (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $this->repository->registerJob(
            $agentName,
            $jobName,
            $skillId,
            $payload,
            $cronExpression,
            $maxRetries,
            $retryDelaySeconds,
            $timezone,
            $nextRunAt,
            'admin',
        );

        return $this->json(['status' => 'created', 'agent_name' => $agentName, 'job_name' => $jobName]);
    }
}
