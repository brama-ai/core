<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Scheduler\ScheduledJobRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SchedulerToggleController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $repository,
    ) {
    }

    #[Route('/api/v1/internal/scheduler/{id}/toggle', name: 'api_internal_scheduler_toggle', requirements: ['id' => Requirement::UUID_V4], methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $job = $this->repository->findById($id);

        if (null === $job) {
            return $this->json(['error' => sprintf('Scheduled job "%s" not found', $id)], Response::HTTP_NOT_FOUND);
        }

        try {
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $body = [];
        }

        $enabled = isset($body['enabled']) ? (bool) $body['enabled'] : !(bool) $job['enabled'];

        $this->repository->toggleEnabled($id, $enabled);

        return $this->json(['status' => 'updated', 'id' => $id, 'enabled' => $enabled]);
    }
}
