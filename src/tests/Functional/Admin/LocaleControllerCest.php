<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

/**
 * Tests the locale switch controller for the admin panel.
 *
 * The locale switch endpoint is public (no auth required) — it only sets a cookie.
 * After setting the cookie, it redirects back to the referer.
 *
 * @see docs/features/i18n-locale/en/i18n-locale.md
 */
final class LocaleControllerCest
{
    public function switchLocaleToEnglishSetsCookie(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Referer', '/admin/login');
        $I->sendPost('/admin/locale/switch', ['locale' => 'en']);

        $I->seeCookie('locale');
        $I->assertSame('en', $I->grabCookie('locale'));
    }

    public function switchLocaleToUkrainianSetsCookie(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Referer', '/admin/login');
        $I->sendPost('/admin/locale/switch', ['locale' => 'uk']);

        $I->seeCookie('locale');
        $I->assertSame('uk', $I->grabCookie('locale'));
    }

    public function switchLocaleWithInvalidValueFallsBackToDefault(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Referer', '/admin/login');
        $I->sendPost('/admin/locale/switch', ['locale' => 'fr']);

        $I->seeCookie('locale');
        $I->assertSame('uk', $I->grabCookie('locale'));
    }

    public function switchLocaleWithNoValueFallsBackToDefault(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Referer', '/admin/login');
        $I->sendPost('/admin/locale/switch', []);

        $I->seeCookie('locale');
        $I->assertSame('uk', $I->grabCookie('locale'));
    }

    public function switchLocaleRedirectsToReferer(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Referer', '/admin/login');
        $I->sendPost('/admin/locale/switch', ['locale' => 'en']);

        // After redirect to /admin/login (public), we should see the login page
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('_username');
    }

    public function switchLocaleAppliesLocaleToSubsequentRequests(\FunctionalTester $I): void
    {
        // Switch to English
        $I->haveHttpHeader('Referer', '/admin/login');
        $I->sendPost('/admin/locale/switch', ['locale' => 'en']);

        // The login page should now be in English
        $I->sendGet('/admin/login');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Login');
    }
}
