<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service for monitoring workflow execution and performance.
 * Provides basic monitoring capabilities even when Inspector is not available.
 */
final class WorkflowMonitoringService
{
    private array $metrics = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function startWorkflow(string $workflowClass, array $context = []): void
    {
        $workflowId = uniqid($workflowClass . '_', true);
        $this->metrics[$workflowId] = [
            'class' => $workflowClass,
            'start_time' => microtime(true),
            'context' => $context,
            'nodes' => [],
        ];

        $this->logger->info('Workflow started', [
            'workflow_id' => $workflowId,
            'workflow_class' => $workflowClass,
            'context' => $context,
        ]);
    }

    public function endWorkflow(string $workflowClass, array $finalState = []): void
    {
        $workflowId = $this->findWorkflowId($workflowClass);
        if ($workflowId === null) {
            return;
        }

        $metrics = $this->metrics[$workflowId];
        $duration = microtime(true) - $metrics['start_time'];

        $this->logger->info('Workflow completed', [
            'workflow_id' => $workflowId,
            'workflow_class' => $workflowClass,
            'duration_ms' => round($duration * 1000, 2),
            'nodes_executed' => count($metrics['nodes']),
            'final_state' => $finalState,
        ]);

        unset($this->metrics[$workflowId]);
    }

    public function startNode(string $workflowClass, string $nodeClass, array $stateBefore = []): void
    {
        $workflowId = $this->findWorkflowId($workflowClass);
        if ($workflowId === null) {
            return;
        }

        $nodeId = uniqid($nodeClass . '_', true);
        $this->metrics[$workflowId]['nodes'][$nodeId] = [
            'class' => $nodeClass,
            'start_time' => microtime(true),
            'state_before' => $stateBefore,
        ];

        $this->logger->debug('Node started', [
            'workflow_id' => $workflowId,
            'node_id' => $nodeId,
            'node_class' => $nodeClass,
        ]);
    }

    public function endNode(string $workflowClass, string $nodeClass, array $stateAfter = []): void
    {
        $workflowId = $this->findWorkflowId($workflowClass);
        if ($workflowId === null) {
            return;
        }

        $nodeId = $this->findNodeId($workflowId, $nodeClass);
        if ($nodeId === null) {
            return;
        }

        $nodeMetrics = $this->metrics[$workflowId]['nodes'][$nodeId];
        $duration = microtime(true) - $nodeMetrics['start_time'];

        $this->logger->debug('Node completed', [
            'workflow_id' => $workflowId,
            'node_id' => $nodeId,
            'node_class' => $nodeClass,
            'duration_ms' => round($duration * 1000, 2),
            'state_after' => $stateAfter,
        ]);

        $this->metrics[$workflowId]['nodes'][$nodeId]['duration'] = $duration;
        $this->metrics[$workflowId]['nodes'][$nodeId]['state_after'] = $stateAfter;
    }

    public function recordError(string $workflowClass, \Throwable $exception): void
    {
        $workflowId = $this->findWorkflowId($workflowClass);

        $this->logger->error('Workflow error', [
            'workflow_id' => $workflowId,
            'workflow_class' => $workflowClass,
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
            'error_trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get current workflow metrics for debugging/monitoring.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    private function findWorkflowId(string $workflowClass): ?string
    {
        foreach ($this->metrics as $id => $metrics) {
            if ($metrics['class'] === $workflowClass) {
                return $id;
            }
        }

        return null;
    }

    private function findNodeId(string $workflowId, string $nodeClass): ?string
    {
        if (!isset($this->metrics[$workflowId]['nodes'])) {
            return null;
        }

        foreach ($this->metrics[$workflowId]['nodes'] as $id => $nodeMetrics) {
            if ($nodeMetrics['class'] === $nodeClass) {
                return $id;
            }
        }

        return null;
    }
}