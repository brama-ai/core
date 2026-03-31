<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\AgentRegistry\AgentRegistryInterface;
use App\Dashboard\DashboardMetricsService;
use App\Security\User;
use App\Telegram\Repository\TelegramBotRepository;
use App\Telegram\Repository\TelegramChatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly DashboardMetricsService $metricsService,
        private readonly TelegramBotRepository $telegramBotRepository,
        private readonly TelegramChatRepository $telegramChatRepository,
    ) {
    }

    #[Route('/admin/', name: 'admin_index')]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('admin_dashboard', [], Response::HTTP_FOUND);
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $all = $this->registry->findAll();
        $enabled = array_filter($all, static fn (array $a): bool => (bool) $a['enabled']);

        $metrics = $this->metricsService->getMetrics();
        $telegramStats = $this->buildTelegramStats();

        return $this->render('admin/dashboard.html.twig', [
            'username' => $user->getUserIdentifier(),
            'agents_total' => count($all),
            'agents_enabled' => count($enabled),
            'agents_disabled' => count($all) - count($enabled),
            'metrics' => $metrics,
            'telegram_stats' => $telegramStats,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function buildTelegramStats(): array
    {
        try {
            $bots = $this->telegramBotRepository->findAll();
            $chats = $this->telegramChatRepository->findAll();

            $enabledBots = array_filter($bots, static fn (array $b): bool => (bool) $b['enabled']);
            $activeChats = array_filter($chats, static fn (array $c): bool => null === ($c['left_at'] ?? null));

            $since = (new \DateTimeImmutable())->modify('-24 hours');
            $recentChats = array_filter($chats, static function (array $c) use ($since): bool {
                $lastMsg = $c['last_message_at'] ?? null;

                return $lastMsg instanceof \DateTimeImmutable && $lastMsg > $since;
            });

            return [
                'total_bots' => count($bots),
                'enabled_bots' => count($enabledBots),
                'total_chats' => count($chats),
                'active_chats' => count($activeChats),
                'messages_today' => count($recentChats),
            ];
        } catch (\Throwable) {
            return [
                'total_bots' => 0,
                'enabled_bots' => 0,
                'total_chats' => 0,
                'active_chats' => 0,
                'messages_today' => 0,
            ];
        }
    }
}
