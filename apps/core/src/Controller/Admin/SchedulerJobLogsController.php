<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Scheduler\ScheduledJobRepositoryInterface;
use App\Scheduler\SchedulerJobLogRepositoryInterface;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SchedulerJobLogsController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $jobRepository,
        private readonly SchedulerJobLogRepositoryInterface $logRepository,
    ) {
    }

    #[Route('/admin/scheduler/{id}/logs', name: 'admin_scheduler_logs', requirements: ['id' => Requirement::UUID_V4])]
    public function __invoke(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $job = $this->jobRepository->findById($id);

        if (null === $job) {
            throw $this->createNotFoundException('Job not found');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $logs = $this->logRepository->findByJob($id, $limit, $offset);
        $total = $this->logRepository->countByJob($id);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->render('admin/scheduler/logs.html.twig', [
            'job' => $job,
            'logs' => $logs,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'username' => $user->getUserIdentifier(),
        ]);
    }
}
