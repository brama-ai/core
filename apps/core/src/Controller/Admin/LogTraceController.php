<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Logging\LogIndexManager;
use App\Logging\TraceSequenceProjector;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LogTraceController extends AbstractController
{
    public function __construct(
        private readonly LogIndexManager $indexManager,
        private readonly TraceSequenceProjector $sequenceProjector,
    ) {
    }

    #[Route('/admin/logs/trace/{traceId}', name: 'admin_log_trace')]
    public function __invoke(#[CurrentUser] User $user, string $traceId): Response
    {
        $searchBody = [
            'query' => [
                'term' => ['trace_id' => $traceId],
            ],
            'sort' => [['@timestamp' => 'asc']],
            'size' => 500,
        ];

        $result = $this->indexManager->search($searchBody);

        $hits = [];
        $spans = [];
        $traceStartTime = null;
        $traceEndTime = null;

        if (null !== $result) {
            /** @var list<array{_source: array<string, mixed>}> $rawHits */
            $rawHits = $result['hits']['hits'] ?? [];
            foreach ($rawHits as $hit) {
                $source = $hit['_source'];
                $hits[] = $source;

                $reqId = $source['request_id'] ?? 'unknown_req';
                $ts = isset($source['@timestamp']) ? new \DateTimeImmutable($source['@timestamp']) : new \DateTimeImmutable();

                if (null === $traceStartTime || $ts < $traceStartTime) {
                    $traceStartTime = $ts;
                }
                if (null === $traceEndTime || $ts > $traceEndTime) {
                    $traceEndTime = $ts;
                }

                if (!isset($spans[$reqId])) {
                    $spans[$reqId] = [
                        'request_id' => $reqId,
                        'app_name' => $source['app_name'] ?? 'unknown',
                        'start_time' => $ts,
                        'end_time' => $ts,
                        'logs' => [],
                    ];
                }

                if ($ts < $spans[$reqId]['start_time']) {
                    $spans[$reqId]['start_time'] = $ts;
                }
                if ($ts > $spans[$reqId]['end_time']) {
                    $spans[$reqId]['end_time'] = $ts;
                }
                $spans[$reqId]['logs'][] = $source;
            }
        }
        $sequenceProjection = $this->sequenceProjector->project($hits);

        $totalDurationMs = 0;
        if (null !== $traceStartTime && null !== $traceEndTime) {
            $totalDurationMs = ($traceEndTime->format('U.u') - $traceStartTime->format('U.u')) * 1000;
        }

        $processedSpans = [];
        foreach ($spans as $reqId => $spanData) {
            $durationMs = ($spanData['end_time']->format('U.u') - $spanData['start_time']->format('U.u')) * 1000;
            // minimal visible duration for UI
            if ($durationMs < 1) {
                $durationMs = 1;
            }

            $offsetMs = 0;
            if (null !== $traceStartTime) {
                $offsetMs = ($spanData['start_time']->format('U.u') - $traceStartTime->format('U.u')) * 1000;
            }

            $processedSpans[] = [
                'request_id' => $reqId,
                'app_name' => $spanData['app_name'],
                'duration_ms' => round($durationMs, 2),
                'offset_ms' => round($offsetMs, 2),
                'percent_start' => $totalDurationMs > 0 ? ($offsetMs / $totalDurationMs) * 100 : 0,
                'percent_width' => $totalDurationMs > 0 ? ($durationMs / $totalDurationMs) * 100 : 100,
                'logs' => $spanData['logs'],
            ];
        }

        // Sort spans by start time
        usort($processedSpans, fn ($a, $b) => $a['offset_ms'] <=> $b['offset_ms']);

        return $this->render('admin/log_trace.html.twig', [
            'username' => $user->getUserIdentifier(),
            'trace_id' => $traceId,
            'hits' => $hits,
            'spans' => $processedSpans,
            'sequence_events' => $sequenceProjection['events'],
            'participants' => $sequenceProjection['participants'],
            'sequence_call_events' => $sequenceProjection['call_events'],
            'call_participants' => $sequenceProjection['call_participants'],
            'total_duration_ms' => round((float) $totalDurationMs, 2),
        ]);
    }
}
