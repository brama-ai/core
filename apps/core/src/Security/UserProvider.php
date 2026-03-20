<?php

declare(strict_types=1);

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<User>
 */
final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function loadUserByIdentifier(string $identifier): User
    {
        // Support login by username or email
        $row = $this->connection->fetchAssociative(
            'SELECT id, uuid, username, email, password, roles FROM users WHERE username = :id OR email = :id',
            ['id' => $identifier],
        );

        if (false === $row) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        /** @var list<string> $roles */
        $roles = json_decode((string) $row['roles'], true) ?? ['ROLE_USER'];

        $uuid = (string) $row['uuid'];
        \assert('' !== $uuid);

        $username = (string) $row['username'];
        \assert('' !== $username);

        // Load tenant memberships
        /** @var list<array{tenant_id: string, role: string}> $tenants */
        $tenants = $this->connection->fetchAllAssociative(
            'SELECT tenant_id, role FROM user_tenant WHERE user_id = :uuid ORDER BY joined_at',
            ['uuid' => $uuid],
        );

        return new User(
            $uuid,
            (int) $row['id'],
            $username,
            (string) $row['email'],
            (string) $row['password'],
            $roles,
            $tenants,
        );
    }

    public function refreshUser(UserInterface $user): User
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
