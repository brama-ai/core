<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tenant;

use App\Tenant\Tenant;
use App\Tenant\TenantContext;
use Codeception\Test\Unit;

final class TenantContextTest extends Unit
{
    private TenantContext $context;

    protected function setUp(): void
    {
        $this->context = new TenantContext();
    }

    public function testInitiallyEmpty(): void
    {
        $this->assertFalse($this->context->isSet());
        $this->assertNull($this->context->getTenantId());
        $this->assertNull($this->context->getTenant());
    }

    public function testSetTenant(): void
    {
        $tenant = new Tenant('tid-1', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->context->set($tenant);

        $this->assertTrue($this->context->isSet());
        $this->assertSame('tid-1', $this->context->getTenantId());
        $this->assertSame($tenant, $this->context->getTenant());
    }

    public function testClear(): void
    {
        $tenant = new Tenant('tid-1', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->context->set($tenant);
        $this->context->clear();

        $this->assertFalse($this->context->isSet());
        $this->assertNull($this->context->getTenantId());
    }

    public function testRequireTenantIdThrowsWhenNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->context->requireTenantId();
    }

    public function testRequireTenantIdReturnsWhenSet(): void
    {
        $tenant = new Tenant('tid-1', 'Test', 'test', true, new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->context->set($tenant);

        $this->assertSame('tid-1', $this->context->requireTenantId());
    }
}
