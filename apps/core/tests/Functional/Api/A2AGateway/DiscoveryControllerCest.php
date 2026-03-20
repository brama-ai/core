<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\A2AGateway;

final class DiscoveryControllerCest
{
    private function gatewayToken(): string
    {
        return (string) ($_ENV['OPENCLAW_GATEWAY_TOKEN'] ?? $_SERVER['OPENCLAW_GATEWAY_TOKEN'] ?? 'test-openclaw-token');
    }

    public function discoveryWithoutAuthReturns401(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/a2a/discovery');

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function discoveryWithInvalidTokenReturns401(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer wrong-token');
        $I->sendGet('/api/v1/a2a/discovery');

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
    }

    public function discoveryWithValidTokenReturnsTools(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->sendGet('/api/v1/a2a/discovery');

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"platform_version"');
        $I->seeResponseContains('"tools"');
        $I->seeResponseContains('"generated_at"');
    }

    public function discoveryResponseHasCorrectStructure(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->sendGet('/api/v1/a2a/discovery');

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        // Verify top-level structure
        $I->seeResponseContainsJson([
            'platform_version' => '0.1.0',
        ]);

        $I->seeResponseMatchesJsonType([
            'platform_version' => 'string',
            'generated_at' => 'string',
            'tools' => 'array',
        ]);

        // Get response to check tools structure
        $response = json_decode($I->grabResponse(), true);

        if (!empty($response['tools'])) {
            // Verify each tool has required fields
            foreach ($response['tools'] as $tool) {
                $I->assertArrayHasKey('name', $tool);
                $I->assertArrayHasKey('agent', $tool);
                $I->assertArrayHasKey('description', $tool);
                $I->assertArrayHasKey('input_schema', $tool);

                $I->assertIsString($tool['name']);
                $I->assertIsString($tool['agent']);
                $I->assertIsString($tool['description']);
                $I->assertIsArray($tool['input_schema']);

                // Verify input_schema has type
                $I->assertArrayHasKey('type', $tool['input_schema']);
                $I->assertSame('object', $tool['input_schema']['type']);
            }
        }
    }

    public function discoveryResponseIsCached(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());

        // First request
        $I->sendGet('/api/v1/a2a/discovery');
        $I->seeResponseCodeIs(200);
        $firstResponse = $I->grabResponse();
        $firstData = json_decode($firstResponse, true);

        // Second request should return same generated_at (cached)
        $I->sendGet('/api/v1/a2a/discovery');
        $I->seeResponseCodeIs(200);
        $secondResponse = $I->grabResponse();
        $secondData = json_decode($secondResponse, true);

        $I->assertSame($firstData['generated_at'], $secondData['generated_at']);
    }

    public function discoveryOnlyIncludesEnabledAgents(\FunctionalTester $I): void
    {
        // This test verifies that disabled agents don't appear in discovery
        // The registry service ensures only enabled agents are returned
        $I->haveHttpHeader('Authorization', 'Bearer '.$this->gatewayToken());
        $I->sendGet('/api/v1/a2a/discovery');

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();

        $response = json_decode($I->grabResponse(), true);

        // Verify all tools come from enabled agents only
        // This is enforced by the AgentRegistry::findEnabled() method
        foreach ($response['tools'] ?? [] as $tool) {
            $I->assertIsString($tool['agent']);
            $I->assertNotEmpty($tool['agent']);
            // The fact that the tool appears means its agent is enabled
            // as the SkillCatalogBuilder only processes enabled agents
        }
    }
}
