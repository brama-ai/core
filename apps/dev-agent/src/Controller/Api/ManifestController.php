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
            'name' => 'dev-agent',
            'version' => '1.0.0',
            'description' => 'Development orchestration agent — creates tasks, runs pipeline, and creates PRs',
            'url' => 'http://dev-agent/api/v1/a2a',
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
                    'id' => 'dev.create_task',
                    'name' => 'Create Development Task',
                    'description' => 'Creates a new development task with optional Opus-powered refinement.',
                    'tags' => ['development', 'task'],
                    'examples' => ['Create a task', 'New feature request'],
                ],
                [
                    'id' => 'dev.run_pipeline',
                    'name' => 'Run Pipeline',
                    'description' => 'Starts the multi-agent pipeline for a task (architect, coder, validator, tester, documenter).',
                    'tags' => ['development', 'pipeline'],
                    'examples' => ['Run pipeline for task', 'Start development'],
                ],
                [
                    'id' => 'dev.get_status',
                    'name' => 'Get Task Status',
                    'description' => 'Returns current status, logs, branch and PR URL for a task.',
                    'tags' => ['development', 'status'],
                    'examples' => ['Task status', 'Pipeline progress'],
                ],
                [
                    'id' => 'dev.list_tasks',
                    'name' => 'List Tasks',
                    'description' => 'Returns recent development tasks with optional status filter.',
                    'tags' => ['development', 'list'],
                    'examples' => ['List tasks', 'Show running pipelines'],
                ],
            ],
            'skill_schemas' => [
                'dev.create_task' => [
                    'description' => 'Creates a new development task.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Task title'],
                            'description' => ['type' => 'string', 'description' => 'Task description'],
                            'pipeline_options' => ['type' => 'object', 'description' => 'Pipeline options (skip_architect, audit)'],
                        ],
                        'required' => ['title', 'description'],
                    ],
                ],
                'dev.run_pipeline' => [
                    'description' => 'Starts pipeline execution for a task.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'task_id' => ['type' => 'integer', 'description' => 'Task ID to run'],
                        ],
                        'required' => ['task_id'],
                    ],
                ],
                'dev.get_status' => [
                    'description' => 'Returns task status and details.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'task_id' => ['type' => 'integer', 'description' => 'Task ID'],
                        ],
                        'required' => ['task_id'],
                    ],
                ],
                'dev.list_tasks' => [
                    'description' => 'Lists recent development tasks.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'status_filter' => ['type' => 'string', 'description' => 'Filter by status'],
                            'limit' => ['type' => 'integer', 'description' => 'Max results'],
                        ],
                    ],
                ],
            ],
            'permissions' => [],
            'commands' => [],
            'events' => [],
            'admin_url' => '' !== $this->adminPublicUrl ? $this->adminPublicUrl : null,
            'health_url' => 'http://dev-agent/health',
        ]);
    }
}
