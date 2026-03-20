<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin\Tenant;

/**
 * Tests tenant context switching in the admin panel.
 */
class TenantSwitchCest
{
    private function login(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function switchTenantRequiresPost(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendGet('/admin/tenant/switch/00000000-0000-4000-a000-000000000001');
        $I->seeResponseCodeIs(405);
    }

    public function switchTenantRedirectsToDashboard(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendPost('/admin/tenant/switch/00000000-0000-4000-a000-000000000001');
        // After switch, redirects to dashboard (followed automatically)
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Статистика');
    }

    public function switchToNonExistentTenantReturns404(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendPost('/admin/tenant/switch/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(404);
    }

    public function dashboardShowsTenantContext(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendGet('/admin/dashboard');
        $I->seeResponseCodeIs(200);
        // Dashboard should show current tenant name
        $I->seeResponseContains('Default');
    }
}
