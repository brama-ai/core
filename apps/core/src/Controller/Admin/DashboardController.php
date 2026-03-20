<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\AgentRegistry\AgentRegistryInterface;
use App\Dashboard\DashboardMetricsService;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly DashboardMetricsService $metricsService,
    ) {
    }

    #[Route('/admin/', name: 'admin_index')]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('admin_dashboard', [], Response::HTTP_FOUND);
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $all = $this->registry->findAll();
        $enabled = array_filter($all, static fn (array $a): bool => (bool) $a['enabled']);

        $metrics = $this->metricsService->getMetrics();

        return $this->render('admin/dashboard.html.twig', [
            'username' => $user->getUserIdentifier(),
            'agents_total' => count($all),
            'agents_enabled' => count($enabled),
            'agents_disabled' => count($all) - count($enabled),
            'metrics' => $metrics,
        ]);
    }
}
