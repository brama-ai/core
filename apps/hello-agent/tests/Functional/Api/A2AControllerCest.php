<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class A2AControllerCest
{
    public function a2aWithHelloGreetReturnsGreeting(\FunctionalTester $I): void
    {
        $requestId = 'test-req-001';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'hello.greet',
            'payload' => ['name' => 'TestUser'],
            'request_id' => $requestId,
            'trace_id' => 'test-trace-001',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);

        $response = json_decode($I->grabResponse(), true);
        \PHPUnit\Framework\Assert::assertIsArray($response);
        \PHPUnit\Framework\Assert::assertSame($requestId, $response['request_id'] ?? null);
        \PHPUnit\Framework\Assert::assertIsString($response['result']['greeting'] ?? null);
        \PHPUnit\Framework\Assert::assertNotSame('', trim($response['result']['greeting']));
        \PHPUnit\Framework\Assert::assertStringContainsString('TestUser', $response['result']['greeting']);
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
        $requestId = 'test-req-003';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'hello.greet',
            'payload' => [],
            'request_id' => $requestId,
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);

        $response = json_decode($I->grabResponse(), true);
        \PHPUnit\Framework\Assert::assertIsArray($response);
        \PHPUnit\Framework\Assert::assertSame($requestId, $response['request_id'] ?? null);
        \PHPUnit\Framework\Assert::assertIsString($response['result']['greeting'] ?? null);
        \PHPUnit\Framework\Assert::assertNotSame('', trim($response['result']['greeting']));
        \PHPUnit\Framework\Assert::assertStringContainsString('World', $response['result']['greeting']);
    }

    public function a2aGreetMeWithUsernameReturnsGreeting(\FunctionalTester $I): void
    {
        $requestId = 'test-req-004';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'hello.greet_me',
            'payload' => ['username' => 'testuser'],
            'request_id' => $requestId,
            'trace_id' => 'test-trace-004',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);

        $response = json_decode($I->grabResponse(), true);
        \PHPUnit\Framework\Assert::assertIsArray($response);
        \PHPUnit\Framework\Assert::assertSame($requestId, $response['request_id'] ?? null);
        \PHPUnit\Framework\Assert::assertIsString($response['result']['greeting'] ?? null);
        \PHPUnit\Framework\Assert::assertNotSame('', trim($response['result']['greeting']));
        \PHPUnit\Framework\Assert::assertStringContainsString('@testuser', $response['result']['greeting']);
    }

    public function a2aGreetMeWithoutUsernameReturnsWorld(\FunctionalTester $I): void
    {
        $requestId = 'test-req-005';

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'hello.greet_me',
            'payload' => [],
            'request_id' => $requestId,
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);

        $response = json_decode($I->grabResponse(), true);
        \PHPUnit\Framework\Assert::assertIsArray($response);
        \PHPUnit\Framework\Assert::assertSame($requestId, $response['request_id'] ?? null);
        \PHPUnit\Framework\Assert::assertIsString($response['result']['greeting'] ?? null);
        \PHPUnit\Framework\Assert::assertNotSame('', trim($response['result']['greeting']));
        \PHPUnit\Framework\Assert::assertStringContainsString('World', $response['result']['greeting']);
    }
}
