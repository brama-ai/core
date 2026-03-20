<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\CoderAgent\CoderTaskLogRepositoryInterface;
use App\CoderAgent\CoderTaskRepositoryInterface;
use App\CoderAgent\CoderTaskService;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class CoderDetailController extends AbstractController
{
    public function __construct(
        private readonly CoderTaskRepositoryInterface $tasks,
        private readonly CoderTaskLogRepositoryInterface $logs,
        private readonly CoderTaskService $service,
    ) {
    }

    #[Route('/admin/coder/{id}', name: 'admin_coder_detail', requirements: ['id' => Requirement::UUID_V4], methods: ['GET'])]
    public function __invoke(string $id, #[CurrentUser] User $user): Response
    {
        $task = $this->tasks->findById($id);
        if (null === $task) {
            throw $this->createNotFoundException();
        }

        $task = $this->service->reconcile($id);

        return $this->render('admin/coder/detail.html.twig', [
            'username' => $user->getUserIdentifier(),
            'task' => $task,
            'logs' => $this->logs->findByTask($id),
            'stage_progress' => $this->decodeStageProgress($task),
        ]);
    }

    /**
     * @param array<string, mixed> $task
     *
     * @return array<string, mixed>
     */
    private function decodeStageProgress(array $task): array
    {
        $raw = $task['stage_progress'] ?? '[]';
        if (\is_array($raw)) {
            return $raw;
        }

        if (\is_string($raw)) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                return \is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return [];
            }
        }

        return [];
    }
}
