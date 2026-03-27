# Tasks: Add Kubernetes-native agent discovery

## 1. Helm Chart — Labels

- [x] 1.1 Add `ai.platform.agent: "true"` and `ai.platform.agent-name: {{ $agentKey | lower }}-agent` labels to `templates/agents/service.yaml`
- [x] 1.2 Add `ai.platform.agent: "true"` to pod template labels in `templates/agents/deployment.yaml`
- [x] 1.3 Verify: `helm template` renders labels on all agent Services and Deployments

## 2. RBAC

- [x] 2.1 Create `templates/core/rbac.yaml` with Role + RoleBinding granting `list services` to the core ServiceAccount
- [x] 2.2 Verify: `kubectl auth can-i list services --as=system:serviceaccount:<ns>:<sa>` returns `yes` after install

## 3. PHP — Provider Interface

- [x] 3.1 Create `src/A2AGateway/Discovery/AgentDiscoveryProviderInterface.php` with `discover(): array` returning `list<array{hostname: string, port: int}>`
- [x] 3.2 Contract verified via `TraefikDiscoveryProviderTest` and `KubernetesDiscoveryProviderTest`

## 4. PHP — TraefikDiscoveryProvider

- [x] 4.1 Extract Traefik discovery logic into `src/A2AGateway/Discovery/TraefikDiscoveryProvider.php`
- [x] 4.2 Implements `AgentDiscoveryProviderInterface`; no behavior change from original logic
- [x] 4.3 Add unit test `src/tests/Unit/A2AGateway/Discovery/TraefikDiscoveryProviderTest.php` (5 cases)

## 5. PHP — KubernetesDiscoveryProvider

- [x] 5.1 Create `src/A2AGateway/Discovery/KubernetesDiscoveryProvider.php`
- [x] 5.2 Query `GET /api/v1/namespaces/{namespace}/services?labelSelector=ai.platform.agent=true` using in-cluster SA token and CA bundle
- [x] 5.3 Read namespace from `/var/run/secrets/kubernetes.io/serviceaccount/namespace`
- [x] 5.4 Build `hostname` as `<service-name>.<namespace>.svc.cluster.local`, `port` from `spec.ports[http]`
- [x] 5.5 Log clear warning on RBAC/network failure; return empty list (do not throw)
- [x] 5.6 Add unit test `src/tests/Unit/A2AGateway/Discovery/KubernetesDiscoveryProviderTest.php` (4 cases)

## 6. PHP — AgentDiscoveryService refactor

- [x] 6.1 `AgentDiscoveryService::discoverAgents()` delegates to `AgentDiscoveryProviderInterface`
- [x] 6.2 Provider injected via constructor; downstream consumers unchanged
- [x] 6.3 Factory tests cover provider selection logic (`AgentDiscoveryProviderFactoryTest`)

## 7. DI — services.yaml

- [x] 7.1 `AGENT_DISCOVERY_PROVIDER: auto` in `config/services.yaml` and `values.yaml`
- [x] 7.2 Provider selection: `auto` → check SA token; `traefik` → TraefikDiscoveryProvider; `kubernetes` → KubernetesDiscoveryProvider
- [x] 7.3 Docker Compose still uses `traefik` mode (default for non-k8s envs)

## 8. Quality Checks

- [x] 8.1 `phpstan analyse` — 0 errors at level 8
- [x] 8.2 `php-cs-fixer check` — 0 violations
- [x] 8.3 Unit test suite: **18 tests, 28 assertions** — all green
- [x] 8.4 OpenSpec validated (all tasks implemented)
