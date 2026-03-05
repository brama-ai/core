<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\OpenClaw;

final class DiscoveryControllerCest
{
    private function gatewayToken(): string
    {
        return (string) ($_ENV['OPENCLAW_GATEWAY_TOKEN'] ?? $_SERVER['OPENCLAW_GATEWAY_TOKEN'] ?? 'test-openclaw-token');
    }

    public function discoveryWithoutAuthReturns401(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/agents/discovery');

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function discoveryWithInvalidTokenReturns401(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer wrong-token');
        $I->sendGet('/api/v1/agents/discovery');

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
    }

    public function discoveryWithValidTokenReturnsTools(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->sendGet('/api/v1/agents/discovery');

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"platform_version"');
        $I->seeResponseContains('"tools"');
        $I->seeResponseContains('"generated_at"');
    }
}
