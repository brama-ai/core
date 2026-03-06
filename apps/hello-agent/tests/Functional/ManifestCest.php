<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ManifestCest
{
    public function testManifestEndpointReturnsValidJson(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/manifest');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'name' => 'hello-agent',
            'version' => '1.0.0',
        ]);
        $I->seeResponseContainsJson(['url' => 'http://hello-agent/api/v1/a2a']);
        $response = json_decode($I->grabResponse(), true);
        \PHPUnit\Framework\Assert::assertIsArray($response['skills']);
        \PHPUnit\Framework\Assert::assertSame('hello.greet', $response['skills'][0]['id']);
    }

    public function testManifestContainsHealthUrl(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/manifest');
        $I->seeResponseContainsJson(['health_url' => 'http://hello-agent/health']);
    }
}
