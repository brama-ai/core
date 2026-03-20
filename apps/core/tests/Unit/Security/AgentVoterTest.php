<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\AgentVoter;
use App\Security\User;
use App\Tenant\Tenant;
use Codeception\Test\Unit;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AgentVoterTest extends Unit
{
    private AgentVoter $voter;
    private Tenant $tenant;

    protected function setUp(): void
    {
        $this->voter = new AgentVoter();
        $this->tenant = new Tenant('tid-1', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testSuperAdminGrantsAll(): void
    {
        $user = new User('uuid', 0, 'admin', 'a@t', 'p', ['ROLE_SUPER_ADMIN'], []);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_SUPER_ADMIN']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [AgentVoter::INSTALL]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [AgentVoter::MANAGE]));
    }

    public function testOwnerCanInstallAndManage(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'tid-1', 'role' => 'owner']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [AgentVoter::INSTALL]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [AgentVoter::MANAGE]));
    }

    public function testAdminCanInstallAndManage(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'tid-1', 'role' => 'admin']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [AgentVoter::INSTALL]));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $this->tenant, [AgentVoter::MANAGE]));
    }

    public function testMemberDenied(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], [['tenant_id' => 'tid-1', 'role' => 'member']]);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [AgentVoter::INSTALL]));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [AgentVoter::MANAGE]));
    }

    public function testNonMemberDenied(): void
    {
        $user = new User('uuid', 0, 'u', 'u@t', 'p', ['ROLE_USER'], []);
        $token = new UsernamePasswordToken($user, 'admin', ['ROLE_USER']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->tenant, [AgentVoter::INSTALL]));
    }
}
