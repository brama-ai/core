<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\User;
use App\Tenant\TenantContext;
use App\Tenant\TenantRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class TenantExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        $userTenants = [];

        if ($user instanceof User) {
            $userTenants = $this->tenantRepository->findByUser($user->getUuid());
        }

        return [
            'currentTenant' => $this->tenantContext->getTenant(),
            'userTenants' => $userTenants,
        ];
    }
}
