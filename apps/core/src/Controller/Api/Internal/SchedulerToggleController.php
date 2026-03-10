<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Scheduler\ScheduledJobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SchedulerToggleController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepository $repository,
    ) {
    }

    #[Route('/api/v1/internal/scheduler/{id}/toggle', name: 'api_internal_scheduler_toggle', methods: ['POST'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $job = $this->repository->findById($id);

        if (null === $job) {
            return $this->json(['error' => sprintf('Scheduled job "%s" not found', $id)], Response::HTTP_NOT_FOUND);
        }

        $body = [];
        $content = $request->getContent();
        if ('' !== $content) {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                $body = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                $body = [];
            }
        }

        $enabled = isset($body['enabled']) ? (bool) $body['enabled'] : !(bool) $job['enabled'];

        $this->repository->toggleEnabled($id, $enabled);

        return $this->json(['status' => $enabled ? 'enabled' : 'disabled', 'id' => $id]);
    }
}
