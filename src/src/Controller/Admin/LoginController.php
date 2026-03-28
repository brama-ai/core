<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\TurnstileVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    public function __construct(
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly string $turnstileSiteKey,
    ) {
    }

    #[Route('/admin/login', name: 'admin_login')]
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'turnstile_enabled' => $this->turnstileVerifier->isEnabled(),
            'turnstile_site_key' => $this->turnstileSiteKey,
        ]);
    }
}
