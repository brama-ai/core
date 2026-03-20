<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Security\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Sets the TenantContext on each request from the session's selected tenant,
 * or auto-selects the user's first tenant if none is set.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
final class TenantContextListener
{
    public const SESSION_KEY = '_tenant_id';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $tenantId = $session->get(self::SESSION_KEY);

        // Validate that the stored tenant ID actually belongs to this user
        if (is_string($tenantId) && in_array($tenantId, $user->getTenantIds(), true)) {
            $tenant = $this->tenantRepository->findById($tenantId);
            if (null !== $tenant) {
                $this->tenantContext->set($tenant);

                return;
            }
        }

        // Auto-select first tenant
        $tenantIds = $user->getTenantIds();
        if ([] !== $tenantIds) {
            $tenant = $this->tenantRepository->findById($tenantIds[0]);
            if (null !== $tenant) {
                $this->tenantContext->set($tenant);
                $session->set(self::SESSION_KEY, $tenant->getId());
            }
        }
    }
}
