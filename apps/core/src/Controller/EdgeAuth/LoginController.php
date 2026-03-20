<?php

declare(strict_types=1);

namespace App\Controller\EdgeAuth;

use App\EdgeAuth\EdgeJwtService;
use App\Security\User;
use App\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LoginController extends AbstractController
{
    public function __construct(
        private readonly UserProvider $userProvider,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EdgeJwtService $jwtService,
        private readonly string $cookieName,
        private readonly int $tokenTtlSeconds,
    ) {
    }

    #[Route('/edge/auth/login', name: 'edge_auth_login', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, #[CurrentUser] ?User $currentUser): Response
    {
        $requestedRedirect = $request->isMethod('POST')
            ? (string) $request->request->get('rd', '')
            : (string) $request->query->get('rd', '');
        $redirectTarget = $this->normalizeRedirectTarget($requestedRedirect);

        if (!$request->isMethod('POST') && $currentUser instanceof User) {
            return $this->redirectWithToken($currentUser->getUserIdentifier(), $redirectTarget);
        }

        if (!$request->isMethod('POST')) {
            return $this->render('edge_auth/login.html.twig', [
                'rd' => $redirectTarget,
                'error' => null,
                'last_username' => '',
            ]);
        }

        $username = trim((string) $request->request->get('_username', ''));
        $password = (string) $request->request->get('_password', '');

        if ('' === $username || '' === $password) {
            return $this->render('edge_auth/login.html.twig', [
                'rd' => $redirectTarget,
                'error' => 'Введіть логін і пароль.',
                'last_username' => $username,
            ], new Response('', Response::HTTP_UNAUTHORIZED));
        }

        $user = $this->loadUser($username);

        if (null === $user || !$this->isPasswordValid($user, $password)) {
            return $this->render('edge_auth/login.html.twig', [
                'rd' => $redirectTarget,
                'error' => 'Невірний логін або пароль.',
                'last_username' => $username,
            ], new Response('', Response::HTTP_UNAUTHORIZED));
        }

        return $this->redirectWithToken($user->getUserIdentifier(), $redirectTarget);
    }

    private function redirectWithToken(string $username, string $redirectTarget): RedirectResponse
    {
        $token = $this->jwtService->createToken($username, $this->tokenTtlSeconds);
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $this->tokenTtlSeconds));

        $response = new RedirectResponse($redirectTarget, Response::HTTP_FOUND);

        // Use host-only cookie (no domain) so each subdomain gets its own cookie.
        // This avoids browser issues where domain=localhost cookies aren't sent to *.localhost.
        $response->headers->setCookie(Cookie::create(
            $this->cookieName,
            $token,
            $expiresAt,
            '/',
            null,
            false,
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));

        return $response;
    }

    private function normalizeRedirectTarget(string $target): string
    {
        $default = '/admin/dashboard';

        if ('' === $target) {
            return $default;
        }

        if (str_starts_with($target, '/')) {
            return $target;
        }

        $parts = parse_url($target);
        if (!is_array($parts)) {
            return $default;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            return $default;
        }

        if ('' !== $host
            && !in_array($host, ['localhost', '127.0.0.1'], true)
            && !str_ends_with($host, '.localhost')
        ) {
            return $default;
        }

        return $target;
    }

    private function loadUser(string $username): ?User
    {
        try {
            $user = $this->userProvider->loadUserByIdentifier($username);
        } catch (UserNotFoundException) {
            return null;
        }

        return $user;
    }

    private function isPasswordValid(UserInterface $user, string $password): bool
    {
        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($user, $password);
    }
}
