<?php

declare(strict_types=1);

namespace App\Locale;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public const string COOKIE_NAME = 'locale';
    public const string DEFAULT_LOCALE = 'uk';
    public const array ALLOWED_LOCALES = ['uk', 'en'];

    /** @return array<string, array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->cookies->get(self::COOKIE_NAME, self::DEFAULT_LOCALE);

        if (!\in_array($locale, self::ALLOWED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        $request->setLocale($locale);
    }
}
