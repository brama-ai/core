<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ManifestController extends AbstractController
{
    public function __construct(
        private readonly string $adminPublicUrl,
    ) {
    }

    #[Route('/api/v1/manifest', name: 'api_manifest', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'name' => 'knowledge-agent',
            'version' => '1.0.0',
            'description' => 'Knowledge base management and semantic search',
            'url' => 'http://knowledge-agent/api/v1/knowledge/a2a',
            'provider' => [
                'organization' => 'AI Community Platform',
                'url' => 'https://github.com/nmdimas/ai-community-platform',
            ],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'skills' => [
                [
                    'id' => 'knowledge.search',
                    'name' => 'Knowledge Search',
                    'description' => 'Search the knowledge base using semantic and hybrid search',
                    'tags' => ['search', 'knowledge'],
                ],
                [
                    'id' => 'knowledge.upload',
                    'name' => 'Knowledge Upload',
                    'description' => 'Extract and store knowledge from messages',
                    'tags' => ['upload', 'knowledge'],
                ],
            ],
            'permissions' => ['admin', 'moderator'],
            'commands' => ['/wiki', '/knowledge'],
            'events' => ['message.created'],
            'health_url' => 'http://knowledge-agent/health',
            'admin_url' => $this->adminPublicUrl,
            'storage' => [
                'postgres' => [
                    'db_name' => 'knowledge_agent',
                    'user' => 'knowledge_agent',
                    'password' => 'knowledge_agent',
                ],
                'redis' => [
                    'db_number' => 1,
                ],
                'opensearch' => [
                    'collections' => ['knowledge_entries'],
                ],
            ],
        ]);
    }
}
