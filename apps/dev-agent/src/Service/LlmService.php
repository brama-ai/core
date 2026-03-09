<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

final class LlmService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are a task specification assistant for a software development pipeline.
        The user will describe a feature or bug. Your job is to:
        1. Ask clarifying questions if the request is ambiguous
        2. Produce a structured task specification in markdown format
        3. Include: Requirements, Affected apps/files, DB changes needed, API surface changes, Test scenarios

        The project is a PHP/Symfony AI Community Platform with multiple agents.
        Stack: PHP 8.5, Symfony 7, Doctrine DBAL, PostgreSQL, Docker, Traefik.
        Agents: core, knowledge-agent, news-maker-agent, dev-reporter-agent, hello-agent.

        Reply in the same language the user uses.
        PROMPT;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $liteLlmBaseUrl,
        private readonly string $liteLlmApiKey,
        private readonly string $llmModel,
    ) {
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     */
    public function chat(array $messages, int $maxTokens = 4096): string
    {
        $allMessages = array_merge(
            [['role' => 'system', 'content' => self::SYSTEM_PROMPT]],
            $messages,
        );

        $payload = json_encode([
            'model' => $this->llmModel,
            'messages' => $allMessages,
            'max_tokens' => $maxTokens,
        ], \JSON_THROW_ON_ERROR);

        $url = rtrim($this->liteLlmBaseUrl, '/').'/v1/chat/completions';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$this->liteLlmApiKey}",
                ]),
                'content' => $payload,
                'timeout' => 120,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            $this->logger->error('LLM request failed: no response');
            throw new \RuntimeException('LLM request failed');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response, true);

        if (!isset($decoded['choices'][0]['message']['content'])) {
            $this->logger->error('LLM response missing content', ['response' => substr($response, 0, 500)]);
            throw new \RuntimeException('LLM response missing content');
        }

        return (string) $decoded['choices'][0]['message']['content'];
    }
}
