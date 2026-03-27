# Change: Add Kubernetes-native agent discovery to AgentDiscoveryService

## Why

The current agent discovery mechanism (`AgentDiscoveryService`) works **exclusively in Docker Compose**:
it queries the Traefik API (`http://traefik:8080/api/http/services`) and filters services matching
the pattern `*-agent@docker`. This pattern relies on Traefik's Docker provider, which reads container
labels from the Docker socket — a mechanism that does not exist in Kubernetes.

When the platform runs on k3s/Kubernetes, Traefik uses the Kubernetes Ingress/IngressRoute provider
instead of the Docker provider, so the `@docker` suffix never appears and agents are invisible to
the current discovery logic. The platform needs an **environment-aware discovery strategy** that works
in both runtimes without duplicating the downstream pipeline (manifest fetch → convention verify →
registry upsert).

## Current State

### Docker Compose flow
1. Each agent's `compose.agent-*.yaml` declares Traefik labels:
   - `traefik.enable=true`
   - `traefik.http.services.<name>.loadbalancer.server.port=<port>`
   - `ai.platform.agent=true` (present but unused by discovery)
2. `AgentDiscoveryService::discoverAgents()` calls Traefik API, filters `*-agent@docker` services
3. Extracts hostname + port from loadBalancer server URLs
4. Returns `list<array{hostname, port}>` → consumed by `AgentDiscoveryCommand`

### Kubernetes Helm chart (current)
- Agents are deployed via `templates/agents/deployment.yaml` + `templates/agents/service.yaml`
- Each agent gets label `app.kubernetes.io/component: agent-<key>`
- Services are ClusterIP with named port `http`
- **No discovery label** and **no discovery mechanism** — agents must be manually registered

## What Changes

### 1. Add `ai.platform.agent` label to Kubernetes Service manifests

In `templates/agents/service.yaml`, add a discovery label to every agent Service:

```yaml
metadata:
  labels:
    ai.platform.agent: "true"
    ai.platform.agent-name: {{ $agentKey | lower }}-agent
```

And in `templates/agents/deployment.yaml` pod template labels:

```yaml
labels:
  ai.platform.agent: "true"
```

### 2. Introduce `AgentDiscoveryProviderInterface`

Create an interface that both providers implement:

```php
interface AgentDiscoveryProviderInterface
{
    /** @return list<array{hostname: string, port: int}> */
    public function discover(): array;
}
```

### 3. Implement `TraefikDiscoveryProvider` (extract from current code)

Move existing Traefik API logic into a dedicated provider class. No behavior change — this is
a pure refactor of `AgentDiscoveryService::discoverAgents()`.

### 4. Implement `KubernetesDiscoveryProvider`

New provider that queries the Kubernetes API for Services with label `ai.platform.agent=true`:

```
GET /api/v1/namespaces/{namespace}/services?labelSelector=ai.platform.agent=true
```

- Uses the **in-cluster service account token** (`/var/run/secrets/kubernetes.io/serviceaccount/token`)
- Namespace read from `/var/run/secrets/kubernetes.io/serviceaccount/namespace`
- For each matching Service, extracts:
  - `hostname` = `<service-name>.<namespace>.svc.cluster.local`
  - `port` = first port from `spec.ports[]` (or the port named `http`)
- No external dependencies — uses PHP's native HTTP client with the cluster CA bundle

### 5. Add environment switch via `AGENT_DISCOVERY_PROVIDER` env var

| Value       | Provider                    | Default when          |
|-------------|-----------------------------|-----------------------|
| `traefik`   | `TraefikDiscoveryProvider`  | Docker Compose        |
| `kubernetes`| `KubernetesDiscoveryProvider`| k3s / Kubernetes     |
| `auto`      | Auto-detect (check for SA token) | —              |

Default: `auto` — if `/var/run/secrets/kubernetes.io/serviceaccount/token` exists, use Kubernetes
provider; otherwise fall back to Traefik.

### 6. Refactor `AgentDiscoveryService` to delegate

`AgentDiscoveryService::discoverAgents()` becomes a thin wrapper that delegates to the resolved
provider. All downstream consumers (command, admin controller, health poller) remain unchanged.

### 7. RBAC: ServiceAccount permissions

The core deployment's ServiceAccount needs permission to list Services in its namespace:

```yaml
# templates/core/rbac.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: {{ include "acp.fullname" . }}-core-discovery
rules:
  - apiGroups: [""]
    resources: ["services"]
    verbs: ["list"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: {{ include "acp.fullname" . }}-core-discovery
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: Role
  name: {{ include "acp.fullname" . }}-core-discovery
subjects:
  - kind: ServiceAccount
    name: {{ include "acp.serviceAccountName" . }}
```

## Impact

- **Affected code:**
  - `src/A2AGateway/AgentDiscoveryService.php` — refactor to use provider interface
  - NEW `src/A2AGateway/Discovery/AgentDiscoveryProviderInterface.php`
  - NEW `src/A2AGateway/Discovery/TraefikDiscoveryProvider.php`
  - NEW `src/A2AGateway/Discovery/KubernetesDiscoveryProvider.php`
  - `config/services.yaml` — wire provider based on env var
- **Affected Helm chart:**
  - `templates/agents/service.yaml` — add `ai.platform.agent` label
  - `templates/agents/deployment.yaml` — add `ai.platform.agent` pod label
  - NEW `templates/core/rbac.yaml` — Service list permission
  - `values.yaml` — add `core.env.AGENT_DISCOVERY_PROVIDER: auto`
- **Affected Docker Compose:**
  - None — `ai.platform.agent=true` label already present on agent services
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
