<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\TenantVoter;
use App\Security\User;
use App\Tenant\TenantContext;
use App\Tenant\TenantDeletionGuard;
use App\Tenant\TenantRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TenantsController extends AbstractController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantDeletionGuard $deletionGuard,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route('/admin/tenants', name: 'admin_tenants', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function list(): Response
    {
        $tenants = $this->tenantRepository->findAll();
        $memberCounts = $this->tenantRepository->countMembersAll();

        return $this->render('admin/tenants/index.html.twig', [
            'tenants' => $tenants,
            'memberCounts' => $memberCounts,
            'currentTenant' => $this->tenantContext->getTenant(),
        ]);
    }

    #[Route('/admin/tenants/create', name: 'admin_tenant_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));

            if ('' === $name) {
                return $this->render('admin/tenants/create.html.twig', [
                    'error' => 'Назва тенанта обов\'язкова.',
                    'currentTenant' => $this->tenantContext->getTenant(),
                ]);
            }

            $slug = $this->generateSlug($name);

            if (null !== $this->tenantRepository->findBySlug($slug)) {
                return $this->render('admin/tenants/create.html.twig', [
                    'error' => 'Тенант з таким slug вже існує.',
                    'currentTenant' => $this->tenantContext->getTenant(),
                ]);
            }

            $tenantId = $this->tenantRepository->create($name, $slug);
            $this->tenantRepository->assignUser($tenantId, $user->getUuid(), 'owner');

            return $this->redirectToRoute('admin_tenants');
        }

        return $this->render('admin/tenants/create.html.twig', [
            'error' => null,
            'currentTenant' => $this->tenantContext->getTenant(),
        ]);
    }

    #[Route('/admin/tenants/{id}/edit', name: 'admin_tenant_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request): Response
    {
        $tenant = $this->tenantRepository->findById($id);
        if (null === $tenant) {
            throw $this->createNotFoundException('Тенант не знайдено.');
        }

        $this->denyAccessUnlessGranted(TenantVoter::EDIT, $tenant);

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $enabled = (bool) $request->request->get('enabled', false);

            if ('' === $name) {
                return $this->render('admin/tenants/edit.html.twig', [
                    'tenant' => $tenant,
                    'error' => 'Назва тенанта обов\'язкова.',
                    'currentTenant' => $this->tenantContext->getTenant(),
                ]);
            }

            $this->tenantRepository->update($id, $name, $enabled);

            return $this->redirectToRoute('admin_tenants');
        }

        return $this->render('admin/tenants/edit.html.twig', [
            'tenant' => $tenant,
            'error' => null,
            'currentTenant' => $this->tenantContext->getTenant(),
        ]);
    }

    #[Route('/admin/tenants/{id}/delete', name: 'admin_tenant_delete', methods: ['POST'])]
    public function delete(string $id): RedirectResponse
    {
        $tenant = $this->tenantRepository->findById($id);
        if (null === $tenant) {
            throw $this->createNotFoundException('Тенант не знайдено.');
        }

        $this->denyAccessUnlessGranted(TenantVoter::DELETE, $tenant);

        $reasons = $this->deletionGuard->check($id);
        if ([] !== $reasons) {
            $this->addFlash('error', 'Неможливо видалити тенант: '.implode(' ', $reasons));

            return $this->redirectToRoute('admin_tenants');
        }

        $this->tenantRepository->delete($id);
        $this->addFlash('success', sprintf('Тенант "%s" видалено.', $tenant->getName()));

        return $this->redirectToRoute('admin_tenants');
    }

    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = (string) preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = (string) preg_replace('/-+/', '-', $slug);

        return trim($slug, '-');
    }
}
