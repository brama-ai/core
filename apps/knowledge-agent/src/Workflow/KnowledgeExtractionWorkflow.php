<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Service\WorkflowMonitoringService;
use App\Workflow\Nodes\AnalyzeMessages;
use App\Workflow\Nodes\EnrichMetadata;
use App\Workflow\Nodes\ExtractKnowledge;

use NeuronAI\Observability\InspectorObserver;
use NeuronAI\Workflow\NodeInterface;
use NeuronAI\Workflow\Workflow;
use NeuronAI\Workflow\WorkflowState;

final class KnowledgeExtractionWorkflow extends Workflow
{
    private WorkflowState $workflowState;

    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed>       $chunkMeta
     */
    public function __construct(
        private readonly KnowledgeExtractionAgent $agent,
        array $messages,
        array $chunkMeta = [],
        private readonly ?InspectorObserver $inspector = null,
        private readonly ?WorkflowMonitoringService $monitoring = null,
    ) {
        $this->workflowState = new WorkflowState([
            'messages' => $messages,
            'chunk_meta' => $chunkMeta,
            'is_valuable' => false,
            'knowledge' => null,
        ]);

        // Add Inspector observer if available
        if ($this->inspector !== null) {
            $this->observe($this->inspector);
        }

        parent::__construct(null, null, $this->workflowState);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getKnowledge(): ?array
    {
        /** @var array<string, mixed>|null $knowledge */
        $knowledge = $this->workflowState->get('knowledge');

        return $knowledge;
    }

    /**
     * Override run method to add monitoring hooks.
     */
    public function run(): \Generator
    {
        $this->monitoring?->startWorkflow(self::class, [
            'message_count' => count($this->workflowState->get('messages', [])),
            'chunk_meta' => $this->workflowState->get('chunk_meta', []),
        ]);

        try {
            yield from parent::run();
            
            $this->monitoring?->endWorkflow(self::class, [
                'is_valuable' => $this->workflowState->get('is_valuable', false),
                'has_knowledge' => $this->workflowState->get('knowledge') !== null,
            ]);
        } catch (\Throwable $e) {
            $this->monitoring?->recordError(self::class, $e);
            throw $e;
        }
    }

    /**
     * @return list<NodeInterface>
     */
    protected function nodes(): array
    {
        return [
            new AnalyzeMessages($this->agent),
            new ExtractKnowledge($this->agent),
            new EnrichMetadata(),
        ];
    }
}
