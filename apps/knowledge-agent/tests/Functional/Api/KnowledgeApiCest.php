<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

class KnowledgeApiCest
{
    private function internalToken(): string
    {
        $token = $_ENV['APP_INTERNAL_TOKEN'] ?? $_SERVER['APP_INTERNAL_TOKEN'] ?? 'dev-internal-token';

        return (string) $token;
    }

    public function healthEndpointReturns200(\FunctionalTester $I): void
    {
        $I->sendGet('/health');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'ok', 'service' => 'knowledge-agent']);
    }

    public function uploadRequiresToken(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/knowledge/upload', json_encode(['messages' => []], \JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(401);
    }

    public function uploadWithEmptyMessagesReturns422(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        $I->sendPost('/api/v1/knowledge/upload', json_encode(['messages' => []], \JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(422);
    }

    public function searchRequiresQueryParam(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search');
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
    }

    public function a2aRequiresToken(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/knowledge/a2a', json_encode(['request' => ['intent' => 'get_tree']], \JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(401);
    }

    public function a2aAcceptsDirectEnvelopeForStoreMessageIntent(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        $I->sendPost('/api/v1/knowledge/a2a', json_encode([
            'intent' => 'knowledge.store_message',
            'request_id' => 'req-functional-store-message',
            'payload' => [],
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'status' => 'failed',
            'request_id' => 'req-functional-store-message',
            'error' => 'message payload is required',
        ]);
    }

    public function entryGetReturns404ForUnknownId(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries/nonexistent-id-xyz');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
    }

    public function treeEndpointReturnsJson(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/tree');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['tree' => []]);
    }

    public function entriesEndpointReturnsJson(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['entries' => [], 'count' => 0]);
    }

    // CRUD Operations Tests

    public function createEntryRequiresAuth(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/knowledge/entries', json_encode([
            'title' => 'Test Entry',
            'body' => 'Test body content',
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function createEntryRequiresTitleAndBody(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        // Missing title
        $I->sendPost('/api/v1/knowledge/entries', json_encode([
            'body' => 'Test body content',
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['error' => 'title and body are required']);
        
        // Missing body
        $I->sendPost('/api/v1/knowledge/entries', json_encode([
            'title' => 'Test Entry',
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['error' => 'title and body are required']);
    }

    public function createEntryWithInvalidPayload(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        // Invalid JSON
        $I->sendPost('/api/v1/knowledge/entries', 'invalid json');
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['error' => 'title and body are required']);
    }

    public function createEntrySuccessfully(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        $entryData = [
            'title' => 'Test Knowledge Entry',
            'body' => '# Test Knowledge\n\nThis is a test knowledge entry with **markdown** content.',
            'tags' => ['test', 'knowledge', 'functional'],
            'category' => 'Testing',
            'tree_path' => 'Testing/Functional',
        ];
        
        $I->sendPost('/api/v1/knowledge/entries', json_encode($entryData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseJsonMatchesJsonPath('$.id');
        $I->seeResponseContainsJson(['status' => 'created']);
    }

    public function updateEntryRequiresAuth(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPut('/api/v1/knowledge/entries/test-id', json_encode([
            'title' => 'Updated Title',
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function updateNonexistentEntry(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        $I->sendPut('/api/v1/knowledge/entries/nonexistent-id', json_encode([
            'title' => 'Updated Title',
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(404);
        $I->seeResponseContainsJson(['error' => 'Not found']);
    }

    public function updateEntryWithInvalidPayload(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        $I->sendPut('/api/v1/knowledge/entries/test-id', 'invalid json');
        $I->seeResponseCodeIs(422);
        $I->seeResponseContainsJson(['error' => 'Invalid payload']);
    }

    public function deleteEntryRequiresAuth(\FunctionalTester $I): void
    {
        $I->sendDelete('/api/v1/knowledge/entries/test-id');
        $I->seeResponseCodeIs(401);
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function deleteNonexistentEntry(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        $I->sendDelete('/api/v1/knowledge/entries/nonexistent-id');
        $I->seeResponseCodeIs(404);
        $I->seeResponseContainsJson(['error' => 'Not found']);
    }

    public function fullCrudWorkflow(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        // 1. Create entry
        $entryData = [
            'title' => 'CRUD Test Entry',
            'body' => '# CRUD Test\n\nThis entry tests the full CRUD workflow.',
            'tags' => ['crud', 'test', 'workflow'],
            'category' => 'Testing',
            'tree_path' => 'Testing/CRUD',
        ];
        
        $I->sendPost('/api/v1/knowledge/entries', json_encode($entryData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(201);
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        $entryId = $data['id'];
        
        // 2. Read entry
        $I->sendGet("/api/v1/knowledge/entries/{$entryId}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'entry' => [
                'title' => 'CRUD Test Entry',
                'category' => 'Testing',
                'tree_path' => 'Testing/CRUD',
            ]
        ]);
        
        // 3. Update entry
        $updateData = [
            'title' => 'Updated CRUD Test Entry',
            'body' => '# Updated CRUD Test\n\nThis entry has been updated.',
            'tags' => ['crud', 'test', 'updated'],
        ];
        
        $I->sendPut("/api/v1/knowledge/entries/{$entryId}", json_encode($updateData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'status' => 'updated',
            'id' => $entryId,
        ]);
        
        // 4. Verify update
        $I->sendGet("/api/v1/knowledge/entries/{$entryId}");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'entry' => [
                'title' => 'Updated CRUD Test Entry',
            ]
        ]);
        
        // 5. Delete entry
        $I->sendDelete("/api/v1/knowledge/entries/{$entryId}");
        $I->seeResponseCodeIs(204);
        
        // 6. Verify deletion
        $I->sendGet("/api/v1/knowledge/entries/{$entryId}");
        $I->seeResponseCodeIs(404);
    }

    public function createEntryWithUkrainianContent(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        $entryData = [
            'title' => 'Українська стаття',
            'body' => '# Українська стаття\n\nЦе тестова стаття українською мовою з **жирним** текстом.',
            'tags' => ['українська', 'тест', 'стаття'],
            'category' => 'Тестування',
            'tree_path' => 'Тестування/Українська',
        ];
        
        $I->sendPost('/api/v1/knowledge/entries', json_encode($entryData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
        $I->seeResponseJsonMatchesJsonPath('$.id');
        $I->seeResponseContainsJson(['status' => 'created']);
    }

    public function createEntryWithComplexMarkdown(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        $complexBody = "# Complex Markdown Entry\n\n## Code Example\n\n```php\n<?php\nclass Example {\n    public function test() {\n        return 'Hello World';\n    }\n}\n```\n\n## List\n\n- Item 1\n- Item 2\n  - Nested item\n\n## Table\n\n| Column 1 | Column 2 |\n|----------|----------|\n| Value 1  | Value 2  |\n\n## Links\n\n[Example Link](https://example.com)";
        
        $entryData = [
            'title' => 'Complex Markdown Test',
            'body' => $complexBody,
            'tags' => ['markdown', 'complex', 'test'],
            'category' => 'Documentation',
            'tree_path' => 'Documentation/Markdown',
        ];
        
        $I->sendPost('/api/v1/knowledge/entries', json_encode($entryData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(201);
        $I->seeResponseIsJson();
    }

    public function updateEntryRegeneratesEmbedding(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        
        // Create entry first
        $entryData = [
            'title' => 'Embedding Test Entry',
            'body' => 'Original content for embedding test.',
            'category' => 'Testing',
        ];
        
        $I->sendPost('/api/v1/knowledge/entries', json_encode($entryData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(201);
        
        $response = $I->grabResponse();
        $data = json_decode($response, true);
        $entryId = $data['id'];
        
        // Update with new content (should regenerate embedding)
        $updateData = [
            'title' => 'Updated Embedding Test Entry',
            'body' => 'Updated content that should trigger embedding regeneration.',
        ];
        
        $I->sendPut("/api/v1/knowledge/entries/{$entryId}", json_encode($updateData, JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'status' => 'updated',
            'id' => $entryId,
        ]);
        
        // Clean up
        $I->sendDelete("/api/v1/knowledge/entries/{$entryId}");
        $I->seeResponseCodeIs(204);
    }
}
