<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TurnstileVerifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly bool $enabled,
        private readonly string $secretKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function verify(Request $request): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $token = (string) $request->request->get('cf-turnstile-response', '');

        if ('' === $token) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $request->getClientIp() ?? '',
                ],
            ]);

            $data = $response->toArray();

            return (bool) ($data['success'] ?? false);
        } catch (\Exception) {
            return false;
        }
    }
}
