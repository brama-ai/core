<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final class TurnstileLoginListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly TurnstileVerifier $verifier,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', 512],
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        if (!$this->verifier->isEnabled()) {
            return;
        }

        if (!$this->verifier->verify($request)) {
            throw new CustomUserMessageAuthenticationException('Не вдалося пройти перевірку CAPTCHA. Спробуйте ще раз.');
        }
    }
}
