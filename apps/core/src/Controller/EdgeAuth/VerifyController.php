<?php

declare(strict_types=1);

namespace App\Controller\EdgeAuth;

use App\EdgeAuth\EdgeJwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyController extends AbstractController
{
    public function __construct(
        private readonly EdgeJwtService $jwtService,
        private readonly string $cookieName,
        private readonly string $loginBaseUrl,
    ) {
    }

    #[Route('/edge/auth/verify', name: 'edge_auth_verify', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $token = (string) $request->cookies->get($this->cookieName, '');

        if ('' !== $token && null !== $this->jwtService->validateToken($token)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $requestedUrl = $this->buildRequestedUrl($request);

        // Redirect to login on the SAME subdomain so the cookie is host-only.
        // Traefik routes /edge/auth/ on all subdomains to core.
        $parsed = parse_url($requestedUrl);
        if (isset($parsed['scheme'], $parsed['host'])) {
            $base = sprintf('%s://%s', $parsed['scheme'], $parsed['host']);
        } else {
            $base = rtrim($this->loginBaseUrl, '/');
        }
        $loginUrl = sprintf('%s/edge/auth/login?rd=%s', $base, urlencode($requestedUrl));

        return new RedirectResponse($loginUrl, Response::HTTP_FOUND);
    }

    private function buildRequestedUrl(Request $request): string
    {
        $host = (string) $request->headers->get('X-Forwarded-Host', '');
        $uri = (string) $request->headers->get('X-Forwarded-Uri', '/');
        $proto = (string) $request->headers->get('X-Forwarded-Proto', 'http');

        if ('' === $uri) {
            $uri = '/';
        }

        if ('' === $host) {
            return $uri;
        }

        return sprintf('%s://%s%s', $proto, $host, $uri);
    }
}
