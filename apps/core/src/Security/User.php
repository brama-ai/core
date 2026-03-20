<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string                             $uuid
     * @param non-empty-string                             $username
     * @param list<string>                                 $roles
     * @param list<array{tenant_id: string, role: string}> $tenants
     */
    public function __construct(
        private readonly string $uuid,
        private readonly int $legacyId,
        private readonly string $username,
        private readonly string $email,
        private readonly string $password,
        private readonly array $roles = ['ROLE_USER'],
        private readonly array $tenants = [],
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getLegacyId(): int
    {
        return $this->legacyId;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return list<array{tenant_id: string, role: string}>
     */
    public function getTenants(): array
    {
        return $this->tenants;
    }

    /**
     * @return list<string>
     */
    public function getTenantIds(): array
    {
        return array_map(static fn (array $t): string => $t['tenant_id'], $this->tenants);
    }

    public function getTenantRole(string $tenantId): ?string
    {
        foreach ($this->tenants as $t) {
            if ($t['tenant_id'] === $tenantId) {
                return $t['role'];
            }
        }

        return null;
    }

    public function isSuperAdmin(): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
