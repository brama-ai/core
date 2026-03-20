<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class EntriesApiCest
{
    public function testListEntriesWithoutFilters(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        // Verify response structure
        $I->seeResponseJsonMatchesJsonPath('$.entries');
        $I->seeResponseJsonMatchesJsonPath('$.count');
        $I->seeResponseJsonMatchesJsonPath('$.from');
        $I->seeResponseJsonMatchesJsonPath('$.size');
        
        $I->seeResponseMatchesJsonType([
            'entries' => 'array',
            'count' => 'integer',
            'from' => 'integer',
            'size' => 'integer',
        ]);
        
        // Default pagination
        $I->seeResponseContainsJson([
            'from' => 0,
            'size' => 20,
        ]);
    }

    public function testListEntriesWithPagination(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?from=10&size=5');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $I->seeResponseContainsJson([
            'from' => 10,
            'size' => 5,
        ]);
    }

    public function testListEntriesPaginationLimits(\FunctionalTester $I): void
    {
        // Test maximum size limit (100)
        $I->sendGet('/api/v1/knowledge/entries?size=150');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['size' => 100]);
        
        // Test minimum size (1)
        $I->sendGet('/api/v1/knowledge/entries?size=0');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['size' => 1]);
        
        // Test negative from (should be 0)
        $I->sendGet('/api/v1/knowledge/entries?from=-5');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['from' => 0]);
    }

    public function testListEntriesWithTreePathFilter(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?tree_path=Technology/PHP');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $I->seeResponseJsonMatchesJsonPath('$.entries');
        $I->seeResponseJsonMatchesJsonPath('$.count');
        
        // Verify entries match the filter (if any entries exist)
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['entries'])) {
            foreach ($data['entries'] as $entry) {
                $I->assertArrayHasKey('tree_path', $entry);
                $I->assertEquals('Technology/PHP', $entry['tree_path']);
            }
        }
    }

    public function testListEntriesWithCategoryFilter(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?category=Technology');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['entries'])) {
            foreach ($data['entries'] as $entry) {
                $I->assertArrayHasKey('category', $entry);
                $I->assertEquals('Technology', $entry['category']);
            }
        }
    }

    public function testListEntriesWithTagsFilter(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?tags=symfony,cache');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['entries'])) {
            foreach ($data['entries'] as $entry) {
                $I->assertArrayHasKey('tags', $entry);
                $I->assertIsArray($entry['tags']);
                
                // Entry should have at least one of the requested tags
                $hasRequestedTag = array_intersect(['symfony', 'cache'], $entry['tags']);
                $I->assertNotEmpty($hasRequestedTag);
            }
        }
    }

    public function testListEntriesWithMultipleFilters(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?category=Technology&tree_path=Technology/PHP&tags=symfony');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['entries'])) {
            foreach ($data['entries'] as $entry) {
                $I->assertEquals('Technology', $entry['category']);
                $I->assertEquals('Technology/PHP', $entry['tree_path']);
                $I->assertContains('symfony', $entry['tags']);
            }
        }
    }

    public function testListEntriesWithEmptyFilters(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?category=&tree_path=&tags=');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        // Empty filters should be ignored
        $I->seeResponseJsonMatchesJsonPath('$.entries');
    }

    public function testListEntriesResponseStructure(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries');
        $I->seeResponseCodeIs(200);
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        // Verify count matches actual entries length
        $I->assertEquals(count($data['entries']), $data['count']);
        
        if (!empty($data['entries'])) {
            foreach ($data['entries'] as $entry) {
                // Each entry should have required fields
                $I->assertArrayHasKey('title', $entry);
                $I->assertArrayHasKey('body', $entry);
                $I->assertArrayHasKey('tags', $entry);
                $I->assertArrayHasKey('category', $entry);
                $I->assertArrayHasKey('tree_path', $entry);
                
                // Verify field types
                $I->assertIsString($entry['title']);
                $I->assertIsString($entry['body']);
                $I->assertIsArray($entry['tags']);
                $I->assertIsString($entry['category']);
                $I->assertIsString($entry['tree_path']);
            }
        }
    }

    public function testListEntriesWithUkrainianFilters(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?category=' . urlencode('Технології'));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        if (!empty($data['entries'])) {
            foreach ($data['entries'] as $entry) {
                $I->assertEquals('Технології', $entry['category']);
            }
        }
    }

    public function testListEntriesWithSpecialCharactersInFilters(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries?tree_path=' . urlencode('Technology/PHP/Symfony'));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
    }

    public function testListEntriesCountConsistency(\FunctionalTester $I): void
    {
        // Get first page
        $I->sendGet('/api/v1/knowledge/entries?size=5');
        $I->seeResponseCodeIs(200);
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        
        // Count should match array length
        $I->assertEquals(count($data['entries']), $data['count']);
        
        // If we have more than 5 entries, test second page
        if ($data['count'] === 5) {
            $I->sendGet('/api/v1/knowledge/entries?from=5&size=5');
            $I->seeResponseCodeIs(200);
            
            $response2 = $I->grabResponse();
            $data2 = json_decode($response2, true);
            
            $I->assertEquals(count($data2['entries']), $data2['count']);
        }
    }
}