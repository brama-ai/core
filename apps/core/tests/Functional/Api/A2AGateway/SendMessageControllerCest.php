<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\A2AGateway;

final class SendMessageControllerCest
{
    private function gatewayToken(): string
    {
        return (string) ($_ENV['OPENCLAW_GATEWAY_TOKEN'] ?? $_SERVER['OPENCLAW_GATEWAY_TOKEN'] ?? 'test-openclaw-token');
    }

    public function sendMessageWithoutAuthReturns401(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'tool' => 'hello.greet',
            'input' => ['name' => 'Test'],
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function sendMessageWithInvalidTokenReturns401(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer wrong-token');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'tool' => 'hello.greet',
            'input' => ['name' => 'Test'],
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
    }

    public function sendMessageWithInvalidJsonReturns400(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', '{invalid-json}');

        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Invalid JSON']);
    }

    public function sendMessageWithMissingToolReturns400(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'input' => ['name' => 'Test'],
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'tool is required']);
    }

    public function sendMessageWithUnknownToolReturnsFailed(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'trace_id' => 'trace-provided-001',
            'tool' => 'nonexistent.tool',
            'input' => [],
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'failed', 'reason' => 'unknown_tool']);
        $I->seeResponseContainsJson(['trace_id' => 'trace-provided-001']);
        $I->seeResponseJsonMatchesJsonPath('$.request_id');
    }

    public function sendMessageWithoutTraceIdReturnsGeneratedTraceId(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'tool' => 'nonexistent.tool',
            'input' => [],
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseJsonMatchesJsonPath('$.trace_id');
        $I->seeResponseJsonMatchesJsonPath('$.request_id');
    }

    public function sendMessageWithValidInputStructure(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'tool' => 'test.tool',
            'input' => [
                'query' => 'test query',
                'options' => ['mode' => 'test'],
            ],
            'trace_id' => 'test-trace-123',
            'request_id' => 'test-req-456',
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'trace_id' => 'test-trace-123',
            'request_id' => 'test-req-456',
        ]);

        // Should return failed for unknown tool, but with proper structure
        $I->seeResponseContainsJson(['status' => 'failed']);
        $I->seeResponseJsonMatchesJsonPath('$.reason');
    }

    public function sendMessageResponseIncludesRequiredFields(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a/send-message', json_encode([
            'tool' => 'unknown.tool',
            'input' => [],
        ], JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        // Verify response structure
        $I->seeResponseMatchesJsonType([
            'status' => 'string',
            'reason' => 'string',
            'trace_id' => 'string',
            'request_id' => 'string',
        ]);
    }
}
