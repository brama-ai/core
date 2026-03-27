<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway\Discovery;

use App\A2AGateway\Discovery\AgentDiscoveryProviderFactory;
use App\A2AGateway\Discovery\KubernetesDiscoveryProvider;
use App\A2AGateway\Discovery\TraefikDiscoveryProvider;
use Codeception\Test\Unit;
use Psr\Log\NullLogger;

/**
 * Unit tests for AgentDiscoveryProviderFactory.
 *
 * Note: Environment detection tests (K8s token file presence) are covered
 * by E2E tests since the factory class is final and uses direct file checks.
 */
final class AgentDiscoveryProviderFactoryTest extends Unit
{
    public function testCreateWithNullProviderModeReturnsTraefikProvider(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create(null, $traefikProvider, $kubernetesProvider);

        $this->assertSame($traefikProvider, $result);
    }

    public function testCreateWithEmptyStringProviderModeReturnsTraefikProvider(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('', $traefikProvider, $kubernetesProvider);

        $this->assertSame($traefikProvider, $result);
    }

    public function testCreateWithWhitespaceProviderModeReturnsTraefikProvider(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('   ', $traefikProvider, $kubernetesProvider);

        $this->assertSame($traefikProvider, $result);
    }

    public function testCreateWithTraefikModeReturnsTraefikProvider(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('traefik', $traefikProvider, $kubernetesProvider);

        $this->assertSame($traefikProvider, $result);
    }

    public function testCreateWithTraefikModeCaseInsensitive(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('TRAEFIK', $traefikProvider, $kubernetesProvider);

        $this->assertSame($traefikProvider, $result);
    }

    public function testCreateWithTraefikModeWithWhitespace(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('  traefik  ', $traefikProvider, $kubernetesProvider);

        $this->assertSame($traefikProvider, $result);
    }

    public function testCreateWithKubernetesModeReturnsKubernetesProvider(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('kubernetes', $traefikProvider, $kubernetesProvider);

        $this->assertSame($kubernetesProvider, $result);
    }

    public function testCreateWithKubernetesModeCaseInsensitive(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('KUBERNETES', $traefikProvider, $kubernetesProvider);

        $this->assertSame($kubernetesProvider, $result);
    }

    public function testCreateWithKubernetesModeWithWhitespaceReturnsKubernetesProvider(): void
    {
        $factory = new AgentDiscoveryProviderFactory();
        $traefikProvider = $this->createTraefikProvider();
        $kubernetesProvider = $this->createKubernetesProvider();

        $result = $factory->create('  kubernetes  ', $traefikProvider, $kubernetesProvider);

        $this->assertSame($kubernetesProvider, $result);
    }

    private function createTraefikProvider(): TraefikDiscoveryProvider
    {
        return new TraefikDiscoveryProvider(new NullLogger());
    }

    private function createKubernetesProvider(): KubernetesDiscoveryProvider
    {
        return new KubernetesDiscoveryProvider(
            new NullLogger(),
            static fn (): string|false => false,
            static function (): array {
                return ['status' => 200, 'body' => '{"items":[]}'];
            },
        );
    }
}
