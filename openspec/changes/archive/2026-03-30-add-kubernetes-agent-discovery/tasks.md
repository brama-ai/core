# Tasks: Add Kubernetes-native agent discovery

## Phase A — Implementation (Completed)

### 1. Helm Chart — Labels

- [x] 1.1 Add `ai.platform.agent: "true"` and `ai.platform.agent-name: {{ $agentKey | lower }}-agent` labels to `templates/agents/service.yaml`
- [x] 1.2 Add `ai.platform.agent: "true"` to pod template labels in `templates/agents/deployment.yaml`

### 2. RBAC

- [x] 2.1 Create `templates/core/rbac.yaml` with Role + RoleBinding granting `list services` to the core ServiceAccount

### 3. PHP — Provider Interface

- [x] 3.1 Create `src/A2AGateway/Discovery/AgentDiscoveryProviderInterface.php` with `discover(): array` returning `list<array{hostname: string, port: int}>`

### 4. PHP — TraefikDiscoveryProvider

- [x] 4.1 Extract Traefik discovery logic into `src/A2AGateway/Discovery/TraefikDiscoveryProvider.php`
- [x] 4.2 Implements `AgentDiscoveryProviderInterface`; no behavior change from original logic

### 5. PHP — KubernetesDiscoveryProvider

- [x] 5.1 Create `src/A2AGateway/Discovery/KubernetesDiscoveryProvider.php`
- [x] 5.2 Query `GET /api/v1/namespaces/{namespace}/services?labelSelector=ai.platform.agent=true` using in-cluster SA token and CA bundle
- [x] 5.3 Read namespace from `/var/run/secrets/kubernetes.io/serviceaccount/namespace`
- [x] 5.4 Build `hostname` as `<service-name>.<namespace>.svc.cluster.local`, `port` from `spec.ports[http]`
- [x] 5.5 Log clear warning on RBAC/network failure; return empty list (do not throw)

### 6. PHP — AgentDiscoveryProviderFactory

- [x] 6.1 Create `src/A2AGateway/Discovery/AgentDiscoveryProviderFactory.php`
- [x] 6.2 Resolve provider based on `AGENT_DISCOVERY_PROVIDER` env var: `traefik`, `kubernetes`, `auto`

### 7. PHP — AgentDiscoveryService refactor

- [x] 7.1 `AgentDiscoveryService::discoverAgents()` delegates to `AgentDiscoveryProviderInterface`
- [x] 7.2 Provider injected via constructor; downstream consumers unchanged

### 8. DI — services.yaml

- [x] 8.1 Factory-based provider resolution in `config/services.yaml`
- [x] 8.2 `AGENT_DISCOVERY_PROVIDER: auto` in `values.yaml`

### 9. Unit Tests — Implementation

- [x] 9.1 `KubernetesDiscoveryProviderTest.php` — 4 tests
- [x] 9.2 `TraefikDiscoveryProviderTest.php` — 5 tests
- [x] 9.3 `AgentDiscoveryProviderFactoryTest.php` — 9 tests

---

## Phase B — Verification & Completion (Remaining)

### 10. Unit Tests — KubernetesDiscoveryProvider

- [x] 10.1 Run `KubernetesDiscoveryProviderTest` — confirm all 4 tests pass
- [x] 10.2 Verify: `testDiscoverReturnsAgentServicesFromKubernetesApi` — correct hostname format `<name>.<ns>.svc.cluster.local` and port extraction
- [x] 10.3 Verify: `testDiscoverReturnsEmptyWhenCredentialsAreMissing` — no API call when SA token/namespace missing
- [x] 10.4 Verify: `testDiscoverReturnsEmptyForNonSuccessStatusCode` — graceful handling of 403/500 responses
- [x] 10.5 Verify: `testDiscoverPrefersHttpNamedPort` — `http` named port takes priority over other ports

### 11. Unit Tests — TraefikDiscoveryProvider

- [x] 11.1 Run `TraefikDiscoveryProviderTest` — confirm all 5 tests pass
- [x] 11.2 Verify: `testDiscoverReturnsAgentServicesFromTraefikApi` — filters `*-agent@docker` services, extracts hostname and port
- [x] 11.3 Verify: `testDiscoverReturnsEmptyWhenTraefikUnreachable` — returns empty on connection failure
- [x] 11.4 Verify: `testDiscoverReturnsEmptyOnInvalidJson` — handles malformed JSON gracefully
- [x] 11.5 Verify: `testDiscoverFallsBackToPortFromServerStatus` — uses `serverStatus` URL when `loadBalancer.servers` is empty
- [x] 11.6 Verify: `testDiscoverDefaultsToPort80WhenNoPortFound` — defaults to port 80

### 12. Unit Tests — AgentDiscoveryProviderFactory

- [x] 12.1 Run `AgentDiscoveryProviderFactoryTest` — confirm all 9 tests pass
- [x] 12.2 Verify: null/empty/whitespace mode returns Traefik provider (3 tests)
- [x] 12.3 Verify: `traefik` mode (case-insensitive, with whitespace) returns Traefik provider (3 tests)
- [x] 12.4 Verify: `kubernetes` mode (case-insensitive, with whitespace) returns Kubernetes provider (3 tests)

### 13. Helm Chart Validation

- [x] 13.1 Run `helm template` on `deploy/charts/brama/` — confirm it renders without errors
- [x] 13.2 Verify: agent Service manifests include `ai.platform.agent: "true"` label
- [x] 13.3 Verify: agent Service manifests include `ai.platform.agent-name: <key>-agent` label
- [x] 13.4 Verify: agent Deployment pod templates include `ai.platform.agent: "true"` label
- [x] 13.5 Run `helm lint deploy/charts/brama/` — confirm 0 errors
- [x] 13.6 Verify: `templates/core/rbac.yaml` renders Role with `list services` verb and RoleBinding to core ServiceAccount

### 14. DI Wiring Verification

- [x] 14.1 Verify `config/services.yaml` defines `AgentDiscoveryProviderInterface` with factory `AgentDiscoveryProviderFactory::create`
- [x] 14.2 Verify factory receives `$providerMode` from `%env(default::AGENT_DISCOVERY_PROVIDER)%`
- [x] 14.3 Run Symfony container compilation check (`bin/console debug:container AgentDiscoveryProviderInterface` or lint) — confirm no wiring errors

### 15. Static Analysis

- [x] 15.1 Run `phpstan analyse` on `src/A2AGateway/Discovery/` — 0 errors at level 8
- [x] 15.2 Run `phpstan analyse` on `src/A2AGateway/AgentDiscoveryService.php` — 0 errors at level 8
- [x] 15.3 Run `phpstan analyse` on `tests/Unit/A2AGateway/Discovery/` — 0 errors at level 8

### 16. Code Style

- [x] 16.1 Run `php-cs-fixer check` on `src/A2AGateway/Discovery/` — 0 violations
- [x] 16.2 Run `php-cs-fixer check` on `tests/Unit/A2AGateway/Discovery/` — 0 violations

### 17. Documentation

- [x] 17.1 Update or create `docs/guides/discovery/en/kubernetes-discovery.md` — developer docs for the provider strategy pattern, env var config, and Helm chart labels
- [x] 17.2 Update `docs/agent-requirements/conventions.md` if Kubernetes discovery changes agent onboarding conventions

### 18. OpenSpec Validation

- [x] 18.1 Run `openspec validate add-kubernetes-agent-discovery --strict` — 0 errors
