<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Scheduler\ScheduledJobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SchedulerRunNowController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepository $repository,
    ) {
    }

    #[Route('/api/v1/internal/scheduler/{id}/run', name: 'api_internal_scheduler_run_now', methods: ['POST'])]
    public function __invoke(string $id): JsonResponse
    {
        $job = $this->repository->findById($id);

        if (null === $job) {
            return $this->json(['error' => sprintf('Scheduled job "%s" not found', $id)], Response::HTTP_NOT_FOUND);
        }

        $this->repository->triggerNow($id);

        return $this->json(['status' => 'triggered', 'id' => $id]);
    }
}
