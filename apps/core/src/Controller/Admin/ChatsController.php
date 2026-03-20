<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Chat\ChatRepository;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ChatsController extends AbstractController
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
    ) {
    }

    #[Route('/admin/chats', name: 'admin_chats')]
    public function __invoke(#[CurrentUser] User $user, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $agent = (string) $request->query->get('agent', '');
        $status = (string) $request->query->get('status', '');

        $chats = $this->chatRepository->listChats($page, $agent, $status);
        $total = $this->chatRepository->countChats($agent, $status);
        $pageSize = ChatRepository::pageSize();

        return $this->render('admin/chats.html.twig', [
            'username' => $user->getUserIdentifier(),
            'chats' => $chats,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'pages' => (int) ceil($total / $pageSize),
            'agent_filter' => $agent,
            'status_filter' => $status,
        ]);
    }
}
