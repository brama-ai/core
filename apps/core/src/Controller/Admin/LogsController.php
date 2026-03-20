<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Logging\LogIndexManager;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LogsController extends AbstractController
{
    private const PAGE_SIZE = 50;
    private const LEVELS = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];
    private const APPS = ['core', 'knowledge-agent', 'hello-agent', 'news-maker-agent'];

    /** @var array<string, array{label: string, prefixes?: list<string>, terms?: list<string>, term?: array<string, string>}> */
    private const CATEGORIES = [
        'chat' => ['label' => 'Чати (A2A)', 'prefixes' => ['core.invoke.', 'core.a2a.', 'hello.a2a.']],
        'chat_start' => ['label' => 'Початок чатів', 'terms' => ['core.invoke.received']],
        'llm' => ['label' => 'LLM виклики', 'prefixes' => ['hello.llm.']],
        'intent' => ['label' => 'Обробка намірів', 'prefixes' => ['hello.intent.']],
        'discovery' => ['label' => 'Виявлення агентів', 'prefixes' => ['core.discovery.', 'core.agent_card.']],
        'observability' => ['label' => 'Спостережуваність', 'prefixes' => ['hello.langfuse.']],
        'error' => ['label' => 'Помилки', 'term' => ['status' => 'failed']],
    ];

    public function __construct(
        private readonly LogIndexManager $indexManager,
    ) {
    }

    #[Route('/admin/logs', name: 'admin_logs')]
    public function __invoke(#[CurrentUser] User $user, Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $level = $request->query->get('level', '');
        $app = $request->query->get('app', '');
        $category = $request->query->get('category', '');
        $dateFrom = $request->query->get('from', '');
        $dateTo = $request->query->get('to', '');
        $page = max(1, $request->query->getInt('page', 1));

        $searchBody = $this->buildSearchQuery($query, (string) $level, (string) $app, (string) $category, (string) $dateFrom, (string) $dateTo, $page);
        $result = $this->indexManager->search($searchBody);

        $hits = [];
        $total = 0;

        if (null !== $result) {
            $total = $result['hits']['total']['value'] ?? 0;
            /** @var list<array{_source: array<string, mixed>}> $rawHits */
            $rawHits = $result['hits']['hits'] ?? [];
            foreach ($rawHits as $hit) {
                $hits[] = $hit['_source'];
            }
        }

        return $this->render('admin/logs.html.twig', [
            'username' => $user->getUserIdentifier(),
            'hits' => $hits,
            'total' => $total,
            'page' => $page,
            'page_size' => self::PAGE_SIZE,
            'pages' => (int) ceil($total / self::PAGE_SIZE),
            'q' => $query,
            'level' => $level,
            'app_filter' => $app,
            'category' => $category,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'levels' => self::LEVELS,
            'apps' => self::APPS,
            'categories' => self::CATEGORIES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSearchQuery(string $query, string $level, string $app, string $category, string $dateFrom, string $dateTo, int $page): array
    {
        $must = [];
        $filter = [];

        if ('' !== $query) {
            $must[] = ['multi_match' => [
                'query' => $query,
                'fields' => [
                    'message',
                    'exception.message',
                    'trace_id',
                    'request_id',
                    'request_uri',
                    'event_name',
                    'step',
                    'tool',
                    'intent',
                    'error_code',
                ],
                'type' => 'phrase_prefix',
            ]];
        }

        if ('' !== $level) {
            $filter[] = ['term' => ['level_name' => $level]];
        }

        if ('' !== $app) {
            $filter[] = ['term' => ['app_name' => $app]];
        }

        if ('' !== $category && isset(self::CATEGORIES[$category])) {
            $catDef = self::CATEGORIES[$category];

            $filter[] = $this->buildCategoryFilter($catDef);
        }

        $range = [];
        if ('' !== $dateFrom) {
            $range['gte'] = $dateFrom.'T00:00:00Z';
        }
        if ('' !== $dateTo) {
            $range['lte'] = $dateTo.'T23:59:59Z';
        }
        if ([] !== $range) {
            $filter[] = ['range' => ['@timestamp' => $range]];
        }

        $body = [
            'query' => [
                'bool' => [],
            ],
            'sort' => [['@timestamp' => 'desc']],
            'from' => ($page - 1) * self::PAGE_SIZE,
            'size' => self::PAGE_SIZE,
        ];

        if ([] !== $must) {
            $body['query']['bool']['must'] = $must;
        }
        if ([] !== $filter) {
            $body['query']['bool']['filter'] = $filter;
        }
        if ([] === $must && [] === $filter) {
            $body['query'] = ['match_all' => (object) []];
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $catDef
     *
     * @return array<string, mixed>
     */
    private function buildCategoryFilter(array $catDef): array
    {
        if (isset($catDef['prefixes']) && \is_array($catDef['prefixes'])) {
            $should = [];
            foreach ($catDef['prefixes'] as $prefix) {
                $should[] = ['prefix' => ['event_name' => $prefix]];
            }

            return ['bool' => ['should' => $should, 'minimum_should_match' => 1]];
        }

        if (isset($catDef['terms']) && \is_array($catDef['terms'])) {
            return ['terms' => ['event_name' => $catDef['terms']]];
        }

        if (isset($catDef['term']) && \is_array($catDef['term'])) {
            return ['term' => $catDef['term']];
        }

        return ['match_all' => (object) []];
    }
}
