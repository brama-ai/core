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

        return new RedirectResponse($this->buildLoginUrl($requestedUrl), Response::HTTP_FOUND);
    }

    private function buildRequestedUrl(Request $request): string
    {
        $uri = (string) $request->headers->get('X-Forwarded-Uri', '/');
        if ('' === $uri) {
            $uri = '/';
        }

        return $uri;
    }

    private function buildLoginUrl(string $requestedUrl): string
    {
        $base = rtrim($this->loginBaseUrl, '/');

        return sprintf('%s/edge/auth/login?rd=%s', $base, urlencode($requestedUrl));
    }
}
