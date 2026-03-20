<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\CoderAgent\CoderTaskService;
use App\CoderAgent\DTO\UpdateTaskPriorityRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CoderTaskActionController extends AbstractController
{
    public function __construct(
        private readonly CoderTaskService $tasks,
    ) {
    }

    #[Route('/api/v1/internal/coder/{id}/queue', name: 'api_internal_coder_queue', requirements: ['id' => Requirement::UUID_V4], methods: ['POST'])]
    public function queue(string $id): JsonResponse
    {
        try {
            $this->tasks->queue($id);

            return $this->json(['status' => 'queued', 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/v1/internal/coder/{id}/cancel', name: 'api_internal_coder_cancel', requirements: ['id' => Requirement::UUID_V4], methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        try {
            $this->tasks->cancel($id);

            return $this->json(['status' => 'cancelled', 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/v1/internal/coder/{id}/retry', name: 'api_internal_coder_retry', requirements: ['id' => Requirement::UUID_V4], methods: ['POST'])]
    public function retry(string $id): JsonResponse
    {
        try {
            $this->tasks->retry($id);

            return $this->json(['status' => 'queued', 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/v1/internal/coder/{id}/priority', name: 'api_internal_coder_priority', requirements: ['id' => Requirement::UUID_V4], methods: ['POST'])]
    public function priority(string $id, Request $request): JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $priority = max(1, min(10, (int) ($payload['priority'] ?? 1)));
            $this->tasks->updatePriority(new UpdateTaskPriorityRequest($id, $priority));

            return $this->json(['status' => 'updated', 'id' => $id, 'priority' => $priority]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/api/v1/internal/coder/{id}', name: 'api_internal_coder_delete', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->tasks->delete($id);

            return $this->json(['status' => 'deleted', 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
