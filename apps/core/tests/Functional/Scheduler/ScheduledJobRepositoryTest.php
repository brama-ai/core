<?php

declare(strict_types=1);

namespace App\Tests\Functional\Scheduler;

use App\Scheduler\ScheduledJobRepository;
use Doctrine\DBAL\Connection;

final class ScheduledJobRepositoryTest
{
    private function getRepository(\FunctionalTester $I): ScheduledJobRepository
    {
        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');

        return new ScheduledJobRepository($connection);
    }

    private function cleanup(\FunctionalTester $I, string $prefix): void
    {
        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');
        $connection->executeStatement(
            'DELETE FROM scheduled_jobs WHERE agent_name LIKE :prefix',
            ['prefix' => $prefix.'%'],
        );
    }

    public function registerJobInsertsNewRow(\FunctionalTester $I): void
    {
        $agentName = 'test-repo-agent-'.bin2hex(random_bytes(4));
        $this->cleanup($I, 'test-repo-agent-');

        $repo = $this->getRepository($I);
        $repo->registerJob(
            $agentName,
            'test-job',
            'test.skill',
            ['key' => 'value'],
            '* * * * *',
            (new \DateTimeImmutable('+1 minute'))->format('Y-m-d H:i:sP'),
            3,
            60,
            'UTC',
        );

        $jobs = $repo->findAll();
        $found = array_filter($jobs, static fn (array $j): bool => $j['agent_name'] === $agentName);

        $I->assertCount(1, $found);
        $job = array_values($found)[0];
        $I->assertSame('test-job', $job['job_name']);
        $I->assertSame('test.skill', $job['skill_id']);
        $I->assertSame('* * * * *', $job['cron_expression']);
        $I->assertTrue((bool) $job['enabled']);

        $this->cleanup($I, 'test-repo-agent-');
    }

    public function registerJobUpsertUpdatesExistingRow(\FunctionalTester $I): void
    {
        $agentName = 'test-repo-upsert-'.bin2hex(random_bytes(4));

        $repo = $this->getRepository($I);
        $repo->registerJob(
            $agentName,
            'upsert-job',
            'skill.v1',
            [],
            '0 * * * *',
            (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP'),
            3,
            60,
            'UTC',
        );

        $repo->registerJob(
            $agentName,
            'upsert-job',
            'skill.v2',
            [],
            '0 * * * *',
            (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP'),
            3,
            60,
            'UTC',
        );

        $jobs = $repo->findAll();
        $found = array_filter($jobs, static fn (array $j): bool => $j['agent_name'] === $agentName);

        $I->assertCount(1, $found);
        $job = array_values($found)[0];
        $I->assertSame('skill.v2', $job['skill_id']);

        $repo->deleteByAgent($agentName);
    }

    public function deleteByAgentRemovesAllJobs(\FunctionalTester $I): void
    {
        $agentName = 'test-repo-delete-'.bin2hex(random_bytes(4));

        $repo = $this->getRepository($I);
        $repo->registerJob(
            $agentName,
            'job-1',
            'test.skill',
            [],
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            3,
            60,
            'UTC',
        );

        $repo->registerJob(
            $agentName,
            'job-2',
            'test.skill',
            [],
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            3,
            60,
            'UTC',
        );

        $deleted = $repo->deleteByAgent($agentName);

        $I->assertSame(2, $deleted);

        $jobs = $repo->findAll();
        $found = array_filter($jobs, static fn (array $j): bool => $j['agent_name'] === $agentName);
        $I->assertCount(0, $found);
    }

    public function updateAfterRunUpdatesJobState(\FunctionalTester $I): void
    {
        $agentName = 'test-repo-update-'.bin2hex(random_bytes(4));

        $repo = $this->getRepository($I);
        $repo->registerJob(
            $agentName,
            'update-job',
            'test.skill',
            [],
            '* * * * *',
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            3,
            60,
            'UTC',
        );

        $jobs = $repo->findAll();
        $found = array_values(array_filter($jobs, static fn (array $j): bool => $j['agent_name'] === $agentName));
        $I->assertCount(1, $found);

        $id = (string) $found[0]['id'];
        $nextRun = (new \DateTimeImmutable('+1 minute'))->format('Y-m-d H:i:sP');

        $repo->updateAfterRun($id, 'completed', $nextRun);

        $updated = $repo->findById($id);
        $I->assertNotNull($updated);
        $I->assertSame('completed', $updated['last_status']);
        $I->assertSame(0, (int) $updated['retry_count']);
        $I->assertNotNull($updated['last_run_at']);

        $repo->deleteByAgent($agentName);
    }
}
