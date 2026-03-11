<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Scheduler\ScheduledJobRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SchedulerDeleteController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $repository,
    ) {
    }

    #[Route('/api/v1/internal/scheduler/{id}', name: 'api_internal_scheduler_delete', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
    public function __invoke(string $id): JsonResponse
    {
        $job = $this->repository->findById($id);

        if (null === $job) {
            return $this->json(['error' => 'Job not found'], Response::HTTP_NOT_FOUND);
        }

        if ('admin' !== ($job['source'] ?? 'manifest')) {
            return $this->json(['error' => 'Cannot delete manifest-created jobs'], Response::HTTP_FORBIDDEN);
        }

        $this->repository->deleteJob($id);

        return $this->json(['status' => 'deleted', 'id' => $id]);
    }
}
