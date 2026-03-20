<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin\Tenant;

/**
 * Tests tenant management CRUD operations.
 */
class TenantCrudCest
{
    private function login(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function tenantListRequiresAuth(\FunctionalTester $I): void
    {
        $I->sendGet('/admin/tenants');
        // Should redirect to login (302 or show login page)
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('_username');
    }

    public function tenantListAccessibleAfterLogin(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendGet('/admin/tenants');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Управління тенантами');
        $I->seeResponseContains('Default');
    }

    public function tenantCreatePageAccessible(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendGet('/admin/tenants/create');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Створити тенант');
    }

    public function tenantCreateEmptyNameShowsError(\FunctionalTester $I): void
    {
        $this->login($I);
        $I->sendPost('/admin/tenants/create', ['name' => '']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('обов');
    }

    public function tenantCreateSuccess(\FunctionalTester $I): void
    {
        $this->login($I);
        $uniqueName = 'Test Tenant '.bin2hex(random_bytes(4));
        $I->sendPost('/admin/tenants/create', ['name' => $uniqueName]);
        // After successful create, redirects to tenant list (followed automatically)
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Управління тенантами');
    }

    public function tenantEditPageAccessible(\FunctionalTester $I): void
    {
        $this->login($I);
        // Use default tenant UUID
        $I->sendGet('/admin/tenants/00000000-0000-4000-a000-000000000001/edit');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Default');
    }
}
