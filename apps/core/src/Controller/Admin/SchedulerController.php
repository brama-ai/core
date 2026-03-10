<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Scheduler\ScheduledJobRepository;
use App\Security\AdminUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SchedulerController extends AbstractController
{
    public function __construct(
        private readonly ScheduledJobRepository $repository,
    ) {
    }

    #[Route('/admin/scheduler', name: 'admin_scheduler')]
    public function __invoke(#[CurrentUser] AdminUser $user): Response
    {
        $jobs = $this->repository->findAll();

        return $this->render('admin/scheduler/index.html.twig', [
            'jobs' => $jobs,
            'username' => $user->getUserIdentifier(),
        ]);
    }
}
