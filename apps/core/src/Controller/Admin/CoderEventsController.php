<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\CoderAgent\CoderTaskLogRepositoryInterface;
use App\CoderAgent\CoderTaskRepositoryInterface;
use App\CoderAgent\CoderWorkerRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CoderEventsController extends AbstractController
{
    public function __construct(
        private readonly CoderTaskRepositoryInterface $tasks,
        private readonly CoderTaskLogRepositoryInterface $logs,
        private readonly CoderWorkerRepositoryInterface $workers,
    ) {
    }

    #[Route('/admin/coder/events', name: 'admin_coder_events', methods: ['GET'])]
    public function __invoke(Request $request): StreamedResponse
    {
        $cursor = $request->query->get('cursor');
        $since = \is_string($cursor) && '' !== $cursor
            ? new \DateTimeImmutable($cursor)
            : new \DateTimeImmutable('-30 seconds');

        $response = new StreamedResponse(function () use ($since): void {
            @set_time_limit(20);

            for ($i = 0; $i < 10; ++$i) {
                $taskUpdates = $this->tasks->findUpdatedSince($since);
                foreach ($taskUpdates as $task) {
                    echo "event: task.status_changed\n";
                    echo 'data: '.json_encode([
                        'task_id' => $task['id'],
                        'status' => $task['status'],
                        'current_stage' => $task['current_stage'],
                        'updated_at' => $task['updated_at'],
                    ], JSON_THROW_ON_ERROR)."\n\n";
                }

                $logUpdates = $this->logs->findCreatedSince($since);
                foreach ($logUpdates as $log) {
                    echo "event: task.log\n";
                    echo 'data: '.json_encode([
                        'id' => $log['id'],
                        'task_id' => $log['task_id'],
                        'stage' => $log['stage'],
                        'level' => $log['level'],
                        'message' => $log['message'],
                        'created_at' => $log['created_at'],
                    ], JSON_THROW_ON_ERROR)."\n\n";
                }

                $workerUpdates = $this->workers->findUpdatedSince($since);
                foreach ($workerUpdates as $worker) {
                    echo "event: worker.heartbeat\n";
                    echo 'data: '.json_encode([
                        'worker_id' => $worker['id'],
                        'status' => $worker['status'],
                        'current_task_id' => $worker['current_task_id'],
                        'last_heartbeat_at' => $worker['last_heartbeat_at'],
                    ], JSON_THROW_ON_ERROR)."\n\n";
                }

                echo ": heartbeat\n\n";
                @ob_flush();
                flush();
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
