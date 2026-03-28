<?php

declare(strict_types=1);

namespace App\Tests\Functional\EdgeAuth;

/**
 * Tests the edge auth login flow including Cloudflare Turnstile CAPTCHA integration.
 *
 * Turnstile is disabled in the test environment (TURNSTILE_ENABLED=false),
 * so these tests verify the login flow without CAPTCHA.
 * Turnstile-specific behaviour (token validation, fail-closed) is covered by
 * the unit tests in tests/Unit/EdgeAuth/TurnstileVerifierTest.php.
 */
class LoginControllerCest
{
    public function loginPageIsPubliclyAccessible(\FunctionalTester $I): void
    {
        $I->sendGet('/edge/auth/login');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('<form');
        $I->seeResponseContains('_username');
        $I->seeResponseContains('_password');
    }

    public function loginPageDoesNotShowTurnstileWidgetWhenDisabled(\FunctionalTester $I): void
    {
        // TURNSTILE_ENABLED=false in test env — widget must not be rendered
        $I->sendGet('/edge/auth/login');
        $I->seeResponseCodeIs(200);
        $I->dontSeeResponseContains('cf-turnstile');
        $I->dontSeeResponseContains('challenges.cloudflare.com');
    }

    public function loginPageAcceptsRedirectParameter(\FunctionalTester $I): void
    {
        $I->sendGet('/edge/auth/login?rd=/admin/dashboard');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('name="rd"');
    }

    public function invalidCredentialsReturn401WithErrorMessage(\FunctionalTester $I): void
    {
        $I->sendPost('/edge/auth/login', [
            '_username' => 'admin',
            '_password' => 'wrong-password',
        ]);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContains('Невірний логін або пароль');
    }

    public function emptyCredentialsReturn401WithErrorMessage(\FunctionalTester $I): void
    {
        $I->sendPost('/edge/auth/login', [
            '_username' => '',
            '_password' => '',
        ]);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContains('Введіть логін і пароль');
    }

    public function unknownUserReturn401WithErrorMessage(\FunctionalTester $I): void
    {
        $I->sendPost('/edge/auth/login', [
            '_username' => 'nonexistent-user-xyz',
            '_password' => 'any-password',
        ]);
        $I->seeResponseCodeIs(401);
        $I->seeResponseContains('Невірний логін або пароль');
    }
}
