<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Controller\Admin\KnowledgeAdminController;
use App\Llm\TracingHttpClient;
use App\Logging\TraceContext;
use App\Repository\SettingsRepository;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAILike;

class KnowledgeExtractionAgent extends Agent
{
    public function __construct(
        private readonly string $litellmBaseUrl,
        private readonly string $litellmApiKey,
        private readonly string $model = 'minimax/minimax-m2.5',
        private readonly string $baseInstructions = '',
        private readonly ?SettingsRepository $settingsRepository = null,
        private readonly ?TraceContext $traceContext = null,
    ) {
    }

    protected function provider(): AIProviderInterface
    {
        $httpClient = new GuzzleHttpClient();
        if (null !== $this->traceContext) {
            $httpClient = new TracingHttpClient($httpClient, $this->traceContext);
        }

        return new OpenAILike(
            $this->litellmBaseUrl.'/v1',
            $this->litellmApiKey,
            $this->model,
            [],
            false,
            $httpClient,
        );
    }

    public function instructions(): string
    {
        $storedInstructions = $this->settingsRepository?->get('base_instructions', '') ?? '';
        $base = '' !== $storedInstructions
            ? $storedInstructions
            : ($this->baseInstructions ?: 'You are a knowledge extraction assistant for a Ukrainian-speaking tech community. Your role is to analyze Telegram message batches and extract valuable, structured knowledge.');

        $security = KnowledgeAdminController::SECURITY_INSTRUCTIONS;

        return (string) new SystemPrompt(
            background: [$base, $security],
            steps: [
                'Read the provided message batch carefully.',
                'Determine if the messages contain extractable, reusable knowledge (tutorials, decisions, best practices, useful links, technical solutions).',
                'Extract the knowledge in structured form with title, body, tags, category, and tree path.',
                'Write extracted content in Ukrainian language.',
            ],
            output: [
                'Title should be concise (5-10 words) and descriptive.',
                'Body should be clear Markdown with proper headings if needed.',
                'Tags should be lowercase, space-separated keywords.',
                'Category should be one of: Technology, Business, Community, Events, Resources, Other.',
                'TreePath uses forward slashes as separators (e.g. Technology/PHP/Symfony).',
            ],
        );
    }
}
