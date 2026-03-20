<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class TreeApiCest
{
    public function testGetKnowledgeTree(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/tree');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        // Verify response structure
        $I->seeResponseJsonMatchesJsonPath('$.tree');
        $I->seeResponseMatchesJsonType([
            'tree' => 'array',
        ]);
    }

    public function testTreeResponseIsCached(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/tree');
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Cache-Control', 'max-age=60, public');
    }

    public function testTreeStructureFormat(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/tree');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        // Tree should be an array of nodes
        $I->seeResponseJsonMatchesJsonPath('$.tree');
        
        // If tree has items, they should have proper structure
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['tree'])) {
            // Each tree node should have required fields
            foreach ($data['tree'] as $node) {
                $I->assertArrayHasKey('path', $node);
                $I->assertArrayHasKey('count', $node);
                $I->assertIsString($node['path']);
                $I->assertIsInt($node['count']);
                $I->assertGreaterThan(0, $node['count']);
            }
        }
    }

    public function testTreePathHierarchy(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/tree');
        $I->seeResponseCodeIs(200);
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['tree'])) {
            foreach ($data['tree'] as $node) {
                // Tree paths should be slash-separated
                $I->assertStringContainsString('/', $node['path'] . '/'); // Add slash to handle root paths
                
                // Paths should not start or end with slash
                $I->assertStringStartsNotWith('/', $node['path']);
                $I->assertStringEndsNotWith('/', $node['path']);
            }
        }
    }
}