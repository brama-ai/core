<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging\DTO;

use App\Logging\DTO\CaptureMeta;
use App\Logging\DTO\SanitizationResult;
use Codeception\Test\Unit;

final class SanitizationResultTest extends Unit
{
    public function testFromArrayWithFullData(): void
    {
        $result = SanitizationResult::fromArray([
            'data' => ['key' => 'value'],
            'capture_meta' => [
                'is_truncated' => true,
                'original_size_bytes' => 5000,
                'captured_size_bytes' => 2048,
                'redacted_fields_count' => 3,
                'truncated_values_count' => 1,
            ],
        ]);

        $this->assertSame(['key' => 'value'], $result->data);
        $this->assertInstanceOf(CaptureMeta::class, $result->captureMeta);
        $this->assertTrue($result->captureMeta->isTruncated);
        $this->assertSame(5000, $result->captureMeta->originalSizeBytes);
        $this->assertSame(2048, $result->captureMeta->capturedSizeBytes);
        $this->assertSame(3, $result->captureMeta->redactedFieldsCount);
        $this->assertSame(1, $result->captureMeta->truncatedValuesCount);
    }

    public function testFromArrayWithMissingData(): void
    {
        $result = SanitizationResult::fromArray([]);

        $this->assertNull($result->data);
        $this->assertFalse($result->captureMeta->isTruncated);
        $this->assertSame(0, $result->captureMeta->originalSizeBytes);
    }

    public function testFromArrayWithScalarData(): void
    {
        $result = SanitizationResult::fromArray(['data' => 'plain string']);

        $this->assertSame('plain string', $result->data);
    }

    public function testToArrayRoundtrip(): void
    {
        $input = [
            'data' => ['sanitized' => true],
            'capture_meta' => [
                'is_truncated' => false,
                'original_size_bytes' => 100,
                'captured_size_bytes' => 100,
                'redacted_fields_count' => 0,
                'truncated_values_count' => 0,
            ],
        ];

        $result = SanitizationResult::fromArray($input);
        $output = $result->toArray();

        $this->assertSame($input['data'], $output['data']);
        $this->assertSame($input['capture_meta'], $output['capture_meta']);
    }
}
