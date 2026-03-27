<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway\Discovery;

use App\A2AGateway\Discovery\TraefikDiscoveryProvider;
use Codeception\Test\Unit;
use Psr\Log\NullLogger;

final class TraefikDiscoveryProviderTest extends Unit
{
    public function testDiscoverReturnsAgentServicesFromTraefikApi(): void
    {
        $services = [
            ['name' => 'knowledge-agent@docker', 'loadBalancer' => ['servers' => [['url' => 'http://knowledge-agent:80']]]],
            ['name' => 'hello-agent@docker', 'loadBalancer' => ['servers' => [['url' => 'http://hello-agent:8085']]]],
            ['name' => 'core@docker', 'loadBalancer' => ['servers' => [['url' => 'http://core:80']]]],
        ];

        $provider = new TraefikDiscoveryProvider(
            new NullLogger(),
            static fn (string $url): string => (string) json_encode($services),
        );

        $result = $provider->discover();

        self::assertCount(2, $result);
        self::assertSame('knowledge-agent', $result[0]['hostname']);
        self::assertSame(80, $result[0]['port']);
        self::assertSame('hello-agent', $result[1]['hostname']);
        self::assertSame(8085, $result[1]['port']);
    }

    public function testDiscoverReturnsEmptyWhenTraefikUnreachable(): void
    {
        $provider = new TraefikDiscoveryProvider(
            new NullLogger(),
            static fn (string $url): false => false,
        );

        self::assertSame([], $provider->discover());
    }

    public function testDiscoverReturnsEmptyOnInvalidJson(): void
    {
        $provider = new TraefikDiscoveryProvider(
            new NullLogger(),
            static fn (string $url): string => 'not-valid-json',
        );

        self::assertSame([], $provider->discover());
    }

    public function testDiscoverFallsBackToPortFromServerStatus(): void
    {
        $services = [
            [
                'name' => 'news-maker-agent@docker',
                'loadBalancer' => ['servers' => []],
                'serverStatus' => ['http://news-maker-agent:8084' => 'UP'],
            ],
        ];

        $provider = new TraefikDiscoveryProvider(
            new NullLogger(),
            static fn (string $url): string => (string) json_encode($services),
        );

        $result = $provider->discover();

        self::assertCount(1, $result);
        self::assertSame('news-maker-agent', $result[0]['hostname']);
        self::assertSame(8084, $result[0]['port']);
    }

    public function testDiscoverDefaultsToPort80WhenNoPortFound(): void
    {
        $services = [
            ['name' => 'dev-reporter-agent@docker', 'loadBalancer' => ['servers' => []]],
        ];

        $provider = new TraefikDiscoveryProvider(
            new NullLogger(),
            static fn (string $url): string => (string) json_encode($services),
        );

        $result = $provider->discover();

        self::assertCount(1, $result);
        self::assertSame(80, $result[0]['port']);
    }
}
