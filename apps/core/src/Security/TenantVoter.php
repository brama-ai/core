<?php

declare(strict_types=1);

namespace App\Security;

use App\Tenant\Tenant;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants/denies access to tenant-scoped operations based on the user's role within the tenant.
 *
 * Supported attributes: TENANT_VIEW, TENANT_EDIT, TENANT_DELETE
 * Subject: Tenant entity
 *
 * @extends Voter<string, Tenant>
 */
final class TenantVoter extends Voter
{
    public const VIEW = 'TENANT_VIEW';
    public const EDIT = 'TENANT_EDIT';
    public const DELETE = 'TENANT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Tenant
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Super admins can do anything
        if ($user->isSuperAdmin()) {
            return true;
        }

        $role = $user->getTenantRole($subject->getId());

        if (null === $role) {
            return false; // User is not a member of this tenant
        }

        return match ($attribute) {
            self::VIEW => true, // Any member can view
            self::EDIT => in_array($role, ['owner', 'admin'], true),
            self::DELETE => 'owner' === $role,
            default => false,
        };
    }
}
