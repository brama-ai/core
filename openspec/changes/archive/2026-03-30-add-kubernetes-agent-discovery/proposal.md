# Change: Add Kubernetes-native agent discovery to AgentDiscoveryService

## Status

**PHP implementation merged. Completion verification in progress.**

All provider classes, factory, DI wiring, Helm chart labels, RBAC manifests, and core unit tests
are in the codebase. This proposal now tracks the **remaining verification work**: run and confirm
unit tests for all discovery providers, validate Helm chart rendering and labels, verify DI wiring
compiles correctly, run PHPStan and CS Fixer, and update documentation.

## Why

The current agent discovery mechanism (`AgentDiscoveryService`) works **exclusively in Docker Compose**:
it queries the Traefik API (`http://traefik:8080/api/http/services`) and filters services matching
the pattern `*-agent@docker`. This pattern relies on Traefik's Docker provider, which reads container
labels from the Docker socket — a mechanism that does not exist in Kubernetes.

When the platform runs on k3s/Kubernetes, Traefik uses the Kubernetes Ingress/IngressRoute provider
instead of the Docker provider, so the `@docker` suffix never appears and agents are invisible to
the current discovery logic. The platform needs an **environment-aware discovery strategy** that works
in both runtimes without duplicating the downstream pipeline (manifest fetch -> convention verify ->
registry upsert).

## Current State (Post-Merge)

### Implemented PHP classes
| File | Status |
|------|--------|
| `src/A2AGateway/Discovery/AgentDiscoveryProviderInterface.php` | Merged |
| `src/A2AGateway/Discovery/TraefikDiscoveryProvider.php` | Merged |
| `src/A2AGateway/Discovery/KubernetesDiscoveryProvider.php` | Merged |
| `src/A2AGateway/Discovery/AgentDiscoveryProviderFactory.php` | Merged |
| `src/A2AGateway/AgentDiscoveryService.php` (refactored) | Merged |

### Implemented Helm chart
| File | Status |
|------|--------|
| `templates/agents/service.yaml` — `ai.platform.agent` labels | Merged |
| `templates/agents/deployment.yaml` — pod labels | Merged |
| `templates/core/rbac.yaml` — Role + RoleBinding | Merged |
| `values.yaml` — `AGENT_DISCOVERY_PROVIDER: auto` | Merged |

### Implemented DI wiring
| File | Status |
|------|--------|
| `config/services.yaml` — factory-based provider resolution | Merged |

### Implemented tests
| File | Status |
|------|--------|
| `tests/Unit/A2AGateway/Discovery/KubernetesDiscoveryProviderTest.php` (4 tests) | Merged |
| `tests/Unit/A2AGateway/Discovery/TraefikDiscoveryProviderTest.php` (5 tests) | Merged |
| `tests/Unit/A2AGateway/Discovery/AgentDiscoveryProviderFactoryTest.php` (9 tests) | Merged |

## What Changes (Architecture Summary)

### 1. `AgentDiscoveryProviderInterface`
A strategy interface that both providers implement:
```php
interface AgentDiscoveryProviderInterface
{
    /** @return list<array{hostname: string, port: int}> */
    public function discover(): array;
}
```

### 2. `TraefikDiscoveryProvider` (extracted from existing code)
Queries Traefik API at `http://traefik:8080/api/http/services`, filters `*-agent@docker` services.
Pure refactor — no behavior change from original `AgentDiscoveryService`.

### 3. `KubernetesDiscoveryProvider`
Queries Kubernetes API for Services with label `ai.platform.agent=true`:
- Uses in-cluster service account token and CA bundle
- Reads namespace from `/var/run/secrets/kubernetes.io/serviceaccount/namespace`
- Builds `hostname` as `<service-name>.<namespace>.svc.cluster.local`
- Prefers port named `http`; falls back to first port
- Logs clear warning on RBAC/network failure; returns empty list (no throw)

### 4. `AgentDiscoveryProviderFactory`
Resolves provider based on `AGENT_DISCOVERY_PROVIDER` env var:

| Value       | Provider                    | Default when          |
|-------------|-----------------------------|-----------------------|
| `traefik`   | `TraefikDiscoveryProvider`  | Docker Compose        |
| `kubernetes`| `KubernetesDiscoveryProvider`| k3s / Kubernetes     |
| `auto`/empty| Auto-detect (check for SA token) | —              |

### 5. `AgentDiscoveryService` refactored
Now a thin wrapper delegating to `AgentDiscoveryProviderInterface`. All downstream consumers
(`AgentDiscoveryCommand`, health poller, admin controller) remain unchanged.

### 6. Helm chart labels + RBAC
- Agent Services get `ai.platform.agent: "true"` and `ai.platform.agent-name` labels
- Agent Deployment pod templates get `ai.platform.agent: "true"` label
- Core ServiceAccount gets `list services` permission via Role + RoleBinding

## Remaining Work (Verification Phase)

The following tasks are tracked in `tasks.md`:

1. **Unit test execution** — Run and confirm all 18 tests across KubernetesDiscoveryProviderTest,
   TraefikDiscoveryProviderTest, and AgentDiscoveryProviderFactoryTest pass green
2. **Helm chart validation** — `helm template` rendering confirms `ai.platform.agent` labels on
   all agent Services and Deployments; `helm lint` passes
3. **DI wiring verification** — Symfony container compiles with `AGENT_DISCOVERY_PROVIDER=auto`;
   factory-based provider resolution works
4. **Static analysis** — PHPStan level 8 passes with 0 errors on all discovery files
5. **Code style** — PHP CS Fixer reports 0 violations on all discovery files
6. **Documentation** — Developer docs for discovery architecture

## Impact

- **Affected specs:** `a2a-server` (MODIFIED — discovery now uses provider strategy pattern)
- **Affected code:**
  - `src/A2AGateway/AgentDiscoveryService.php` — refactored to use provider interface
  - NEW `src/A2AGateway/Discovery/AgentDiscoveryProviderInterface.php`
  - NEW `src/A2AGateway/Discovery/TraefikDiscoveryProvider.php`
  - NEW `src/A2AGateway/Discovery/KubernetesDiscoveryProvider.php`
  - NEW `src/A2AGateway/Discovery/AgentDiscoveryProviderFactory.php`
  - `config/services.yaml` — factory-based provider wiring
- **Affected Helm chart:**
  - `templates/agents/service.yaml` — `ai.platform.agent` label
  - `templates/agents/deployment.yaml` — `ai.platform.agent` pod label
  - NEW `templates/core/rbac.yaml` — Service list permission
  - `values.yaml` — `core.env.AGENT_DISCOVERY_PROVIDER: auto`
- **Affected Docker Compose:** None — `ai.platform.agent=true` label already present
- **No breaking changes:** Docker Compose discovery continues to work identically

## Risks

- **RBAC misconfiguration** — if the ServiceAccount lacks `list services` permission, Kubernetes
  discovery will return empty. Mitigation: the provider logs a clear warning and the health poller
  will report agents as unavailable.
- **Namespace scope** — discovery only finds agents in the same namespace. Cross-namespace agents
  would require a ClusterRole. For now, same-namespace is sufficient since all agents are deployed
  in the same Helm release.

## Out of Scope

- Cross-namespace agent discovery
- Kubernetes Ingress/IngressRoute-based discovery (too complex, labels on Services are simpler)
- Agent auto-registration via Kubernetes admission webhooks
- Changes to AgentCardFetcher, AgentConventionVerifier, or AgentRegistryRepository
