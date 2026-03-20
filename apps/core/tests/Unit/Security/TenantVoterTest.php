<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\TenantVoter;
use App\Security\User;
use App\Tenant\Tenant;
use Codeception\Test\Unit;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TenantVoterTest extends Unit
{
    private TenantVoter $voter;
    private Tenant $tenant;

    protected function setUp(): void
    {
        $this->voter = new TenantVoter();
        $this->tenant = new Tenant('tid-1', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testSuperAdminGrantsAll(): void
    {
        $user = new User('uuid', 0, 'admin', 'a@t', 'p', ['ROLE_SUPER_ADMIN'], []);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_SUPER_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::DELETE]));
    }

    public function testOwnerCanViewEditDelete(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'tid-1', 'role' => 'owner']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::DELETE]));
    }

    public function testAdminCanViewAndEditButNotDelete(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'tid-1', 'role' => 'admin']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [TenantVoter::DELETE]));
    }

    public function testMemberCanOnlyView(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'tid-1', 'role' => 'member']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [TenantVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [TenantVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [TenantVoter::DELETE]));
    }

    public function testNonMemberDeniedAll(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'other', 'role' => 'owner']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [TenantVoter::VIEW]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [TenantVoter::EDIT]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [TenantVoter::DELETE]));
    }

    public function testAbstainsOnUnsupportedAttribute(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], []);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $this->tenant, ['UNKNOWN_ATTR']));
    }

    public function testAbstainsOnNonTenantSubject(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], []);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, new \stdClass(), [TenantVoter::VIEW]));
    }
}
