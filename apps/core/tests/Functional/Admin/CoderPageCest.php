<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\CoderAgent\CoderTaskRepositoryInterface;

final class CoderPageCest
{
    private function login(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function coderPageRedirectsUnauthenticatedUser(\FunctionalTester $I): void
    {
        $I->sendGet('/admin/coder');
        $I->seeResponseContains('_username');
    }

    public function coderPageRendersAfterLogin(\FunctionalTester $I): void
    {
        $this->login($I);

        $I->sendGet('/admin/coder');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Coder');
        $I->seeResponseContains('Задачі builder-agent');
    }

    public function createTaskFlowCreatesTaskAndRedirectsToDetail(\FunctionalTester $I): void
    {
        $this->login($I);

        $title = 'Admin created task '.bin2hex(random_bytes(4));
        $I->sendPost('/admin/coder/create', [
            'title' => $title,
            'description' => "## Goal\n\nCreate task from admin.",
            'template' => 'feature',
            'priority' => 5,
            'queue_now' => '1',
        ]);
        $I->seeResponseCodeIs(200);

        /** @var CoderTaskRepositoryInterface $repo */
        $repo = $I->grabService(CoderTaskRepositoryInterface::class);
        $tasks = $repo->findAll();
        $created = array_values(array_filter($tasks, static fn (array $task): bool => $task['title'] === $title));

        $I->assertCount(1, $created);
        $I->assertSame('queued', $created[0]['status']);

        $I->sendGet('/admin/coder/'.$created[0]['id']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains($title);
        $I->seeResponseContains('Stage timeline');
    }
}
