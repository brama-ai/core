<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Logging\LogSettingsProvider;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SettingsController extends AbstractController
{
    private const VALID_LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'];

    public function __construct(
        private readonly LogSettingsProvider $settingsProvider,
    ) {
    }

    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function __invoke(#[CurrentUser] User $user, Request $request): Response
    {
        $saved = false;

        if ($request->isMethod('POST')) {
            $level = strtoupper((string) $request->request->get('log_level', 'DEBUG'));
            if (!\in_array($level, self::VALID_LEVELS, true)) {
                $level = 'DEBUG';
            }

            $retentionDays = max(1, (int) $request->request->get('retention_days', 7));
            $maxSizeGb = max(1, (int) $request->request->get('max_size_gb', 2));

            $this->settingsProvider->save([
                'log_level' => $level,
                'retention_days' => $retentionDays,
                'max_size_gb' => $maxSizeGb,
            ]);

            $saved = true;
        }

        $settings = $this->settingsProvider->load();

        return $this->render('admin/settings.html.twig', [
            'username' => $user->getUserIdentifier(),
            'settings' => $settings,
            'levels' => self::VALID_LEVELS,
            'saved' => $saved,
        ]);
    }
}
