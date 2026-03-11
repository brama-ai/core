<?php

declare(strict_types=1);

namespace App\Command;

use App\AgentRegistry\AgentRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:agent-health-poll', description: 'Poll registered agents health endpoints')]
final class AgentHealthPollerCommand extends Command
{
    private const FAILURE_THRESHOLD = 3;
    private const STALE_THRESHOLD = 5;

    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $agents = $this->registry->findAll();
        $polled = 0;

        foreach ($agents as $agent) {
            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'])
                ? json_decode($agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : $agent['manifest'];

            $healthUrl = $manifest['health_url'] ?? null;
            if (!is_string($healthUrl) || '' === $healthUrl) {
                continue;
            }

            $name = (string) $agent['name'];
            $isHealthy = $this->checkHealth($healthUrl);

            if ($isHealthy) {
                $currentStatus = (string) ($agent['health_status'] ?? 'unknown');
                if ('unavailable' === $currentStatus) {
                    $violations = json_decode(
                        is_string($agent['violations'] ?? null) ? (string) $agent['violations'] : '[]',
                        true,
                    );
                    $restoredStatus = (is_array($violations) && [] !== $violations) ? 'degraded' : 'healthy';
                    $this->registry->resetHealthCheckFailures($name, $restoredStatus);
                    $this->logger->info('Agent recovered', ['agent' => $name, 'status' => $restoredStatus]);
                    $output->writeln(sprintf('[%s] recovered → %s', $name, $restoredStatus));
                }
            } else {
                $failures = $this->registry->recordHealthCheckFailure($name);
                $this->logger->info('Agent health check failed', ['agent' => $name, 'consecutive_failures' => $failures]);
                $output->writeln(sprintf('[%s] health check failed (consecutive: %d)', $name, $failures));

                if ($failures >= self::FAILURE_THRESHOLD) {
                    $this->registry->updateHealthStatus($name, 'unavailable');
                    $this->logger->warning('Agent marked unavailable', ['agent' => $name, 'failures' => $failures]);
                    $output->writeln(sprintf('[%s] → unavailable (threshold reached)', $name));
                }
            }

            ++$polled;
        }

        $output->writeln(sprintf('Polled %d agent(s).', $polled));

        $cleaned = $this->registry->deleteStaleMarketplaceAgents(self::STALE_THRESHOLD);
        if ($cleaned > 0) {
            $this->logger->info('Stale marketplace agents cleaned up', ['count' => $cleaned]);
            $output->writeln(sprintf('Cleaned up %d stale marketplace agent(s).', $cleaned));
        }

        return Command::SUCCESS;
    }

    private function checkHealth(string $url): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header;
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            return false;
        }

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $header, $m)) {
                $code = (int) $m[1];

                return $code >= 200 && $code < 300;
            }
        }

        return true;
    }
}
