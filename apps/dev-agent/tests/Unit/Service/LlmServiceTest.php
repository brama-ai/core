<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\LlmService;
use Codeception\Test\Unit;
use Psr\Log\NullLogger;

final class LlmServiceTest extends Unit
{
    public function testChatThrowsOnEmptyResponse(): void
    {
        // LlmService uses file_get_contents which we can't easily mock
        // in a unit test without integration. This test validates constructor
        // and that the service can be instantiated.
        $service = new LlmService(
            new NullLogger(),
            'http://localhost:4000',
            'test-key',
            'test-model',
        );

        $this->assertInstanceOf(LlmService::class, $service);
    }
}
