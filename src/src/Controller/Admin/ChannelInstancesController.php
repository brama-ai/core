<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Channel\ChannelManagerInterface;
use App\Security\User;
use App\Telegram\Repository\TelegramBotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Admin controller for channel instances (bots/integrations).
 *
 * Replaces TelegramBotsController. Channel-specific actions (test-connection,
 * set-webhook, webhook-info) are delegated through ChannelManager → agent A2A.
 */
final class ChannelInstancesController extends AbstractController
{
    public function __construct(
        private readonly TelegramBotRepository $botRepository,
        private readonly ChannelManagerInterface $channelManager,
    ) {
    }

    #[Route('/admin/channels/instances', name: 'admin_channel_instances', methods: ['GET'])]
    #[Route('/admin/telegram/bots', name: 'admin_telegram_bots', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $bots = $this->botRepository->findAll();

        return $this->render('admin/telegram/bots.html.twig', [
            'bots' => $bots,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/channels/instances/new', name: 'admin_channel_instances_new', methods: ['GET', 'POST'])]
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
                // Check if bot already exists
                $existing = $this->botRepository->findByUsername($data['bot_username']);
                if ($existing) {
                    throw new \RuntimeException(sprintf('Bot with username "%s" already exists', $data['bot_username']));
                }

                // Generate webhook secret
                $data['webhook_secret'] = bin2hex(random_bytes(32));

                $this->botRepository->create($data);
                $this->addFlash('success', 'telegram_bots.flash.created');

                return $this->redirectToRoute('admin_channel_instances');
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

    #[Route('/admin/channels/instances/{id}/edit', name: 'admin_channel_instances_edit', methods: ['GET', 'POST'])]
    #[Route('/admin/telegram/bots/{id}/edit', name: 'admin_telegram_bots_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            throw $this->createNotFoundException('Channel instance not found');
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

            $this->botRepository->update($id, $updates);
            $this->addFlash('success', 'telegram_bots.flash.updated');

            return $this->redirectToRoute('admin_channel_instances');
        }

        return $this->render('admin/telegram/bot_form.html.twig', [
            'bot' => $bot,
            'error' => null,
            'form_data' => [],
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/channels/instances/{id}/delete', name: 'admin_channel_instances_delete', methods: ['POST'])]
    #[Route('/admin/telegram/bots/{id}/delete', name: 'admin_telegram_bots_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            throw $this->createNotFoundException('Channel instance not found');
        }

        if (!$this->isCsrfTokenValid('delete_bot_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'telegram_bots.error.invalid_csrf');

            return $this->redirectToRoute('admin_channel_instances');
        }

        $this->botRepository->delete($id);
        $this->addFlash('success', 'telegram_bots.flash.deleted');

        return $this->redirectToRoute('admin_channel_instances');
    }

    #[Route('/admin/channels/instances/{id}/test-connection', name: 'admin_channel_instances_test', methods: ['POST'])]
    #[Route('/admin/telegram/bots/{id}/test-connection', name: 'admin_telegram_bots_test', methods: ['POST'])]
    public function testConnection(string $id): JsonResponse
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            return $this->json(['ok' => false, 'error' => 'Channel instance not found'], Response::HTTP_NOT_FOUND);
        }

        $channelType = (string) ($bot['channel_type'] ?? 'telegram');

        try {
            $result = $this->channelManager->adminAction($channelType, $id, 'test-connection', [
                'token' => $bot['bot_token'],
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/admin/channels/instances/{id}/set-webhook', name: 'admin_channel_instances_set_webhook', methods: ['POST'])]
    #[Route('/admin/telegram/bots/{id}/set-webhook', name: 'admin_telegram_bots_set_webhook', methods: ['POST'])]
    public function setWebhook(string $id, Request $request): JsonResponse
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            return $this->json(['ok' => false, 'error' => 'Channel instance not found'], Response::HTTP_NOT_FOUND);
        }

        $channelType = (string) ($bot['channel_type'] ?? 'telegram');

        $webhookUrl = $this->generateUrl(
            'api_webhook_channel',
            ['channelType' => $channelType, 'channelId' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $result = $this->channelManager->adminAction($channelType, $id, 'set-webhook', [
                'token' => $bot['bot_token'],
                'url' => $webhookUrl,
                'secret' => $bot['webhook_secret'] ?? null,
            ]);

            if ($result['ok'] ?? false) {
                $this->botRepository->update($id, ['webhook_url' => $webhookUrl]);
            }

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/admin/channels/instances/{id}/webhook-info', name: 'admin_channel_instances_webhook_info', methods: ['GET'])]
    #[Route('/admin/telegram/bots/{id}/webhook-info', name: 'admin_telegram_bots_webhook_info', methods: ['GET'])]
    public function webhookInfo(string $id): JsonResponse
    {
        $bot = $this->botRepository->findById($id);
        if (null === $bot) {
            return $this->json(['ok' => false, 'error' => 'Channel instance not found'], Response::HTTP_NOT_FOUND);
        }

        $channelType = (string) ($bot['channel_type'] ?? 'telegram');

        try {
            $result = $this->channelManager->adminAction($channelType, $id, 'webhook-info', [
                'token' => $bot['bot_token'],
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
