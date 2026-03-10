<?php

declare(strict_types=1);

namespace App\Tests\Functional\Scheduler;

use Doctrine\DBAL\Connection;

final class AgentInstallSchedulerTest
{
    private const INTERNAL_TOKEN = 'test-internal-token';

    private function login(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestWithScheduledJobs(string $name): array
    {
        return [
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Scheduler test agent',
            'permissions' => ['admin'],
            'commands' => ['/test'],
            'events' => ['message.created'],
            'a2a_endpoint' => sprintf('http://%s/a2a', $name),
            'scheduled_jobs' => [
                [
                    'job_name' => 'test_crawl',
                    'skill_id' => sprintf('%s.trigger_crawl', $name),
                    'cron' => '0 */4 * * *',
                    'payload' => [],
                    'max_retries' => 3,
                    'retry_delay_seconds' => 120,
                ],
            ],
        ];
    }

    public function installingAgentWithScheduledJobsCreatesRows(\FunctionalTester $I): void
    {
        $name = 'sched-install-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost(
            '/api/v1/internal/agents/register',
            json_encode($this->manifestWithScheduledJobs($name), JSON_THROW_ON_ERROR),
        );
        $I->seeResponseCodeIs(200);

        $this->login($I);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'installed', 'name' => $name]);

        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');
        $jobs = $connection->fetchAllAssociative(
            'SELECT * FROM scheduled_jobs WHERE agent_name = :name',
            ['name' => $name],
        );

        $I->assertCount(1, $jobs);
        $I->assertSame('test_crawl', $jobs[0]['job_name']);
        $I->assertSame(sprintf('%s.trigger_crawl', $name), $jobs[0]['skill_id']);
        $I->assertSame('0 */4 * * *', $jobs[0]['cron_expression']);

        // Cleanup
        $connection->executeStatement('DELETE FROM scheduled_jobs WHERE agent_name = :name', ['name' => $name]);
    }

    public function uninstallingAgentRemovesScheduledJobs(\FunctionalTester $I): void
    {
        $name = 'sched-uninstall-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost(
            '/api/v1/internal/agents/register',
            json_encode($this->manifestWithScheduledJobs($name), JSON_THROW_ON_ERROR),
        );
        $I->seeResponseCodeIs(200);

        $this->login($I);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);

        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');
        $jobsBefore = $connection->fetchAllAssociative(
            'SELECT * FROM scheduled_jobs WHERE agent_name = :name',
            ['name' => $name],
        );
        $I->assertCount(1, $jobsBefore);

        // Disable first (required before delete)
        $I->sendPost(sprintf('/api/v1/internal/agents/%s/disable', $name));
        $I->seeResponseCodeIs(200);

        $I->sendDelete(sprintf('/api/v1/internal/agents/%s', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'uninstalled', 'name' => $name]);

        $jobsAfter = $connection->fetchAllAssociative(
            'SELECT * FROM scheduled_jobs WHERE agent_name = :name',
            ['name' => $name],
        );
        $I->assertCount(0, $jobsAfter);
    }
}
