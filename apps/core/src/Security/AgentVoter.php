<?php

declare(strict_types=1);

namespace App\Security;

use App\Tenant\Tenant;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants/denies agent management operations within a tenant context.
 *
 * Supported attributes: AGENT_INSTALL, AGENT_MANAGE
 * Subject: Tenant entity (the tenant in which the agent operation is performed)
 *
 * @extends Voter<string, Tenant>
 */
final class AgentVoter extends Voter
{
    public const INSTALL = 'AGENT_INSTALL';
    public const MANAGE = 'AGENT_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Tenant
            && in_array($attribute, [self::INSTALL, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $role = $user->getTenantRole($subject->getId());

        if (null === $role) {
            return false;
        }

        // Only owners and admins can install/manage agents
        return in_array($role, ['owner', 'admin'], true);
    }
}
