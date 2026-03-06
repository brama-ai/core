<?php

declare(strict_types=1);

namespace App\Tests\Unit\Chat;

use App\Chat\ChatRepository;
use App\Chat\DTO\ChatListItem;
use App\Chat\DTO\ChatMessage;
use App\Logging\LogSearchInterface;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;

final class ChatRepositoryTest extends Unit
{
    /**
     * @param list<array<string, mixed>> $fetchAllResult
     * @param array<string, mixed>|null  $searchResult
     */
    private function buildRepo(
        array $fetchAllResult = [],
        mixed $fetchOneResult = 0,
        ?array $searchResult = null,
    ): ChatRepository {
        $connection = $this->makeEmpty(Connection::class, [
            'fetchAllAssociative' => fn (): array => $fetchAllResult,
            'fetchOne' => fn (): mixed => $fetchOneResult,
        ]);

        $indexManager = $this->makeEmpty(LogSearchInterface::class, [
            'search' => fn (): ?array => $searchResult,
        ]);

        return new ChatRepository($connection, $indexManager);
    }

    public function testListChatsReturnsItemsFromAuditTable(): void
    {
        $repo = $this->buildRepo(fetchAllResult: [
            [
                'trace_id' => 'trace_abc123',
                'agent' => 'hello-agent',
                'skill' => 'hello.greet',
                'status' => 'completed',
                'duration_ms' => 150,
                'first_at' => '2026-03-06 11:00:00',
                'last_at' => '2026-03-06 12:00:00',
                'message_count' => 3,
            ],
        ]);

        $chats = $repo->listChats();

        $this->assertCount(1, $chats);
        $this->assertInstanceOf(ChatListItem::class, $chats[0]);
        $this->assertSame('trace_abc123', $chats[0]->sessionKey);
        $this->assertSame('hello-agent', $chats[0]->agent);
        $this->assertSame('hello.greet', $chats[0]->skill);
        $this->assertSame('completed', $chats[0]->status);
        $this->assertSame(150, $chats[0]->durationMs);
        $this->assertSame(3, $chats[0]->messageCount);
        $this->assertSame(['trace_abc123'], $chats[0]->traceIds);
    }

    public function testListChatsReturnsEmptyWhenNoRows(): void
    {
        $repo = $this->buildRepo();
        $this->assertSame([], $repo->listChats());
    }

    public function testCountChatsReturnsValue(): void
    {
        $repo = $this->buildRepo(fetchOneResult: 42);
        $this->assertSame(42, $repo->countChats());
    }

    public function testCountChatsReturnsZeroWhenEmpty(): void
    {
        $repo = $this->buildRepo(fetchOneResult: 0);
        $this->assertSame(0, $repo->countChats());
    }

    public function testGetChatMessagesFromOpenSearch(): void
    {
        $repo = $this->buildRepo(searchResult: [
            'hits' => [
                'hits' => [
                    [
                        '_source' => [
                            '@timestamp' => '2026-03-06T11:00:00Z',
                            'event_name' => 'openclaw.message.received',
                            'trace_id' => 'trace_1',
                            'sender' => '123',
                            'status' => 'completed',
                            'context' => ['step_input' => ['text' => 'Hello']],
                        ],
                    ],
                    [
                        '_source' => [
                            '@timestamp' => '2026-03-06T11:00:01Z',
                            'event_name' => 'openclaw.tool.execute.started',
                            'trace_id' => 'trace_1',
                            'intent' => 'hello.greet',
                            'status' => 'started',
                            'context' => ['step_input' => ['name' => 'World']],
                        ],
                    ],
                    [
                        '_source' => [
                            '@timestamp' => '2026-03-06T11:00:02Z',
                            'event_name' => 'openclaw.message.sent',
                            'trace_id' => 'trace_1',
                            'recipient' => '123',
                            'status' => 'completed',
                            'context' => ['step_output' => ['text' => 'Hi!']],
                        ],
                    ],
                ],
            ],
        ]);

        $messages = $repo->getChatMessages('trace_1');

        $this->assertCount(3, $messages);

        $this->assertInstanceOf(ChatMessage::class, $messages[0]);
        $this->assertSame('inbound', $messages[0]->direction);
        $this->assertSame('123', $messages[0]->sender);
        $this->assertSame(['text' => 'Hello'], $messages[0]->payload);

        $this->assertSame('tool_call', $messages[1]->direction);
        $this->assertSame('hello.greet', $messages[1]->tool);

        $this->assertSame('outbound', $messages[2]->direction);
        $this->assertSame('123', $messages[2]->recipient);
    }

    public function testGetChatMessagesFallsBackToAudit(): void
    {
        $repo = $this->buildRepo(
            fetchAllResult: [
                [
                    'skill' => 'hello.greet',
                    'agent' => 'hello-agent',
                    'trace_id' => 'trace_abc',
                    'request_id' => 'req_001',
                    'status' => 'completed',
                    'duration_ms' => 200,
                    'http_status_code' => 200,
                    'error_code' => null,
                    'actor' => 'openclaw',
                    'created_at' => '2026-03-06 11:00:00',
                ],
            ],
            searchResult: null,
        );

        $messages = $repo->getChatMessages('trace_abc');

        $this->assertCount(1, $messages);
        $this->assertSame('tool_call', $messages[0]->direction);
        $this->assertSame('a2a.audit', $messages[0]->eventName);
        $this->assertSame('hello.greet', $messages[0]->tool);
        $this->assertSame('openclaw', $messages[0]->sender);
        $this->assertSame('hello-agent', $messages[0]->recipient);
        $this->assertSame(200, $messages[0]->durationMs);
    }

    public function testGetChatMessagesReturnsEmptyWhenBothSourcesEmpty(): void
    {
        $repo = $this->buildRepo(searchResult: ['hits' => ['hits' => []]]);
        $this->assertSame([], $repo->getChatMessages('any-trace'));
    }

    public function testGetTraceIdsForChat(): void
    {
        $repo = $this->buildRepo();
        $this->assertSame(['trace_xyz'], $repo->getTraceIdsForChat('trace_xyz'));
    }

    public function testPageSizeConstant(): void
    {
        $this->assertSame(20, ChatRepository::pageSize());
    }
}
