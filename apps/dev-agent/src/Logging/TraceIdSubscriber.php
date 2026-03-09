<?php

declare(strict_types=1);

namespace App\Logging;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class TraceIdSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TraceContext $traceContext,
    ) {
    }

    /** @return array<string, array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 255],
            KernelEvents::RESPONSE => ['onResponse', -255],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $incomingTraceId = $event->getRequest()->headers->get('X-Trace-Id');
        $this->traceContext->initialize($incomingTraceId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-Trace-Id', $this->traceContext->getTraceId());
        $response->headers->set('X-Request-Id', $this->traceContext->getRequestId());
    }
}
