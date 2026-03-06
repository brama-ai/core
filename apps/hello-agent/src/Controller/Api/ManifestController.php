<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ManifestController extends AbstractController
{
    #[Route('/api/v1/manifest', name: 'api_manifest', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'name' => 'hello-agent',
            'version' => '1.0.0',
            'description' => 'Simple hello-world reference agent',
            'url' => 'http://hello-agent/api/v1/a2a',
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
                    'id' => 'hello.greet',
                    'name' => 'Hello Greet',
                    'description' => 'Greet a user by name. Returns a friendly hello message.',
                    'tags' => ['greeting'],
                    'examples' => ['Greet John', 'Say hello to Maria'],
                ],
            ],
            'skill_schemas' => [
                'hello.greet' => [
                    'description' => 'Greet a user by name. Returns a friendly hello message.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Name of the person to greet',
                            ],
                        ],
                    ],
                ],
            ],
            'permissions' => [],
            'commands' => ['/hello'],
            'events' => [],
            'health_url' => 'http://hello-agent/health',
        ]);
    }
}
