<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway\Discovery;

use App\A2AGateway\Discovery\KubernetesDiscoveryProvider;
use Codeception\Test\Unit;
use Psr\Log\NullLogger;

final class KubernetesDiscoveryProviderTest extends Unit
{
    public function testDiscoverReturnsAgentServicesFromKubernetesApi(): void
    {
        $provider = new KubernetesDiscoveryProvider(
            new NullLogger(),
            static function (string $path): string|false {
                if (KubernetesDiscoveryProvider::SERVICE_ACCOUNT_TOKEN_PATH === $path) {
                    return 'k8s-token';
                }

                return '/var/run/secrets/kubernetes.io/serviceaccount/namespace' === $path
                    ? 'agents-namespace'
                    : false;
            },
            static function (string $url, string $token): array {
                self::assertSame('k8s-token', $token);
                self::assertStringContainsString('/api/v1/namespaces/agents-namespace/services', $url);
                self::assertStringContainsString('labelSelector=ai.platform.agent%3Dtrue', $url);

                return [
                    'status' => 200,
                    'body' => (string) json_encode([
                        'items' => [
                            [
                                'metadata' => ['name' => 'knowledge-agent'],
                                'spec' => [
                                    'ports' => [
                                        ['name' => 'http', 'port' => 8083],
                                    ],
                                ],
                            ],
                            [
                                'metadata' => ['name' => 'hello-agent'],
                                'spec' => [
                                    'ports' => [
                                        ['name' => 'custom', 'port' => 8085],
                                    ],
                                ],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            },
        );

        $result = $provider->discover();

        $this->assertSame([
            ['hostname' => 'knowledge-agent.agents-namespace.svc.cluster.local', 'port' => 8083],
            ['hostname' => 'hello-agent.agents-namespace.svc.cluster.local', 'port' => 8085],
        ], $result);
    }

    public function testDiscoverReturnsEmptyWhenCredentialsAreMissing(): void
    {
        $provider = new KubernetesDiscoveryProvider(
            new NullLogger(),
            static fn (): string|false => false,
            static function (): array {
                self::fail('Kubernetes API request should not run when credentials are missing.');

                return ['status' => 200, 'body' => ''];
            },
        );

        $this->assertSame([], $provider->discover());
    }

    public function testDiscoverReturnsEmptyForNonSuccessStatusCode(): void
    {
        $provider = new KubernetesDiscoveryProvider(
            new NullLogger(),
            static function (string $path): string|false {
                if (KubernetesDiscoveryProvider::SERVICE_ACCOUNT_TOKEN_PATH === $path) {
                    return 'k8s-token';
                }

                return '/var/run/secrets/kubernetes.io/serviceaccount/namespace' === $path
                    ? 'agents-namespace'
                    : false;
            },
            static fn (): array => ['status' => 403, 'body' => '{"kind":"Status"}'],
        );

        $this->assertSame([], $provider->discover());
    }

    public function testDiscoverPrefersHttpNamedPort(): void
    {
        $provider = new KubernetesDiscoveryProvider(
            new NullLogger(),
            static function (string $path): string|false {
                if (KubernetesDiscoveryProvider::SERVICE_ACCOUNT_TOKEN_PATH === $path) {
                    return 'k8s-token';
                }

                return '/var/run/secrets/kubernetes.io/serviceaccount/namespace' === $path
                    ? 'agents-namespace'
                    : false;
            },
            static fn (): array => [
                'status' => 200,
                'body' => (string) json_encode([
                    'items' => [
                        [
                            'metadata' => ['name' => 'multi-port-agent'],
                            'spec' => [
                                'ports' => [
                                    ['name' => 'metrics', 'port' => 9090],
                                    ['name' => 'http', 'port' => 8080],
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        );

        $result = $provider->discover();

        $this->assertSame([
            ['hostname' => 'multi-port-agent.agents-namespace.svc.cluster.local', 'port' => 8080],
        ], $result);
    }
}
