<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentRegistry;

use App\AgentRegistry\ManifestValidator;
use Codeception\Test\Unit;

/**
 * Validates agent manifest structure, required fields, and normalization.
 *
 * @see docs/specs/a2a-protocol.md (Section: Agent Card payload and structure)
 */
final class ManifestValidatorTest extends Unit
{
    private ManifestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ManifestValidator();
    }

    public function testValidManifestPassesValidation(): void
    {
        $manifest = [
            'name' => 'knowledge-base',
            'version' => '0.1.0',
            'description' => 'Extracts structured knowledge from chat messages.',
            'url' => 'http://knowledge-agent/a2a',
        ];

        $errors = $this->validator->validate($manifest);

        $this->assertSame([], $errors);
    }

    public function testLegacyManifestWithA2aEndpointPassesValidation(): void
    {
        $manifest = [
            'name' => 'knowledge-base',
            'version' => '0.1.0',
            'a2a_endpoint' => 'http://knowledge-agent/a2a',
        ];

        $errors = $this->validator->validate($manifest);

        $this->assertSame([], $errors);
    }

    public function testMissingRequiredFieldsReturnsErrors(): void
    {
        $errors = $this->validator->validate([]);

        foreach (['name', 'version'] as $field) {
            $this->assertNotEmpty(
                array_filter($errors, static fn (string $e) => str_contains($e, $field)),
                sprintf('Expected error for missing field "%s"', $field),
            );
        }
    }

    public function testInvalidNameFormatReturnsError(): void
    {
        $manifest = $this->validManifest(['name' => 'Invalid Name!']);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name', implode(' ', $errors));
    }

    public function testInvalidVersionFormatReturnsError(): void
    {
        $manifest = $this->validManifest(['version' => 'v1.2']);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('version', implode(' ', $errors));
    }

    public function testInvalidUrlReturnsError(): void
    {
        $manifest = $this->validManifest(['url' => 'not-a-url']);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('url', implode(' ', $errors));
    }

    public function testInvalidA2aEndpointReturnsError(): void
    {
        $manifest = $this->validManifest(['a2a_endpoint' => 'not-a-url', 'url' => null]);
        unset($manifest['url']);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('a2a_endpoint', implode(' ', $errors));
    }

    public function testInvalidConfigSchemaTypeReturnsError(): void
    {
        $manifest = $this->validManifest(['config_schema' => 'should-be-object']);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('config_schema', implode(' ', $errors));
    }

    public function testOptionalFieldsAreIgnoredWhenAbsent(): void
    {
        $errors = $this->validator->validate($this->validManifest());

        $this->assertSame([], $errors);
    }

    public function testValidOptionalFieldsPassValidation(): void
    {
        $manifest = $this->validManifest([
            'config_schema' => ['type' => 'object', 'properties' => []],
            'skills' => ['knowledge_search'],
            'health_url' => 'http://knowledge-agent/health',
            'admin_url' => '/admin/knowledge',
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertSame([], $errors);
    }

    public function testStructuredSkillsPassValidation(): void
    {
        $manifest = $this->validManifest([
            'skills' => [
                [
                    'id' => 'hello.greet',
                    'name' => 'Hello Greet',
                    'description' => 'Greet a user by name',
                    'tags' => ['greeting'],
                    'examples' => ['Greet John'],
                ],
            ],
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertSame([], $errors);
    }

    public function testStructuredSkillMissingRequiredFieldReturnsError(): void
    {
        $manifest = $this->validManifest([
            'skills' => [
                ['id' => 'test.skill'],
            ],
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name', implode(' ', $errors));
    }

    public function testMixedSkillsPassValidation(): void
    {
        $manifest = $this->validManifest([
            'skills' => [
                'legacy.skill',
                ['id' => 'structured.skill', 'name' => 'Structured Skill', 'description' => 'A structured skill'],
            ],
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertSame([], $errors);
    }

    public function testProviderValidation(): void
    {
        $manifest = $this->validManifest([
            'provider' => ['organization' => 'Test Org', 'url' => 'http://example.com'],
        ]);

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testInvalidProviderUrlReturnsError(): void
    {
        $manifest = $this->validManifest([
            'provider' => ['organization' => 'Test Org', 'url' => 'not-a-url'],
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('provider.url', implode(' ', $errors));
    }

    public function testCapabilitiesValidation(): void
    {
        $manifest = $this->validManifest([
            'capabilities' => ['streaming' => false, 'pushNotifications' => false],
        ]);

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testInvalidCapabilitiesReturnsError(): void
    {
        $manifest = $this->validManifest([
            'capabilities' => ['streaming' => 'not-a-bool'],
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('streaming', implode(' ', $errors));
    }

    public function testDefaultInputModesValidation(): void
    {
        $manifest = $this->validManifest([
            'defaultInputModes' => ['text', 'image'],
            'defaultOutputModes' => ['text'],
        ]);

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testDocumentationUrlValidation(): void
    {
        $manifest = $this->validManifest([
            'documentationUrl' => 'http://docs.example.com/agent',
        ]);

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testInvalidDocumentationUrlReturnsError(): void
    {
        $manifest = $this->validManifest([
            'documentationUrl' => 'not-a-url',
        ]);

        $errors = $this->validator->validate($manifest);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('documentationUrl', implode(' ', $errors));
    }

    // --- Normalization tests ---

    public function testNormalizeConvertsA2aEndpointToUrl(): void
    {
        $manifest = ['a2a_endpoint' => 'http://test/a2a', 'name' => 'test'];

        $normalized = $this->validator->normalize($manifest);

        $this->assertSame('http://test/a2a', $normalized['url']);
        $this->assertSame('http://test/a2a', $normalized['a2a_endpoint']);
    }

    public function testNormalizePreservesUrlWhenBothPresent(): void
    {
        $manifest = ['url' => 'http://url/a2a', 'a2a_endpoint' => 'http://endpoint/a2a'];

        $normalized = $this->validator->normalize($manifest);

        $this->assertSame('http://url/a2a', $normalized['url']);
    }

    public function testNormalizeConvertsStringSkillsToObjects(): void
    {
        $manifest = [
            'skills' => ['hello.greet', 'hello.farewell'],
            'skill_schemas' => [
                'hello.greet' => ['description' => 'Greet a user'],
            ],
        ];

        $normalized = $this->validator->normalize($manifest);

        $this->assertCount(2, $normalized['skills']);
        $this->assertSame('hello.greet', $normalized['skills'][0]['id']);
        $this->assertSame('Greet a user', $normalized['skills'][0]['description']);
        $this->assertSame('hello.farewell', $normalized['skills'][1]['id']);
        $this->assertSame('', $normalized['skills'][1]['description']);
    }

    public function testNormalizePreservesStructuredSkills(): void
    {
        $manifest = [
            'skills' => [
                ['id' => 'hello.greet', 'name' => 'Greet', 'description' => 'Greet a user', 'tags' => ['greeting']],
            ],
        ];

        $normalized = $this->validator->normalize($manifest);

        $this->assertSame('hello.greet', $normalized['skills'][0]['id']);
        $this->assertSame(['greeting'], $normalized['skills'][0]['tags']);
    }

    // --- extractSkillIds tests ---

    public function testExtractSkillIdsFromStringSkills(): void
    {
        $manifest = ['skills' => ['a', 'b', 'c']];

        $this->assertSame(['a', 'b', 'c'], ManifestValidator::extractSkillIds($manifest));
    }

    public function testExtractSkillIdsFromStructuredSkills(): void
    {
        $manifest = [
            'skills' => [
                ['id' => 'hello.greet', 'name' => 'Greet', 'description' => 'Greet'],
                ['id' => 'hello.farewell', 'name' => 'Farewell', 'description' => 'Farewell'],
            ],
        ];

        $this->assertSame(['hello.greet', 'hello.farewell'], ManifestValidator::extractSkillIds($manifest));
    }

    public function testExtractSkillIdsFromMixedSkills(): void
    {
        $manifest = [
            'skills' => [
                'legacy.skill',
                ['id' => 'new.skill', 'name' => 'New', 'description' => 'New'],
            ],
        ];

        $this->assertSame(['legacy.skill', 'new.skill'], ManifestValidator::extractSkillIds($manifest));
    }

    // --- resolveUrl tests ---

    public function testResolveUrlPrefersUrl(): void
    {
        $manifest = ['url' => 'http://url/a2a', 'a2a_endpoint' => 'http://endpoint/a2a'];

        $this->assertSame('http://url/a2a', ManifestValidator::resolveUrl($manifest));
    }

    public function testResolveUrlFallsBackToA2aEndpoint(): void
    {
        $manifest = ['a2a_endpoint' => 'http://endpoint/a2a'];

        $this->assertSame('http://endpoint/a2a', ManifestValidator::resolveUrl($manifest));
    }

    public function testResolveUrlReturnsEmptyWhenMissing(): void
    {
        $this->assertSame('', ManifestValidator::resolveUrl([]));
    }

    // --- Storage tests (unchanged) ---

    public function testValidStorageSectionPassesValidation(): void
    {
        $manifest = $this->validManifest([
            'storage' => [
                'postgres' => ['db_name' => 'my_agent', 'user' => 'my_agent', 'password' => 'my_agent'],
                'redis' => ['db_number' => 1],
                'opensearch' => ['collections' => ['chunks', 'pages']],
            ],
        ]);

        $this->assertSame([], $this->validator->validate($manifest));
    }

    public function testManifestWithoutStorageSectionPassesValidation(): void
    {
        $this->assertSame([], $this->validator->validate($this->validManifest()));
    }

    public function testStorageSectionMustBeObject(): void
    {
        $errors = $this->validator->validate($this->validManifest(['storage' => 'not-an-object']));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('storage', implode(' ', $errors));
    }

    public function testStoragePostgresRequiresAllFields(): void
    {
        foreach (['db_name', 'user', 'password'] as $field) {
            $postgres = ['db_name' => 'db', 'user' => 'usr', 'password' => 'pwd'];
            unset($postgres[$field]);

            $errors = $this->validator->validate($this->validManifest([
                'storage' => ['postgres' => $postgres],
            ]));

            $this->assertNotEmpty($errors, sprintf('Expected error for missing postgres.%s', $field));
        }
    }

    public function testStoragePostgresDbNameMustBeValidIdentifier(): void
    {
        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['postgres' => ['db_name' => 'INVALID-NAME!', 'user' => 'usr', 'password' => 'pwd']],
        ]));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('db_name', implode(' ', $errors));
    }

    public function testStoragePostgresUserMustBeValidIdentifier(): void
    {
        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['postgres' => ['db_name' => 'db', 'user' => 'BAD-USER', 'password' => 'pwd']],
        ]));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('user', implode(' ', $errors));
    }

    public function testStorageRedisDbNumberMustBeInRange(): void
    {
        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['redis' => ['db_number' => 16]],
        ]));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('db_number', implode(' ', $errors));

        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['redis' => ['db_number' => -1]],
        ]));

        $this->assertNotEmpty($errors);
    }

    public function testStorageRedisDbNumberMustBeInteger(): void
    {
        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['redis' => ['db_number' => 'not-int']],
        ]));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('db_number', implode(' ', $errors));
    }

    public function testStorageOpenSearchCollectionsMustBeNonEmpty(): void
    {
        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['opensearch' => ['collections' => []]],
        ]));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('collections', implode(' ', $errors));
    }

    public function testStorageOpenSearchCollectionNamesMustBeValid(): void
    {
        $errors = $this->validator->validate($this->validManifest([
            'storage' => ['opensearch' => ['collections' => ['INVALID!']]],
        ]));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('collections', implode(' ', $errors));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validManifest(array $overrides = []): array
    {
        return array_merge([
            'name' => 'test-agent',
            'version' => '1.0.0',
            'url' => 'http://test-agent/a2a',
        ], $overrides);
    }
}
