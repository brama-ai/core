<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class A2AControllerCest
{
    public function a2aWithHelloGreetReturnsGreeting(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'hello.greet',
            'payload' => ['name' => 'TestUser'],
            'request_id' => 'test-req-001',
            'trace_id' => 'test-trace-001',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);
        $I->seeResponseContains('"greeting"');
        $I->seeResponseContains('TestUser');
    }

    public function a2aWithoutIntentReturns422(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'payload' => ['name' => 'Test'],
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        $I->seeResponseContains('intent is required');
    }

    public function a2aWithInvalidJsonReturns422(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', '{not-valid-json}');

        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
    }

    public function a2aWithUnknownIntentReturnsFailed(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'unknown.action',
            'request_id' => 'test-req-002',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'failed']);
        $I->seeResponseContains('Unknown intent');
    }

    public function a2aGreetWithDefaultNameReturnsWorld(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'hello.greet',
            'payload' => [],
            'request_id' => 'test-req-003',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);
        $I->seeResponseContains('World');
    }
}
