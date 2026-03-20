<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\CoderAgent\CoderTaskRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CoderArtifactsApiController extends AbstractController
{
    public function __construct(
        private readonly CoderTaskRepositoryInterface $tasks,
    ) {
    }

    #[Route('/api/v1/internal/coder/{id}/artifacts', name: 'api_internal_coder_artifacts', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $task = $this->tasks->findById($id);
        if (null === $task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'task_id' => $id,
            'summary_path' => $task['summary_path'],
            'artifacts_path' => $task['artifacts_path'],
            'worktree_path' => $task['worktree_path'],
            'branch_name' => $task['branch_name'],
        ]);
    }
}
