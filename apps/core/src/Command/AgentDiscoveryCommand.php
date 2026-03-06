<?php

declare(strict_types=1);

namespace App\Command;

use App\A2AGateway\AgentCardFetcher;
use App\A2AGateway\AgentConventionVerifier;
use App\A2AGateway\AgentDiscoveryService;
use App\AgentRegistry\AgentRegistryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'agent:discovery', description: 'Discover agents via Traefik API and refresh registry')]
final class AgentDiscoveryCommand extends Command
{
    public function __construct(
        private readonly AgentDiscoveryService $discoveryService,
        private readonly AgentCardFetcher $agentCardFetcher,
        private readonly AgentConventionVerifier $conventionVerifier,
        private readonly AgentRegistryInterface $registry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agents = $this->discoveryService->discoverAgents();

        if ([] === $agents) {
            $output->writeln('No agent services found via Traefik API.');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Discovered %d agent service(s). Fetching manifests...', count($agents)));

        $healthy = 0;
        $degraded = 0;
        $errors = 0;

        foreach ($agents as ['hostname' => $hostname, 'port' => $port]) {
            $manifest = $this->agentCardFetcher->fetch($hostname, $port);
            $result = $this->conventionVerifier->verify($manifest);

            // Use manifest name if available, fall back to hostname for error state
            $name = is_string($manifest['name'] ?? null) && '' !== $manifest['name']
                ? (string) $manifest['name']
                : $hostname;

            $this->registry->upsertFromDiscovery($name, $manifest, $result->status, $result->violations);

            $icon = match ($result->status) {
                'healthy' => '✓',
                'degraded' => '⚠',
                default => '✗',
            };
            $output->writeln(sprintf('  [%s] %s (%s)  %s', $result->status, $name, $hostname, $icon));

            if ([] !== $result->violations) {
                foreach ($result->violations as $violation) {
                    $output->writeln(sprintf('      → %s', $violation));
                }
            }

            match ($result->status) {
                'healthy' => ++$healthy,
                'degraded' => ++$degraded,
                default => ++$errors,
            };
        }

        $output->writeln(sprintf('Done. healthy=%d  degraded=%d  error=%d', $healthy, $degraded, $errors));

        return Command::SUCCESS;
    }
}
