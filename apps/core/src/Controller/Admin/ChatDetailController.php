<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Chat\ChatRepository;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ChatDetailController extends AbstractController
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
    ) {
    }

    #[Route('/admin/chats/{traceId}', name: 'admin_chat_detail', requirements: ['traceId' => '.+'])]
    public function __invoke(#[CurrentUser] User $user, string $traceId): Response
    {
        $messages = $this->chatRepository->getChatMessages($traceId);
        $traceIds = $this->chatRepository->getTraceIdsForChat($traceId);

        return $this->render('admin/chat_detail.html.twig', [
            'username' => $user->getUserIdentifier(),
            'trace_id' => $traceId,
            'messages' => $messages,
            'trace_ids' => $traceIds,
        ]);
    }
}
