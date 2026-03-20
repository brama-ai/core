<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\User;
use Codeception\Test\Unit;

final class UserTest extends Unit
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User(
            uuid: 'aaaa-bbbb-cccc',
            legacyId: 1,
            username: 'admin',
            email: 'admin@localhost',
            password: 'hashed',
            roles: ['ROLE_SUPER_ADMIN'],
            tenants: [
                ['tenant_id' => 'tenant-1', 'role' => 'owner'],
                ['tenant_id' => 'tenant-2', 'role' => 'member'],
            ],
        );
    }

    public function testGetters(): void
    {
        $this->assertSame('aaaa-bbbb-cccc', $this->user->getUuid());
        $this->assertSame(1, $this->user->getLegacyId());
        $this->assertSame('admin', $this->user->getUserIdentifier());
        $this->assertSame('admin', $this->user->getUsername());
        $this->assertSame('admin@localhost', $this->user->getEmail());
        $this->assertSame('hashed', $this->user->getPassword());
        $this->assertSame(['ROLE_SUPER_ADMIN'], $this->user->getRoles());
    }

    public function testGetTenantIds(): void
    {
        $this->assertSame(['tenant-1', 'tenant-2'], $this->user->getTenantIds());
    }

    public function testGetTenantRole(): void
    {
        $this->assertSame('owner', $this->user->getTenantRole('tenant-1'));
        $this->assertSame('member', $this->user->getTenantRole('tenant-2'));
        $this->assertNull($this->user->getTenantRole('unknown'));
    }

    public function testIsSuperAdmin(): void
    {
        $this->assertTrue($this->user->isSuperAdmin());

        $regularUser = new User('uuid', 0, 'user', 'user@test', 'pass', ['ROLE_USER'], []);
        $this->assertFalse($regularUser->isSuperAdmin());
    }

    public function testDefaultRoles(): void
    {
        $user = new User('uuid', 0, 'user', 'user@test', 'pass');
        $this->assertSame(['ROLE_USER'], $user->getRoles());
        $this->assertSame([], $user->getTenants());
    }
}
