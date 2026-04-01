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
 * Channel-specific actions (test-connection, set-webhook, webhook-info)
 * are delegated through ChannelManager → agent A2A.
 */
final class ChannelInstancesController extends AbstractController
{
    public function __construct(
        private readonly TelegramBotRepository $botRepository,
        private readonly ChannelManagerInterface $channelManager,
    ) {
    }

    #[Route('/admin/channels/instances', name: 'admin_channel_instances', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $instances = $this->botRepository->findAll();

        return $this->render('admin/channels/instances.html.twig', [
            'instances' => $instances,
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/channels/instances/new', name: 'admin_channel_instances_new', methods: ['GET', 'POST'])]
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
                return $this->render('admin/channels/instance_form.html.twig', [
                    'instance' => null,
                    'error' => 'channels.error.required_fields',
                    'form_data' => $data,
                    'username' => $user->getUserIdentifier(),
                ]);
            }

            try {
                $existing = $this->botRepository->findByUsername($data['bot_username']);
                if ($existing) {
                    throw new \RuntimeException(sprintf('Channel instance with username "%s" already exists', $data['bot_username']));
                }

                $data['webhook_secret'] = bin2hex(random_bytes(32));

                $this->botRepository->create($data);
                $this->addFlash('success', 'channels.flash.created');

                return $this->redirectToRoute('admin_channel_instances');
            } catch (\RuntimeException $e) {
                return $this->render('admin/channels/instance_form.html.twig', [
                    'instance' => null,
                    'error' => $e->getMessage(),
                    'form_data' => $data,
                    'username' => $user->getUserIdentifier(),
                ]);
            }
        }

        return $this->render('admin/channels/instance_form.html.twig', [
            'instance' => null,
            'error' => null,
            'form_data' => [],
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/channels/instances/{id}/edit', name: 'admin_channel_instances_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        $instance = $this->botRepository->findById($id);
        if (null === $instance) {
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
                    return $this->render('admin/channels/instance_form.html.twig', [
                        'instance' => $instance,
                        'error' => 'channels.error.invalid_json',
                        'form_data' => $updates,
                        'username' => $user->getUserIdentifier(),
                    ]);
                }
            }

            $this->botRepository->update($id, $updates);
            $this->addFlash('success', 'channels.flash.updated');

            return $this->redirectToRoute('admin_channel_instances');
        }

        return $this->render('admin/channels/instance_form.html.twig', [
            'instance' => $instance,
            'error' => null,
            'form_data' => [],
            'username' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/admin/channels/instances/{id}/delete', name: 'admin_channel_instances_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $instance = $this->botRepository->findById($id);
        if (null === $instance) {
            throw $this->createNotFoundException('Channel instance not found');
        }

        if (!$this->isCsrfTokenValid('delete_bot_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'channels.error.invalid_csrf');

            return $this->redirectToRoute('admin_channel_instances');
        }

        $this->botRepository->delete($id);
        $this->addFlash('success', 'channels.flash.deleted');

        return $this->redirectToRoute('admin_channel_instances');
    }

    #[Route('/admin/channels/instances/{id}/test-connection', name: 'admin_channel_instances_test', methods: ['POST'])]
    public function testConnection(string $id): JsonResponse
    {
        $instance = $this->botRepository->findById($id);
        if (null === $instance) {
            return $this->json(['ok' => false, 'error' => 'Channel instance not found'], Response::HTTP_NOT_FOUND);
        }

        $channelType = (string) ($instance['channel_type'] ?? 'telegram');

        try {
            $result = $this->channelManager->adminAction($channelType, $id, 'test-connection', [
                'token' => $instance['bot_token'],
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/admin/channels/instances/{id}/set-webhook', name: 'admin_channel_instances_set_webhook', methods: ['POST'])]
    public function setWebhook(string $id, Request $request): JsonResponse
    {
        $instance = $this->botRepository->findById($id);
        if (null === $instance) {
            return $this->json(['ok' => false, 'error' => 'Channel instance not found'], Response::HTTP_NOT_FOUND);
        }

        $channelType = (string) ($instance['channel_type'] ?? 'telegram');

        $webhookUrl = $this->generateUrl(
            'api_webhook_channel',
            ['channelType' => $channelType, 'channelId' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $result = $this->channelManager->adminAction($channelType, $id, 'set-webhook', [
                'token' => $instance['bot_token'],
                'url' => $webhookUrl,
                'secret' => $instance['webhook_secret'] ?? null,
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
    public function webhookInfo(string $id): JsonResponse
    {
        $instance = $this->botRepository->findById($id);
        if (null === $instance) {
            return $this->json(['ok' => false, 'error' => 'Channel instance not found'], Response::HTTP_NOT_FOUND);
        }

        $channelType = (string) ($instance['channel_type'] ?? 'telegram');

        try {
            $result = $this->channelManager->adminAction($channelType, $id, 'webhook-info', [
                'token' => $instance['bot_token'],
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
