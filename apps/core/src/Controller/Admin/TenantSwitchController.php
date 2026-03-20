<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\User;
use App\Tenant\TenantContextListener;
use App\Tenant\TenantRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TenantSwitchController extends AbstractController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {
    }

    #[Route('/admin/tenant/switch/{tenantId}', name: 'admin_tenant_switch', methods: ['POST'])]
    public function __invoke(
        string $tenantId,
        Request $request,
        #[CurrentUser] User $user,
    ): RedirectResponse {
        // Verify user has access to this tenant
        if (!in_array($tenantId, $user->getTenantIds(), true) && !$user->isSuperAdmin()) {
            throw $this->createAccessDeniedException('You do not have access to this tenant.');
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        if (null === $tenant) {
            throw $this->createNotFoundException('Tenant not found.');
        }

        $request->getSession()->set(TenantContextListener::SESSION_KEY, $tenantId);

        return $this->redirectToRoute('admin_dashboard');
    }
}
