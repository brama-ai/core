<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\AgentRegistry\AgentRegistryInterface;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AgentSettingsController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
    ) {
    }

    #[Route('/admin/agents/{name}/settings', name: 'admin_agent_settings')]
    public function __invoke(string $name, #[CurrentUser] User $user): Response
    {
        $agent = $this->registry->findByName($name);

        if (null === $agent) {
            throw new NotFoundHttpException(sprintf('Agent "%s" not found.', $name));
        }

        $manifest = is_string($agent['manifest'])
            ? json_decode($agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
            : $agent['manifest'];

        $config = is_string($agent['config'])
            ? json_decode($agent['config'], true, 512, JSON_THROW_ON_ERROR)
            : ($agent['config'] ?? []);

        $hasOwnStorage = isset($manifest['storage']) && is_array($manifest['storage']);
        $canTriggerNewsCrawl = 'news-maker-agent' === $name && null !== ($agent['installed_at'] ?? null);

        return $this->render('admin/agent_settings.html.twig', [
            'username' => $user->getUserIdentifier(),
            'agent_name' => $name,
            'agent' => $agent,
            'manifest' => $manifest,
            'config' => $config,
            'admin_url' => $manifest['admin_url'] ?? null,
            'has_own_storage' => $hasOwnStorage,
            'installed_at' => $agent['installed_at'] ?? null,
            'can_trigger_news_crawl' => $canTriggerNewsCrawl,
        ]);
    }
}
