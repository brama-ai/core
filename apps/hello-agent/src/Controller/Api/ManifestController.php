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
                [
                    'id' => 'hello.greet_me',
                    'name' => 'Hello Greet Me',
                    'description' => 'Greet the message sender by their Telegram username. Use when someone asks to be greeted (e.g. "привітай мене", "greet me").',
                    'tags' => ['greeting'],
                    'examples' => ['Привітай мене', 'Greet me', 'Скажи мені привіт'],
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
                'hello.greet_me' => [
                    'description' => 'Greet the message sender by their Telegram username. Use when someone asks to be greeted.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => [
                                'type' => 'string',
                                'description' => 'Telegram username of the message sender',
                            ],
                        ],
                        'required' => ['username'],
                    ],
                ],
            ],
            'scheduled_jobs' => [
                [
                    'name' => 'daily-greeting',
                    'skill_id' => 'hello.greet',
                    'cron_expression' => '0 9 * * *',
                    'payload' => ['name' => 'Дмитро'],
                    'max_retries' => 2,
                    'retry_delay_seconds' => 120,
                    'timezone' => 'Europe/Kyiv',
                ],
            ],
            'permissions' => [],
            'commands' => ['/hello'],
            'events' => [],
            'health_url' => 'http://hello-agent/health',
        ]);
    }
}
