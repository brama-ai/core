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
 */
final class ChannelConversationsController extends AbstractController
{
    public function __construct(
        private readonly TelegramChatRepository $chatRepository,
        private readonly TelegramBotRepository $botRepository,
    ) {
    }

    #[Route('/admin/channels/conversations', name: 'admin_channel_conversations', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $conversations = $this->chatRepository->findAll();
        $instances = $this->botRepository->findAll();

        $instanceMap = [];
        foreach ($instances as $instance) {
            $instanceMap[$instance['id']] = $instance;
        }

        $stats = $this->buildStats($conversations);

        return $this->render('admin/channels/conversations.html.twig', [
            'conversations' => $conversations,
            'instance_map' => $instanceMap,
            'stats' => $stats,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $conversations
     *
     * @return array<string, int>
     */
    private function buildStats(array $conversations): array
    {
        $total = count($conversations);
        $active = 0;
        $withThreads = 0;
        $recentlyActive = 0;
        $since = (new \DateTimeImmutable())->modify('-24 hours');

        foreach ($conversations as $conv) {
            if (null === ($conv['left_at'] ?? null)) {
                ++$active;
            }

            if ($conv['has_threads'] ?? false) {
                ++$withThreads;
            }

            $lastMsg = $conv['last_message_at'] ?? null;
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
