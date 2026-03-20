<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\CoderAgent\CoderTaskLogRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CoderLogsApiController extends AbstractController
{
    public function __construct(
        private readonly CoderTaskLogRepositoryInterface $logs,
    ) {
    }

    #[Route('/api/v1/internal/coder/{id}/logs', name: 'api_internal_coder_logs', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $afterId = $request->query->get('after_id');

        return $this->json([
            'task_id' => $id,
            'logs' => $this->logs->findByTask($id, 200, \is_string($afterId) && '' !== $afterId ? $afterId : null),
        ]);
    }
}
