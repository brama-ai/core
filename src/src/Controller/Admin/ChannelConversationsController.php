<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\User;
use App\Telegram\Repository\TelegramBotRepository;
use App\Telegram\Repository\TelegramChatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Admin controller for channel conversations.
 *
 * Replaces TelegramChatsAdminController. Works with channel_conversations table
 * (renamed from telegram_chats) via TelegramChatRepository.
 */
final class ChannelConversationsController extends AbstractController
{
    public function __construct(
        private readonly TelegramChatRepository $chatRepository,
        private readonly TelegramBotRepository $botRepository,
    ) {
    }

    #[Route('/admin/channels/conversations', name: 'admin_channel_conversations', methods: ['GET'])]
    #[Route('/admin/telegram/chats', name: 'admin_telegram_chats', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $chats = $this->chatRepository->findAll();
        $bots = $this->botRepository->findAll();

        $botMap = [];
        foreach ($bots as $bot) {
            $botMap[$bot['id']] = $bot;
        }

        $stats = $this->buildStats($chats);

        return $this->render('admin/telegram/chats.html.twig', [
            'chats' => $chats,
            'bot_map' => $botMap,
            'stats' => $stats,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $chats
     *
     * @return array<string, int>
     */
    private function buildStats(array $chats): array
    {
        $total = count($chats);
        $active = 0;
        $withThreads = 0;
        $recentlyActive = 0;
        $since = (new \DateTimeImmutable())->modify('-24 hours');

        foreach ($chats as $chat) {
            if (null === ($chat['left_at'] ?? null)) {
                ++$active;
            }

            if ($chat['has_threads'] ?? false) {
                ++$withThreads;
            }

            $lastMsg = $chat['last_message_at'] ?? null;
            if ($lastMsg instanceof \DateTimeImmutable && $lastMsg > $since) {
                ++$recentlyActive;
            }
        }

        return [
            'total' => $total,
            'active' => $active,
            'with_threads' => $withThreads,
            'recently_active' => $recentlyActive,
        ];
    }
}
