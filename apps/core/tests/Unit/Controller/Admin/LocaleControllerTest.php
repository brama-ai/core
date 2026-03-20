<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\LocaleController;
use App\Locale\LocaleSubscriber;
use Codeception\Test\Unit;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class LocaleControllerTest extends Unit
{
    public function testSwitchSetsUkrainianLocaleCookie(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->request->set('locale', 'uk');
        $request->headers->set('referer', '/admin/dashboard');

        $response = $controller->switch($request);
        \assert($response instanceof RedirectResponse);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/admin/dashboard', $response->getTargetUrl());

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);

        $cookie = $cookies[0];
        $this->assertSame(LocaleSubscriber::COOKIE_NAME, $cookie->getName());
        $this->assertSame('uk', $cookie->getValue());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testSwitchSetsEnglishLocaleCookie(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->request->set('locale', 'en');
        $request->headers->set('referer', '/admin/agents');

        $response = $controller->switch($request);
        \assert($response instanceof RedirectResponse);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/admin/agents', $response->getTargetUrl());

        $cookies = $response->headers->getCookies();
        $cookie = $cookies[0];
        $this->assertSame('en', $cookie->getValue());
    }

    public function testSwitchFallsBackToDefaultOnInvalidLocale(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->request->set('locale', 'fr');
        $request->headers->set('referer', '/admin');

        $response = $controller->switch($request);

        $this->assertSame(302, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        $cookie = $cookies[0];
        $this->assertSame(LocaleSubscriber::DEFAULT_LOCALE, $cookie->getValue());
    }

    public function testSwitchFallsBackToDefaultOnMissingLocale(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->headers->set('referer', '/admin/settings');

        $response = $controller->switch($request);

        $this->assertSame(302, $response->getStatusCode());

        $cookies = $response->headers->getCookies();
        $cookie = $cookies[0];
        $this->assertSame(LocaleSubscriber::DEFAULT_LOCALE, $cookie->getValue());
    }

    public function testSwitchRedirectsToReferer(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->request->set('locale', 'en');
        $request->headers->set('referer', '/admin/chats?page=2');

        $response = $controller->switch($request);
        \assert($response instanceof RedirectResponse);

        $this->assertSame('/admin/chats?page=2', $response->getTargetUrl());
    }

    public function testSwitchRedirectsToAdminWhenNoReferer(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->request->set('locale', 'en');

        $response = $controller->switch($request);
        \assert($response instanceof RedirectResponse);

        $this->assertSame('/admin', $response->getTargetUrl());
    }

    public function testCookieExpiresInOneYear(): void
    {
        $controller = new LocaleController();

        $request = new Request();
        $request->request->set('locale', 'en');
        $request->headers->set('referer', '/admin');

        $response = $controller->switch($request);

        $cookies = $response->headers->getCookies();
        $cookie = $cookies[0];

        $expires = $cookie->getExpiresTime();
        $expectedMin = time() + 365 * 24 * 60 * 60 - 60;
        $expectedMax = time() + 365 * 24 * 60 * 60 + 60;

        $this->assertGreaterThanOrEqual($expectedMin, $expires);
        $this->assertLessThanOrEqual($expectedMax, $expires);
    }
}