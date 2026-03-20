<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant;

use App\Tenant\TenantDeletionGuard;
use App\Tenant\TenantRepositoryInterface;
use Codeception\Test\Unit;

final class TenantDeletionGuardTest extends Unit
{
    public function testAllowsDeletionWhenEmpty(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('countActiveAgents')->willReturn(0);
        $repo->method('countEnabledJobs')->willReturn(0);

        $guard = new TenantDeletionGuard($repo);
        $reasons = $guard->check('tenant-1');

        $this->assertSame([], $reasons);
    }

    public function testRejectsWhenActiveAgentsExist(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('countActiveAgents')->willReturn(3);
        $repo->method('countEnabledJobs')->willReturn(0);

        $guard = new TenantDeletionGuard($repo);
        $reasons = $guard->check('tenant-1');

        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('3 active agent(s)', $reasons[0]);
    }

    public function testRejectsWhenEnabledJobsExist(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('countActiveAgents')->willReturn(0);
        $repo->method('countEnabledJobs')->willReturn(5);

        $guard = new TenantDeletionGuard($repo);
        $reasons = $guard->check('tenant-1');

        $this->assertCount(1, $reasons);
        $this->assertStringContainsString('5 enabled scheduled job(s)', $reasons[0]);
    }

    public function testRejectsWithMultipleReasons(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('countActiveAgents')->willReturn(2);
        $repo->method('countEnabledJobs')->willReturn(3);

        $guard = new TenantDeletionGuard($repo);
        $reasons = $guard->check('tenant-1');

        $this->assertCount(2, $reasons);
    }
}
