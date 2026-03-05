<?php

declare(strict_types=1);

namespace App\EdgeAuth;

final class EdgeJwtService
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function createToken(string $subject, int $ttlSeconds): string
    {
        $issuedAt = time();
        $payload = [
            'iss' => 'ai-community-platform',
            'sub' => $subject,
            'iat' => $issuedAt,
            'exp' => $issuedAt + max(60, $ttlSeconds),
        ];

        $headerPart = $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payloadPart = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerPart.'.'.$payloadPart, $this->secret, true);

        return $headerPart.'.'.$payloadPart.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            return null;
        }

        [$headerPart, $payloadPart, $signaturePart] = $parts;

        $expectedSignature = hash_hmac('sha256', $headerPart.'.'.$payloadPart, $this->secret, true);
        $decodedSignature = $this->base64UrlDecode($signaturePart);

        if (!hash_equals($expectedSignature, $decodedSignature)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($this->base64UrlDecode($payloadPart), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt <= time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if (0 !== $padding) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return false === $decoded ? '' : $decoded;
    }
}
