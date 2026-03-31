<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\User;
use App\Telegram\Api\TelegramApiClientInterface;
use App\Telegram\Repository\TelegramBotRepository;
use App\Telegram\Service\TelegramBotRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class TelegramBotsController extends AbstractController
{
    public function __construct(
        private readonly TelegramBotRepository $botRepository,
        private readonly TelegramBotRegistry $botRegistry,
        private readonly TelegramApiClientInterface $apiClient,
    ) {
    }

    #[Route('/admin/telegram/bots', name: 'admin_telegram_bots', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $bots = $this->botRepository->findAll();

        return $this->render('admin/telegram/bots.html.twig', [
            'bots' => $bots,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/telegram/bots/new', name: 'admin_telegram_bots_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            $data = [
                'bot_username' => trim((string) $request->request->get('bot_username', '')),
                'bot_token' => trim((string) $request->request->get('bot_token', '')),
                'community_id' => trim((string) $request->request->get('community_id', '')) ?: null,
                'privacy_mode' => $request->request->get('privacy_mode', 'enabled'),
                'polling_mode' => (bool) $request->request->get('polling_mode', false),
                'enabled' => (bool) $request->request->get('enabled', true),
            ];

            if ('' === $data['bot_username'] || '' === $data['bot_token']) {
                return $this->render('admin/telegram/bot_form.html.twig', [
                    'bot' => null,
                    'error' => 'telegram_bots.error.required_fields',
                    'form_data' => $data,
                    'username' => $user->getUserIdentifier(),
                ]);
            }

            try {
                $botId = $this->botRegistry->registerBot($data);
                $this->addFlash('success', 'telegram_bots.flash.created');

                return $this->redirectToRoute('admin_telegram_bots');
            } catch (\RuntimeException $e) {
                return $this->render('admin/telegram/bot_form.html.twig', [
                    'bot' => null,
                    'error' => $e->getMessage(),
                    'form_data' => $data,
                    'username' => $user->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/telegram/bot_form.html.twig', [
            'bot' => null,
            'error' => null,
            'form_data' => [],
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/telegram/bots/{id}/edit', name: 'admin_telegram_bots_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            throw $this->createNotFoundException('Bot not found');
        }

        if ($request->isMethod('POST')) {
            $updates = [
                'bot_username' => trim((string) $request->request->get('bot_username', '')),
                'community_id' => trim((string) $request->request->get('community_id', '')) ?: null,
                'privacy_mode' => $request->request->get('privacy_mode', 'enabled'),
                'polling_mode' => (bool) $request->request->get('polling_mode', false),
                'enabled' => (bool) $request->request->get('enabled', false),
            ];

            $newToken = trim((string) $request->request->get('bot_token', ''));
            if ('' !== $newToken) {
                $updates['bot_token'] = $newToken;
            }

            $roleOverridesRaw = trim((string) $request->request->get('role_overrides', ''));
            if ('' !== $roleOverridesRaw) {
                try {
                    $updates['role_overrides'] = json_decode($roleOverridesRaw, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    return $this->render('admin/telegram/bot_form.html.twig', [
                        'bot' => $bot,
                        'error' => 'telegram_bots.error.invalid_json',
                        'form_data' => $updates,
                        'username' => $user->getUserIdentifier(),
                    ]);
                }
            }

            $this->botRegistry->updateBot($id, $updates);
            $this->addFlash('success', 'telegram_bots.flash.updated');

            return $this->redirectToRoute('admin_telegram_bots');
        }

        return $this->render('admin/telegram/bot_form.html.twig', [
            'bot' => $bot,
            'error' => null,
            'form_data' => [],
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/telegram/bots/{id}/delete', name: 'admin_telegram_bots_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            throw $this->createNotFoundException('Bot not found');
        }

        if (!$this->isCsrfTokenValid('delete_bot_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'telegram_bots.error.invalid_csrf');

            return $this->redirectToRoute('admin_telegram_bots');
        }

        $this->botRegistry->removeBot($id);
        $this->addFlash('success', 'telegram_bots.flash.deleted');

        return $this->redirectToRoute('admin_telegram_bots');
    }

    #[Route('/admin/telegram/bots/{id}/test-connection', name: 'admin_telegram_bots_test', methods: ['POST'])]
    public function testConnection(string $id): JsonResponse
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            return $this->json(['ok' => false, 'error' => 'Bot not found'], Response::HTTP_NOT_FOUND);
        }

        $result = $this->apiClient->getMe($bot['bot_token']);

        if (!($result['ok'] ?? false)) {
            return $this->json([
                'ok' => false,
                'error' => $result['description'] ?? 'Unknown error',
            ]);
        }

        return $this->json([
            'ok' => true,
            'bot' => $result['result'] ?? [],
        ]);
    }

    #[Route('/admin/telegram/bots/{id}/set-webhook', name: 'admin_telegram_bots_set_webhook', methods: ['POST'])]
    public function setWebhook(string $id, Request $request): JsonResponse
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            return $this->json(['ok' => false, 'error' => 'Bot not found'], Response::HTTP_NOT_FOUND);
        }

        $webhookUrl = $this->generateUrl(
            'api_webhook_telegram',
            ['botId' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $params = [
            'url' => $webhookUrl,
            'allowed_updates' => ['message', 'edited_message', 'callback_query', 'channel_post', 'edited_channel_post'],
            'max_connections' => 40,
        ];

        if (!empty($bot['webhook_secret'])) {
            $params['secret_token'] = $bot['webhook_secret'];
        }

        $result = $this->apiClient->setWebhook($bot['bot_token'], $params);

        if ($result['ok'] ?? false) {
            $this->botRepository->update($id, ['webhook_url' => $webhookUrl]);
        }

        return $this->json($result);
    }

    #[Route('/admin/telegram/bots/{id}/webhook-info', name: 'admin_telegram_bots_webhook_info', methods: ['GET'])]
    public function webhookInfo(string $id): JsonResponse
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            return $this->json(['ok' => false, 'error' => 'Bot not found'], Response::HTTP_NOT_FOUND);
        }

        $result = $this->apiClient->getWebhookInfo($bot['bot_token']);

        return $this->json($result);
    }
}
