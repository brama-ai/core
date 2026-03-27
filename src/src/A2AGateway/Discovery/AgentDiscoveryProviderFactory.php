<?php

declare(strict_types=1);

namespace App\A2AGateway\Discovery;

final class AgentDiscoveryProviderFactory
{
    public function create(
        ?string $providerMode,
        TraefikDiscoveryProvider $traefikProvider,
        KubernetesDiscoveryProvider $kubernetesProvider,
    ): AgentDiscoveryProviderInterface {
        $mode = strtolower(trim($providerMode ?? ''));

        if ('kubernetes' === $mode) {
            return $kubernetesProvider;
        }

        if ('traefik' === $mode) {
            return $traefikProvider;
        }

        if (is_file(KubernetesDiscoveryProvider::SERVICE_ACCOUNT_TOKEN_PATH)) {
            return $kubernetesProvider;
        }

        return $traefikProvider;
    }
}
