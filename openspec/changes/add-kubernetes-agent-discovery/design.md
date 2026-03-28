## Context

The platform's agent discovery mechanism was tightly coupled to Docker Compose via Traefik's
Docker provider. As the platform migrates to k3s/Kubernetes, discovery must work in both
runtimes without changing downstream consumers (manifest fetch, convention verify, registry upsert).

This is a cross-cutting change affecting PHP services, Symfony DI configuration, and Helm chart
templates. The design introduces a strategy pattern for discovery providers.

## Goals / Non-Goals

- **Goals:**
  - Support agent discovery in both Docker Compose (Traefik) and Kubernetes (label-based) runtimes
  - Zero breaking changes to existing Docker Compose deployments
  - Auto-detection of runtime environment when `AGENT_DISCOVERY_PROVIDER=auto`
  - Graceful degradation when Kubernetes API is unreachable or RBAC is misconfigured
  - Proper RBAC for core ServiceAccount to list Services in the release namespace

- **Non-Goals:**
  - Cross-namespace agent discovery (all agents in same namespace for now)
  - Kubernetes Ingress/IngressRoute-based discovery
  - Agent auto-registration via admission webhooks
  - Changes to downstream consumers (AgentCardFetcher, AgentConventionVerifier, AgentRegistryRepository)

## Decisions

### Strategy Pattern for Discovery Providers

**Decision:** Introduce `AgentDiscoveryProviderInterface` with two implementations
(`TraefikDiscoveryProvider`, `KubernetesDiscoveryProvider`) and a factory for selection.

**Alternatives considered:**
- **Single class with conditional logic:** Rejected — violates SRP, harder to test, grows with
  each new runtime.
- **Symfony tagged services with priority:** Rejected — over-engineered for two providers;
  factory is simpler and explicit.
- **Environment-specific service definitions:** Rejected — requires separate `services_k8s.yaml`
  and complicates DI.

### Label-Based Kubernetes Discovery

**Decision:** Use `ai.platform.agent=true` label on Kubernetes Services rather than
Ingress/IngressRoute annotations.

**Rationale:**
- Services are the natural unit for internal service-to-service communication
- Labels are simpler to query than Ingress annotations
- No dependency on Traefik CRDs or Ingress controller configuration
- Hostname is deterministic: `<service-name>.<namespace>.svc.cluster.local`

### Auto-Detection via Service Account Token

**Decision:** When `AGENT_DISCOVERY_PROVIDER=auto`, check for the presence of
`/var/run/secrets/kubernetes.io/serviceaccount/token` to determine runtime.

**Rationale:**
- This file is always present in Kubernetes pods with a ServiceAccount
- It is never present in Docker Compose containers
- Simple, reliable, no external dependencies

### Testability via Callable Injection

**Decision:** Both providers accept optional `callable` parameters for file reading and HTTP
requests, enabling pure unit tests without filesystem or network mocking.

**Rationale:**
- Avoids complex mock setups for `file_get_contents` and stream contexts
- Tests are fast, deterministic, and self-contained
- Production code uses default callables that perform real I/O

## Component Interactions

```
AgentDiscoveryService
  └── AgentDiscoveryProviderInterface (injected via DI)
        ├── TraefikDiscoveryProvider (Docker Compose)
        │     └── HTTP GET http://traefik:8080/api/http/services
        └── KubernetesDiscoveryProvider (k3s/Kubernetes)
              └── HTTP GET https://kubernetes.default.svc/api/v1/namespaces/{ns}/services?labelSelector=...
                    └── Uses in-cluster SA token + CA bundle

AgentDiscoveryProviderFactory::create()
  └── Reads AGENT_DISCOVERY_PROVIDER env var
  └── Auto-detects via SA token file presence
  └── Returns appropriate provider instance

Helm Chart
  └── templates/agents/service.yaml → ai.platform.agent labels
  └── templates/agents/deployment.yaml → ai.platform.agent pod labels
  └── templates/core/rbac.yaml → Role + RoleBinding for service listing
  └── values.yaml → AGENT_DISCOVERY_PROVIDER: auto
```

## Risks / Trade-offs

- **RBAC misconfiguration** → Kubernetes discovery returns empty list, agents appear unavailable.
  Mitigation: clear warning logs with structured event names.
- **Namespace scope limitation** → Only same-namespace agents discovered. Acceptable for current
  single-namespace Helm release model.
- **Auto-detection false positive** → If a Docker Compose container somehow has a SA token file,
  it would incorrectly use Kubernetes provider. Extremely unlikely; explicit `traefik` mode
  available as override.

## Open Questions

None — all design decisions are implemented and merged.
