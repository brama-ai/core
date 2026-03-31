<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Delivery;

use App\Telegram\Delivery\DeliveryPayload;
use App\Telegram\Delivery\DeliveryTarget;
use App\Telegram\Delivery\TelegramDeliveryAdapter;
use App\Telegram\Service\TelegramSenderInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class TelegramDeliveryAdapterTest extends Unit
{
    private TelegramSenderInterface&MockObject $sender;
    private TelegramDeliveryAdapter $adapter;

    protected function setUp(): void
    {
        $this->sender = $this->createMock(TelegramSenderInterface::class);
        $this->adapter = new TelegramDeliveryAdapter($this->sender, new NullLogger());
    }

    // -------------------------------------------------------------------------
    // supports()
    // -------------------------------------------------------------------------

    public function testSupportsTelegramType(): void
    {
        $this->assertTrue($this->adapter->supports('telegram'));
    }

    public function testDoesNotSupportOtherTypes(): void
    {
        $this->assertFalse($this->adapter->supports('email'));
        $this->assertFalse($this->adapter->supports('slack'));
        $this->assertFalse($this->adapter->supports(''));
    }

    // -------------------------------------------------------------------------
    // send() — address parsing
    // -------------------------------------------------------------------------

    public function testSendParsesSimpleChatIdAddress(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                '12345',
                'Hello',
                $this->callback(static fn (array $opts): bool => !isset($opts['thread_id'])),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 99]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '12345'),
            text: 'Hello',
        );

        $result = $this->adapter->send($payload);

        $this->assertTrue($result->success);
        $this->assertSame('99', $result->externalMessageId);
    }

    public function testSendParsesChatIdWithThreadId(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                '12345',
                'Hello thread',
                $this->callback(static fn (array $opts): bool => isset($opts['thread_id']) && 42 === $opts['thread_id']),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 7]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '12345:42'),
            text: 'Hello thread',
        );

        $result = $this->adapter->send($payload);

        $this->assertTrue($result->success);
        $this->assertSame('7', $result->externalMessageId);
    }

    // -------------------------------------------------------------------------
    // send() — content_type → parse_mode mapping
    // -------------------------------------------------------------------------

    public function testSendUsesMarkdownV2ForMarkdownContentType(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                '100',
                'Bold *text*',
                $this->callback(static fn (array $opts): bool => isset($opts['parse_mode']) && 'MarkdownV2' === $opts['parse_mode']),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 1]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Bold *text*',
            contentType: 'markdown',
        );

        $this->adapter->send($payload);
    }

    public function testSendUsesHtmlForCardContentType(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                '100',
                '<b>Card</b>',
                $this->callback(static fn (array $opts): bool => isset($opts['parse_mode']) && 'HTML' === $opts['parse_mode']),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 2]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: '<b>Card</b>',
            contentType: 'card',
        );

        $this->adapter->send($payload);
    }

    public function testSendOmitsParseModeForTextContentType(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                '100',
                'Plain text',
                $this->callback(static fn (array $opts): bool => !isset($opts['parse_mode'])),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 3]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Plain text',
            contentType: 'text',
        );

        $this->adapter->send($payload);
    }

    public function testSendOmitsParseModeForUnknownContentType(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                '100',
                'Some text',
                $this->callback(static fn (array $opts): bool => !isset($opts['parse_mode'])),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 4]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Some text',
            contentType: 'unknown',
        );

        $this->adapter->send($payload);
    }

    // -------------------------------------------------------------------------
    // send() — DeliveryResult
    // -------------------------------------------------------------------------

    public function testSendReturnsSuccessResultWithMessageId(): void
    {
        $this->sender->method('send')
            ->willReturn(['ok' => true, 'result' => ['message_id' => 555]]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Hello',
        );

        $result = $this->adapter->send($payload);

        $this->assertTrue($result->success);
        $this->assertSame('555', $result->externalMessageId);
        $this->assertNull($result->errorMessage);
    }

    public function testSendReturnsFailureResultWhenApiReturnsFalse(): void
    {
        $this->sender->method('send')
            ->willReturn(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user']);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Hello',
        );

        $result = $this->adapter->send($payload);

        $this->assertFalse($result->success);
        $this->assertNull($result->externalMessageId);
        $this->assertSame('Forbidden: bot was blocked by the user', $result->errorMessage);
    }

    public function testSendReturnsFailureWithDefaultMessageWhenDescriptionMissing(): void
    {
        $this->sender->method('send')
            ->willReturn(['ok' => false]);

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Hello',
        );

        $result = $this->adapter->send($payload);

        $this->assertFalse($result->success);
        $this->assertSame('Unknown error', $result->errorMessage);
    }

    // -------------------------------------------------------------------------
    // send() — exception handling
    // -------------------------------------------------------------------------

    public function testSendReturnsFailureOnNetworkException(): void
    {
        $this->sender->method('send')
            ->willThrowException(new \RuntimeException('Connection timed out'));

        $payload = new DeliveryPayload(
            botId: 'bot-1',
            target: new DeliveryTarget(address: '100'),
            text: 'Hello',
        );

        $result = $this->adapter->send($payload);

        $this->assertFalse($result->success);
        $this->assertNull($result->externalMessageId);
        $this->assertSame('Connection timed out', $result->errorMessage);
    }

    // -------------------------------------------------------------------------
    // send() — thread_id + parse_mode combined
    // -------------------------------------------------------------------------

    public function testSendPassesBothThreadIdAndParseModeWhenBothPresent(): void
    {
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-2',
                '999',
                'Rich message',
                $this->callback(static function (array $opts): bool {
                    return isset($opts['thread_id'])
                        && 10 === $opts['thread_id']
                        && isset($opts['parse_mode'])
                        && 'HTML' === $opts['parse_mode'];
                }),
            )
            ->willReturn(['ok' => true, 'result' => ['message_id' => 88]]);

        $payload = new DeliveryPayload(
            botId: 'bot-2',
            target: new DeliveryTarget(address: '999:10'),
            text: 'Rich message',
            contentType: 'card',
        );

        $result = $this->adapter->send($payload);

        $this->assertTrue($result->success);
    }
}
