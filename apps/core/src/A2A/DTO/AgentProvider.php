<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * Provider information for an A2A agent.
 *
 * Identifies the organization that operates the agent and provides a link
 * to the organization's website or repository.
 */
final readonly class AgentProvider
{
    public function __construct(
        /** @var string Organization name (e.g. "AI Community Platform") */
        public string $organization = '',
        /** @var string Organization URL (e.g. GitHub repository or company website) */
        public string $url = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            organization: (string) ($data['organization'] ?? ''),
            url: (string) ($data['url'] ?? ''),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'organization' => $this->organization,
            'url' => $this->url,
        ];
    }
}
