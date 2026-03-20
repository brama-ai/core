<?php

declare(strict_types=1);

namespace App\Tests\Unit\Locale;

use App\Locale\LocaleSubscriber;
use Codeception\Test\Unit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class LocaleSubscriberTest extends Unit
{
    public function testGetSubscribedEventsReturnsCorrectEvent(): void
    {
        $events = LocaleSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertSame(['onKernelRequest', 100], $events['kernel.request']);
    }

    public function testSetsLocaleFromValidCookie(): void
    {
        $subscriber = new LocaleSubscriber();

        $request = new Request();
        $request->cookies->set('locale', 'en');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        $this->assertSame('en', $request->getLocale());
    }

    public function testSetsLocaleFromUkrainianCookie(): void
    {
        $subscriber = new LocaleSubscriber();

        $request = new Request();
        $request->cookies->set('locale', 'uk');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        $this->assertSame('uk', $request->getLocale());
    }

    public function testDefaultsToUkrainianWhenNoCookie(): void
    {
        $subscriber = new LocaleSubscriber();

        $request = new Request();

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        $this->assertSame('uk', $request->getLocale());
    }

    public function testFallsBackToDefaultOnInvalidCookie(): void
    {
        $subscriber = new LocaleSubscriber();

        $request = new Request();
        $request->cookies->set('locale', 'fr');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        $this->assertSame('uk', $request->getLocale());
    }

    public function testFallsBackToDefaultOnEmptyCookie(): void
    {
        $subscriber = new LocaleSubscriber();

        $request = new Request();
        $request->cookies->set('locale', '');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        $this->assertSame('uk', $request->getLocale());
    }

    public function testIgnoresSubRequests(): void
    {
        $subscriber = new LocaleSubscriber();

        $request = new Request();
        $request->cookies->set('locale', 'en');
        $request->setLocale('uk');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        $this->assertSame('uk', $request->getLocale());
    }

    public function testConstantsHaveCorrectValues(): void
    {
        $this->assertSame('locale', LocaleSubscriber::COOKIE_NAME);
        $this->assertSame('uk', LocaleSubscriber::DEFAULT_LOCALE);
        $this->assertSame(['uk', 'en'], LocaleSubscriber::ALLOWED_LOCALES);
    }
}