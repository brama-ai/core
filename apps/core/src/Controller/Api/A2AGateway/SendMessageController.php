<?php

declare(strict_types=1);

namespace App\Controller\Api\A2AGateway;

use App\A2AGateway\A2AClient;
use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use App\Observability\LangfuseIngestionClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SendMessageController extends AbstractController
{
    public function __construct(
        private readonly A2AClient $a2aClient,
        private readonly LangfuseIngestionClient $langfuse,
        private readonly LoggerInterface $logger,
        private readonly PayloadSanitizer $payloadSanitizer,
        private readonly string $gatewayToken,
    ) {
    }

    #[Route('/api/v1/a2a/send-message', name: 'api_a2a_send_message', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            $this->logger->warning('Unauthorized invoke attempt', [
                'ip' => $request->getClientIp(),
                'event_name' => 'core.invoke.auth_failed',
                'step' => 'invoke_receive',
                'source_app' => 'core',
                'target_app' => 'openclaw',
                'status' => 'failed',
                'error_code' => 'unauthorized',
                'sequence_order' => (int) round(microtime(true) * 1000000),
            ]);

            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            /** @var array<string, mixed> $body */
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logger->warning(
                'Invalid JSON in invoke request',
                TraceEvent::build('core.invoke.invalid_json', 'invoke_receive', 'core', 'failed', [
                    'target_app' => 'openclaw',
                    'error_code' => 'invalid_json',
                ]),
            );

            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $traceId = (string) ($body['trace_id'] ?? uniqid('trace_', true));
        $requestId = (string) ($body['request_id'] ?? uniqid('req_', true));
        $tool = (string) ($body['tool'] ?? '');
        if ('' === $tool) {
            $this->logger->warning(
                'Invoke request missing tool field',
                TraceEvent::build('core.invoke.missing_tool', 'invoke_receive', 'core', 'failed', [
                    'target_app' => 'openclaw',
                    'trace_id' => $traceId,
                    'request_id' => $requestId,
                    'error_code' => 'missing_tool',
                ]),
            );

            return $this->json([
                'error' => 'tool is required',
                'trace_id' => $traceId,
                'request_id' => $requestId,
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $input */
        $input = (array) ($body['input'] ?? []);
        $sanitizedInput = $this->payloadSanitizer->sanitize($input);

        $this->logger->info(
            'Invoke request received',
            TraceEvent::build('core.invoke.received', 'invoke_receive', 'core', 'started', [
                'target_app' => 'openclaw',
                'tool' => $tool,
                'trace_id' => $traceId,
                'request_id' => $requestId,
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );

        $start = microtime(true);
        $result = $this->a2aClient->invoke($tool, $input, $traceId, $requestId);
        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $this->langfuse->recordOpenClawInvoke($traceId, $requestId, $tool, $input, $result, $durationMs);
        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);

        $status = (string) ($result['status'] ?? 'unknown');
        $this->logger->info(
            'Invoke completed',
            TraceEvent::build('core.invoke.completed', 'invoke_complete', 'core', $status, [
                'target_app' => 'openclaw',
                'tool' => $tool,
                'duration_ms' => $durationMs,
                'trace_id' => $traceId,
                'request_id' => $requestId,
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
                'error_code' => 'failed' === $status ? (string) ($result['reason'] ?? 'invoke_failed') : null,
            ]),
        );

        return $this->json(array_merge($result, [
            'trace_id' => $traceId,
            'request_id' => $requestId,
        ]));
    }

    private function isAuthorized(Request $request): bool
    {
        if ('' === $this->gatewayToken) {
            return false;
        }

        $header = $request->headers->get('Authorization', '');

        return 'Bearer '.$this->gatewayToken === $header;
    }
}
