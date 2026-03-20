<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Locale\LocaleSubscriber;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController
{
    #[Route('/admin/locale/switch', name: 'admin_locale_switch', methods: ['POST'])]
    public function switch(Request $request): Response
    {
        $locale = $request->request->getString('locale', LocaleSubscriber::DEFAULT_LOCALE);

        if (!\in_array($locale, LocaleSubscriber::ALLOWED_LOCALES, true)) {
            $locale = LocaleSubscriber::DEFAULT_LOCALE;
        }

        $referer = $request->headers->get('referer', '/admin');

        $response = new RedirectResponse($referer);
        $response->headers->setCookie(
            Cookie::create(LocaleSubscriber::COOKIE_NAME)
                ->withValue($locale)
                ->withPath('/')
                ->withSameSite('lax')
                ->withExpires(new \DateTimeImmutable('+1 year'))
                ->withHttpOnly(false),
        );

        return $response;
    }
}
