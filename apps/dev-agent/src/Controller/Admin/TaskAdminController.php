<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DevTaskLogRepository;
use App\Repository\DevTaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TaskAdminController extends AbstractController
{
    public function __construct(
        private readonly DevTaskRepository $taskRepo,
        private readonly DevTaskLogRepository $logRepo,
    ) {
    }

    #[Route('/admin/tasks', name: 'admin_tasks', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status');
        $tasks = $this->taskRepo->findRecent(50, \is_string($statusFilter) ? $statusFilter : null);
        $stats = $this->taskRepo->getStats();

        return $this->render('admin/tasks/index.html.twig', [
            'tasks' => $tasks,
            'stats' => $stats,
            'status_filter' => $statusFilter,
        ]);
    }

    #[Route('/admin/tasks/create', name: 'admin_tasks_create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('admin/tasks/create.html.twig');
    }

    #[Route('/admin/tasks/{id}', name: 'admin_tasks_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): Response
    {
        $task = $this->taskRepo->findById($id);
        if (null === $task) {
            throw $this->createNotFoundException('Task not found');
        }

        $logs = $this->logRepo->findByTaskId($id);

        return $this->render('admin/tasks/detail.html.twig', [
            'task' => $task,
            'logs' => $logs,
        ]);
    }
}
