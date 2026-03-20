<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\CoderAgent\CoderTaskLogRepositoryInterface;
use App\CoderAgent\CoderTaskRepositoryInterface;
use App\CoderAgent\CoderWorkerRepositoryInterface;
use App\CoderAgent\TaskStatus;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class CoderDashboardController extends AbstractController
{
    public function __construct(
        private readonly CoderTaskRepositoryInterface $tasks,
        private readonly CoderTaskLogRepositoryInterface $logs,
        private readonly CoderWorkerRepositoryInterface $workers,
    ) {
    }

    #[Route('/admin/coder', name: 'admin_coder')]
    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $statusFilter = $request->query->get('status');
        $status = \is_string($statusFilter) && '' !== $statusFilter ? TaskStatus::tryFrom($statusFilter) : null;

        return $this->render('admin/coder/index.html.twig', [
            'username' => $user->getUserIdentifier(),
            'stats' => $this->tasks->getStats(),
            'tasks' => $this->tasks->findAll($status),
            'workers' => $this->workers->findAll(),
            'recent_activity' => $this->logs->findRecentActivity(),
            'status_filter' => $status?->value,
        ]);
    }
}
