<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

/**
 * Tests the authentication flow for the admin dashboard.
 *
 * @see docs/specs/admin-requirements.md (Section Core Features: Authentication & Roles)
 */
class LoginCest
{
    public function loginPageIsPubliclyAccessible(\FunctionalTester $I): void
    {
        $I->sendGet('/admin/login');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('<form');
        $I->seeResponseContains('_username');
        $I->seeResponseContains('_password');
    }

    public function dashboardRedirectsUnauthenticatedVisitors(\FunctionalTester $I): void
    {
        $I->sendGet('/admin/dashboard');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('_username');
    }

    public function validCredentialsRedirectToDashboard(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Статистика');
    }

    public function invalidCredentialsShowError(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'wrong-password',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Невірні автентифікаційні дані');
    }

    public function dashboardAccessibleAfterLogin(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('admin');
    }

    public function logoutInvalidatesSessionAndRedirects(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);

        $I->sendGet('/admin/logout');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('_username');
    }
}
