<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\A2A\DevAgentA2AHandler;
use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class A2AController extends AbstractController
{
    public function __construct(
        private readonly DevAgentA2AHandler $handler,
        private readonly PayloadSanitizer $payloadSanitizer,
        private readonly LoggerInterface $logger,
        private readonly string $internalToken,
    ) {
    }

    #[Route('/api/v1/a2a', name: 'api_a2a', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-Platform-Internal-Token');
        if ($token !== $this->internalToken) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data) || !isset($data['intent'])) {
            $sanitized = $this->payloadSanitizer->sanitize([
                'raw' => $request->getContent(),
                'ip' => $request->getClientIp(),
            ]);
            $this->logger->warning(
                'Invalid A2A payload received',
                TraceEvent::build('dev.a2a.inbound.invalid_payload', 'a2a_inbound', 'dev-agent', 'failed', [
                    'target_app' => 'core',
                    'error_code' => 'invalid_payload',
                    'step_input' => $sanitized['data'],
                    'capture_meta' => $sanitized['capture_meta'],
                ]),
            );

            return $this->json(
                ['error' => 'Invalid A2A payload: intent is required'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $traceId = (string) ($data['trace_id'] ?? uniqid('trace_', true));
        $requestId = (string) ($data['request_id'] ?? uniqid('req_', true));
        $intent = (string) ($data['intent'] ?? 'unknown');
        $data['trace_id'] = $traceId;
        $data['request_id'] = $requestId;
        $sanitizedInput = $this->payloadSanitizer->sanitize($data);

        $this->logger->info(
            'A2A request received',
            TraceEvent::build('dev.a2a.inbound.received', 'a2a_inbound', 'dev-agent', 'started', [
                'target_app' => 'core',
                'intent' => $intent,
                'trace_id' => $traceId,
                'request_id' => $requestId,
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );

        $start = microtime(true);

        try {
            $result = $this->handler->handle($data);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $this->logger->error(
                'A2A handler exception',
                TraceEvent::build('dev.a2a.inbound.exception', 'a2a_inbound', 'dev-agent', 'failed', [
                    'target_app' => 'core',
                    'intent' => $intent,
                    'duration_ms' => $durationMs,
                    'trace_id' => $traceId,
                    'request_id' => $requestId,
                    'error_code' => 'handler_exception',
                    'error' => $e->getMessage(),
                ]),
            );

            return $this->json([
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Internal error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
        $status = (string) ($result['status'] ?? 'unknown');

        $this->logger->info(
            'A2A request completed',
            TraceEvent::build('dev.a2a.inbound.completed', 'a2a_inbound', 'dev-agent', $status, [
                'target_app' => 'core',
                'intent' => $intent,
                'duration_ms' => $durationMs,
                'trace_id' => $traceId,
                'request_id' => $requestId,
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
                'error_code' => 'failed' === $status ? (string) ($result['error'] ?? 'intent_failed') : null,
            ]),
        );

        return $this->json($result);
    }
}
