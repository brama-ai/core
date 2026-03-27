# Tasks: Add Kubernetes-native agent discovery

## 1. Helm Chart — Labels

- [ ] 1.1 Add `ai.platform.agent: "true"` and `ai.platform.agent-name: {{ $agentKey | lower }}-agent` labels to `templates/agents/service.yaml`
- [ ] 1.2 Add `ai.platform.agent: "true"` to pod template labels in `templates/agents/deployment.yaml`
- [ ] 1.3 Verify: `helm template` renders labels on all agent Services and Deployments

## 2. RBAC

- [ ] 2.1 Create `templates/core/rbac.yaml` with Role + RoleBinding granting `list services` to the core ServiceAccount
- [ ] 2.2 Verify: `kubectl auth can-i list services --as=system:serviceaccount:<ns>:<sa>` returns `yes` after install

## 3. PHP — Provider Interface

- [ ] 3.1 Create `src/A2AGateway/Discovery/AgentDiscoveryProviderInterface.php` with `discover(): array` returning `list<array{hostname: string, port: int}>`
- [ ] 3.2 Create unit test `src/tests/Unit/A2AGateway/Discovery/AgentDiscoveryProviderInterfaceTest.php` (contract test)

## 4. PHP — TraefikDiscoveryProvider

- [ ] 4.1 Extract Traefik discovery logic from `AgentDiscoveryService` into `src/A2AGateway/Discovery/TraefikDiscoveryProvider.php`
- [ ] 4.2 Implement `AgentDiscoveryProviderInterface`; no behavior change from current logic
- [ ] 4.3 Add unit test `src/tests/Unit/A2AGateway/Discovery/TraefikDiscoveryProviderTest.php`

## 5. PHP — KubernetesDiscoveryProvider

- [ ] 5.1 Create `src/A2AGateway/Discovery/KubernetesDiscoveryProvider.php`
- [ ] 5.2 Query `GET /api/v1/namespaces/{namespace}/services?labelSelector=ai.platform.agent=true` using in-cluster SA token and CA bundle
- [ ] 5.3 Read namespace from `/var/run/secrets/kubernetes.io/serviceaccount/namespace`
- [ ] 5.4 Build `hostname` as `<service-name>.<namespace>.svc.cluster.local`, `port` from `spec.ports[http]`
- [ ] 5.5 Log clear warning on RBAC/network failure; return empty list (do not throw)
- [ ] 5.6 Add unit test `src/tests/Unit/A2AGateway/Discovery/KubernetesDiscoveryProviderTest.php`

## 6. PHP — AgentDiscoveryService refactor

- [ ] 6.1 Refactor `AgentDiscoveryService::discoverAgents()` to delegate to `AgentDiscoveryProviderInterface`
- [ ] 6.2 Inject provider via constructor; keep all downstream consumers (`AgentDiscoveryCommand`, health poller) unchanged
- [ ] 6.3 Update unit tests for `AgentDiscoveryService` to use mock provider

## 7. DI — services.yaml

- [ ] 7.1 Add `AGENT_DISCOVERY_PROVIDER: auto` to `config/services.yaml` (and `values.yaml`)
- [ ] 7.2 Wire provider selection: `auto` → check SA token file; `traefik` → `TraefikDiscoveryProvider`; `kubernetes` → `KubernetesDiscoveryProvider`
- [ ] 7.3 Verify: switching `AGENT_DISCOVERY_PROVIDER=traefik` in Docker Compose still works

## 8. Quality Checks

- [ ] 8.1 `phpstan analyse` — zero errors at level 8
- [ ] 8.2 `php-cs-fixer check` — no style violations
- [ ] 8.3 Unit test suite passes (all new Discovery tests green)
- [ ] 8.4 Validate OpenSpec: `openspec validate add-kubernetes-agent-discovery --strict`
