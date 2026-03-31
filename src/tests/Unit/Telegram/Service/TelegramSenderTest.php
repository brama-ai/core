<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Service;

use App\Telegram\Api\TelegramApiClientInterface;
use App\Telegram\Service\TelegramBotRegistry;
use App\Telegram\Service\TelegramSender;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class TelegramSenderTest extends Unit
{
    private TelegramApiClientInterface&MockObject $apiClient;
    private TelegramBotRegistry&MockObject $botRegistry;
    private TelegramSender $sender;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(TelegramApiClientInterface::class);
        $this->botRegistry = $this->createMock(TelegramBotRegistry::class);
        $this->sender = new TelegramSender($this->apiClient, $this->botRegistry, new NullLogger());
    }

    public function testSendReturnsBotNotFoundWhenBotMissing(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->with('bot-1')
            ->willReturn(null);

        $this->apiClient->expects($this->never())->method('sendMessage');

        $result = $this->sender->send('bot-1', 'chat-1', 'Hello');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Bot not found', (string) $result['description']);
    }

    public function testSendCallsApiClientWithCorrectParams(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'test-token-123']);

        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->with(
                'test-token-123',
                $this->callback(static function (array $params): bool {
                    return 'chat-1' === $params['chat_id']
                        && 'Hello world' === $params['text'];
                }),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 1]]);

        $result = $this->sender->send('bot-1', 'chat-1', 'Hello world');

        $this->assertTrue($result['ok']);
    }

    public function testSendIncludesThreadIdWhenProvided(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->with(
                'token',
                $this->callback(static fn (array $p): bool => isset($p['message_thread_id']) && 42 === $p['message_thread_id']),
            )
            ->willReturn(['ok' => true]);

        $this->sender->send('bot-1', 'chat-1', 'Hello', ['thread_id' => 42]);
    }

    public function testSendIncludesReplyParametersWhenReplyToMessageIdProvided(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->with(
                'token',
                $this->callback(static function (array $p): bool {
                    return isset($p['reply_parameters'])
                        && 99 === $p['reply_parameters']['message_id'];
                }),
            )
            ->willReturn(['ok' => true]);

        $this->sender->send('bot-1', 'chat-1', 'Hello', ['reply_to_message_id' => 99]);
    }

    public function testSendIncludesParseModeWhenProvided(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->with(
                'token',
                $this->callback(static fn (array $p): bool => isset($p['parse_mode']) && 'HTML' === $p['parse_mode']),
            )
            ->willReturn(['ok' => true]);

        $this->sender->send('bot-1', 'chat-1', 'Hello', ['parse_mode' => 'HTML']);
    }

    public function testSendFallsBackToHtmlWhenMarkdownV2Fails(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnOnConsecutiveCalls(
                ['ok' => false, 'description' => 'Bad Request: can\'t parse entities'],
                ['ok' => true, 'result' => ['message_id' => 5]],
            );

        $result = $this->sender->send('bot-1', 'chat-1', 'Hello *world*', ['parse_mode' => 'MarkdownV2']);

        $this->assertTrue($result['ok']);
    }

    public function testSendSplitsLongMessageIntoMultipleMessages(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        // Build a text longer than 4096 chars
        $longText = str_repeat('A', 4097);

        $this->apiClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturn(['ok' => true, 'result' => ['message_id' => 1]]);

        $result = $this->sender->send('bot-1', 'chat-1', $longText);

        $this->assertTrue($result['ok']);
    }

    public function testSendSplitsAtParagraphBoundary(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        // Build text with paragraph boundary near the 4096 limit
        $part1 = str_repeat('A', 3000);
        $part2 = str_repeat('B', 3000);
        $longText = $part1."\n\n".$part2;

        $capturedChunks = [];
        $this->apiClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(static function (string $token, array $params) use (&$capturedChunks): array {
                $capturedChunks[] = $params['text'];

                return ['ok' => true, 'result' => ['message_id' => 1]];
            });

        $this->sender->send('bot-1', 'chat-1', $longText);

        // First chunk should end at paragraph boundary
        $this->assertCount(2, $capturedChunks);
        $this->assertStringEndsWith($part1, $capturedChunks[0]);
    }

    public function testSendSplitsAtSentenceBoundaryWhenNoParagraph(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        // Build text with sentence boundary near the 4096 limit
        $part1 = str_repeat('A', 3000).'. ';
        $part2 = str_repeat('B', 3000);
        $longText = $part1.$part2;

        $capturedChunks = [];
        $this->apiClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(static function (string $token, array $params) use (&$capturedChunks): array {
                $capturedChunks[] = $params['text'];

                return ['ok' => true, 'result' => ['message_id' => 1]];
            });

        $this->sender->send('bot-1', 'chat-1', $longText);

        $this->assertCount(2, $capturedChunks);
    }

    public function testSendSplitStopsOnFirstFailure(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $longText = str_repeat('A', 4097);

        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->willReturn(['ok' => false, 'description' => 'Forbidden']);

        $result = $this->sender->send('bot-1', 'chat-1', $longText);

        $this->assertFalse($result['ok']);
    }

    public function testSendSplitOnlyFirstChunkHasReplyToMessageId(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $longText = str_repeat('A', 4097);

        $capturedParams = [];
        $this->apiClient->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(static function (string $token, array $params) use (&$capturedParams): array {
                $capturedParams[] = $params;

                return ['ok' => true, 'result' => ['message_id' => 1]];
            });

        $this->sender->send('bot-1', 'chat-1', $longText, ['reply_to_message_id' => 10]);

        $this->assertArrayHasKey('reply_parameters', $capturedParams[0]);
        $this->assertArrayNotHasKey('reply_parameters', $capturedParams[1]);
    }

    public function testSendShortMessageDoesNotSplit(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->willReturn(['ok' => true]);

        $this->sender->send('bot-1', 'chat-1', 'Short message');
    }

    public function testSendPhotoReturnsBotNotFoundWhenBotMissing(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(null);

        $result = $this->sender->sendPhoto('bot-1', 'chat-1', 'photo-url');

        $this->assertFalse($result['ok']);
    }

    public function testSendPhotoTruncatesCaptionOver1024Chars(): void
    {
        $this->botRegistry->expects($this->atLeastOnce())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $longCaption = str_repeat('C', 1025);

        $this->apiClient->expects($this->once())
            ->method('sendPhoto')
            ->with(
                'token',
                $this->callback(static function (array $p): bool {
                    return isset($p['caption']) && 1024 === mb_strlen($p['caption']);
                }),
            )
            ->willReturn(['ok' => true]);

        // Remaining text is sent as follow-up
        $this->apiClient->expects($this->once())
            ->method('sendMessage')
            ->willReturn(['ok' => true]);

        $this->sender->sendPhoto('bot-1', 'chat-1', 'photo-url', $longCaption);
    }

    public function testSendPhotoDoesNotSendFollowUpWhenCaptionFits(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->once())
            ->method('sendPhoto')
            ->willReturn(['ok' => true]);

        $this->apiClient->expects($this->never())
            ->method('sendMessage');

        $this->sender->sendPhoto('bot-1', 'chat-1', 'photo-url', 'Short caption');
    }

    public function testAnswerCallbackQueryReturnsBotNotFoundWhenBotMissing(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(null);

        $result = $this->sender->answerCallbackQuery('bot-1', 'cbq-id');

        $this->assertFalse($result['ok']);
    }

    public function testAnswerCallbackQueryCallsApiWithCorrectParams(): void
    {
        $this->botRegistry->expects($this->once())
            ->method('getBot')
            ->willReturn(['bot_token' => 'token']);

        $this->apiClient->expects($this->once())
            ->method('answerCallbackQuery')
            ->with(
                'token',
                $this->callback(static function (array $p): bool {
                    return 'cbq-123' === $p['callback_query_id']
                        && 'Done!' === $p['text']
                        && true === $p['show_alert'];
                }),
            )
            ->willReturn(['ok' => true]);

        $this->sender->answerCallbackQuery('bot-1', 'cbq-123', 'Done!', true);
    }
}
