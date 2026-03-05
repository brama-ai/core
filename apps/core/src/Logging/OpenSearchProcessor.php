<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class OpenSearchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TraceContext $traceContext,
        private readonly RequestStack $requestStack,
        private readonly string $appName,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        $extra['trace_id'] = $this->traceContext->getTraceId();
        $extra['request_id'] = $this->traceContext->getRequestId();
        $extra['app_name'] = $this->appName;

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $extra['request_uri'] = $request->getRequestUri();
            $extra['request_method'] = $request->getMethod();
            $extra['client_ip'] = $request->getClientIp();
        }

        return $record->with(extra: $extra);
    }
}
