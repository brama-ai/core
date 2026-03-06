<?php

declare(strict_types=1);

namespace App\Logging;

final class PayloadSanitizer
{
    private const REDACTED = '[REDACTED]';
    private const DEFAULT_MAX_BYTES = 16384;
    private const DEFAULT_MAX_STRING_BYTES = 1024;

    /**
     * @return array{data: mixed, capture_meta: array<string, int|bool>}
     */
    public function sanitize(mixed $value, int $maxBytes = self::DEFAULT_MAX_BYTES): array
    {
        $normalized = $this->normalize($value);
        $originalJson = $this->encode($normalized);

        $redactedCount = 0;
        $truncatedValues = 0;
        $sanitized = $this->sanitizeValue($normalized, '', $redactedCount, $truncatedValues);

        $capturedJson = $this->encode($sanitized);
        $isTruncated = false;

        if (\strlen($capturedJson) > $maxBytes) {
            $isTruncated = true;
            $sanitized = [
                '_truncated' => true,
                '_preview' => substr($capturedJson, 0, $maxBytes),
            ];
            $capturedJson = $this->encode($sanitized);
        }

        return [
            'data' => $sanitized,
            'capture_meta' => [
                'is_truncated' => $isTruncated,
                'original_size_bytes' => \strlen($originalJson),
                'captured_size_bytes' => \strlen($capturedJson),
                'redacted_fields_count' => $redactedCount,
                'truncated_values_count' => $truncatedValues,
            ],
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if (\is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item);
            }

            return $normalized;
        }

        if (\is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $this->normalize($value->jsonSerialize());
            }

            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return ['_object' => $value::class];
        }

        if (\is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    private function sanitizeValue(mixed $value, string $keyPath, int &$redactedCount, int &$truncatedValues): mixed
    {
        if (\is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $keyString = (string) $key;
                $path = '' === $keyPath ? $keyString : $keyPath.'.'.$keyString;

                if ($this->isSensitiveKey($keyString)) {
                    ++$redactedCount;
                    $sanitized[$key] = self::REDACTED;
                    continue;
                }

                $sanitized[$key] = $this->sanitizeValue($item, $path, $redactedCount, $truncatedValues);
            }

            return $sanitized;
        }

        if (\is_string($value)) {
            if (\strlen($value) > self::DEFAULT_MAX_STRING_BYTES) {
                ++$truncatedValues;

                return substr($value, 0, self::DEFAULT_MAX_STRING_BYTES).'...[truncated]';
            }

            return $value;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);

        foreach (['token', 'authorization', 'api_key', 'apikey', 'secret', 'password', 'cookie'] as $needle) {
            if (str_contains($keyLower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function encode(mixed $value): string
    {
        $json = json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return false === $json ? '"[unencodable]"' : $json;
    }
}
