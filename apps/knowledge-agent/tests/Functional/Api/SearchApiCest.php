<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class SearchApiCest
{
    public function testSearchRequiresQuery(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search');
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Query parameter q is required']);
    }

    public function testSearchWithEmptyQuery(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=');
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Query parameter q is required']);
    }

    public function testKeywordSearchMode(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=symfony&mode=keyword');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'symfony',
            'mode' => 'keyword',
        ]);
        $I->seeResponseJsonMatchesJsonPath('$.total');
        $I->seeResponseJsonMatchesJsonPath('$.results');
    }

    public function testVectorSearchMode(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=cache configuration&mode=vector');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'cache configuration',
            'mode' => 'vector',
        ]);
        $I->seeResponseJsonMatchesJsonPath('$.total');
        $I->seeResponseJsonMatchesJsonPath('$.results');
    }

    public function testHybridSearchMode(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=docker deployment&mode=hybrid');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'docker deployment',
            'mode' => 'hybrid',
        ]);
        $I->seeResponseJsonMatchesJsonPath('$.total');
        $I->seeResponseJsonMatchesJsonPath('$.results');
    }

    public function testDefaultSearchModeIsHybrid(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=test');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'test',
            'mode' => 'hybrid',
        ]);
    }

    public function testSearchWithCustomSize(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=test&size=5');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseJsonMatchesJsonPath('$.total');
        $I->seeResponseJsonMatchesJsonPath('$.results');
    }

    public function testSearchSizeLimit(\FunctionalTester $I): void
    {
        // Test maximum size limit (50)
        $I->sendGet('/api/v1/knowledge/search?q=test&size=100');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    public function testSearchMinimumSize(\FunctionalTester $I): void
    {
        // Test minimum size (1)
        $I->sendGet('/api/v1/knowledge/search?q=test&size=0');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    public function testSearchWithInvalidMode(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=test&mode=invalid');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'test',
            'mode' => 'invalid',
        ]);
        // Should still work, just won't use embedding
    }

    public function testSearchResponseStructure(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=test&mode=keyword');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        // Verify response structure
        $I->seeResponseJsonMatchesJsonPath('$.query');
        $I->seeResponseJsonMatchesJsonPath('$.mode');
        $I->seeResponseJsonMatchesJsonPath('$.total');
        $I->seeResponseJsonMatchesJsonPath('$.results');
        
        // Verify results is an array
        $I->seeResponseMatchesJsonType([
            'query' => 'string',
            'mode' => 'string',
            'total' => 'integer',
            'results' => 'array',
        ]);
    }

    public function testSearchWithUkrainianQuery(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=' . urlencode('налаштування кешу') . '&mode=keyword');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'налаштування кешу',
            'mode' => 'keyword',
        ]);
    }

    public function testSearchWithSpecialCharacters(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search?q=' . urlencode('config/packages/cache.yaml') . '&mode=keyword');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'query' => 'config/packages/cache.yaml',
            'mode' => 'keyword',
        ]);
    }

    public function testHealthEndpoint(\FunctionalTester $I): void
    {
        $I->sendGet('/health');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'ok', 'service' => 'knowledge-agent']);
    }
}
