# Kubernetes Agent Discovery

## Overview

The platform supports two agent discovery strategies, selected via the `AGENT_DISCOVERY_PROVIDER`
environment variable. This document describes the provider strategy pattern, how to configure it,
and what Helm chart labels are required for Kubernetes discovery to work.

## Discovery Providers

| Provider | Env value | When to use |
|----------|-----------|-------------|
| `TraefikDiscoveryProvider` | `traefik` | Docker Compose (default) |
| `KubernetesDiscoveryProvider` | `kubernetes` | k3s / Kubernetes |
| Auto-detect | `auto` or empty | Checks for SA token; uses Kubernetes if present, Traefik otherwise |

### `AGENT_DISCOVERY_PROVIDER` values

```
AGENT_DISCOVERY_PROVIDER=traefik      # Force Traefik (Docker Compose)
AGENT_DISCOVERY_PROVIDER=kubernetes   # Force Kubernetes
AGENT_DISCOVERY_PROVIDER=auto         # Auto-detect (default in Helm chart)
```

The Helm chart sets `AGENT_DISCOVERY_PROVIDER: auto` in `values.yaml`. In Docker Compose the
variable is unset, which also triggers auto-detection — and since no SA token file exists in
Docker, the Traefik provider is selected automatically.

## Architecture

```
AgentDiscoveryService
    └── AgentDiscoveryProviderInterface
            ├── TraefikDiscoveryProvider   (Docker Compose)
            └── KubernetesDiscoveryProvider (Kubernetes)
```

`AgentDiscoveryService` is a thin wrapper that delegates to whichever provider is injected.
The provider is resolved at container boot time by `AgentDiscoveryProviderFactory` based on
the `AGENT_DISCOVERY_PROVIDER` env var.

### DI wiring (`config/services.yaml`)

```yaml
App\A2AGateway\Discovery\AgentDiscoveryProviderInterface:
    factory: ['@App\A2AGateway\Discovery\AgentDiscoveryProviderFactory', 'create']
    arguments:
        $providerMode: '%env(default::AGENT_DISCOVERY_PROVIDER)%'
```

The factory receives the two concrete providers via autowiring and returns the appropriate one.

## Kubernetes Provider

`KubernetesDiscoveryProvider` queries the Kubernetes API for Services labelled
`ai.platform.agent=true` in the same namespace as the running pod.

### How it works

1. Reads the in-cluster service account token from
   `/var/run/secrets/kubernetes.io/serviceaccount/token`
2. Reads the current namespace from
   `/var/run/secrets/kubernetes.io/serviceaccount/namespace`
3. Calls `GET https://kubernetes.default.svc/api/v1/namespaces/{namespace}/services?labelSelector=ai.platform.agent=true`
   using the SA token as a Bearer token and the in-cluster CA bundle for TLS verification
4. For each returned Service, builds `hostname` as `<service-name>.<namespace>.svc.cluster.local`
5. Selects the port named `http` if present; falls back to the first port in `spec.ports`
6. On any RBAC or network failure, logs a warning and returns an empty list (no exception thrown)

### Required RBAC

The core ServiceAccount must have permission to list Services in its namespace. The Helm chart
creates this automatically via `templates/core/rbac.yaml`:

```yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: <release>-core-discovery
rules:
  - apiGroups: [""]
    resources: ["services"]
    verbs: ["list"]
```

A `RoleBinding` binds this Role to the core ServiceAccount in the same namespace.

## Helm Chart Labels

For Kubernetes discovery to find an agent, its Service must carry the `ai.platform.agent: "true"`
label. The Helm chart applies this automatically to all enabled agents.

### Service labels (`templates/agents/service.yaml`)

```yaml
labels:
  ai.platform.agent: "true"
  ai.platform.agent-name: <agentKey>-agent
```

### Pod template labels (`templates/agents/deployment.yaml`)

```yaml
labels:
  ai.platform.agent: "true"
```

The `ai.platform.agent-name` label is present on Services only (used for identification).
The pod label is informational and may be used for future filtering.

## Adding a New Agent

When onboarding a new agent to the Helm chart, ensure:

1. The agent is defined under `agents:` in `values.yaml` with `enabled: true`
2. The agent Service exposes a port named `http` (the Kubernetes provider prefers this name)
3. No additional label configuration is needed — the chart templates apply the labels automatically

For Docker Compose agents, the existing `ai.platform.agent=true` Docker label on the container
is sufficient for Traefik-based discovery.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| No agents discovered in Kubernetes | Missing RBAC | Check `kubectl get role,rolebinding -n <ns>` |
| No agents discovered in Kubernetes | Missing label | Verify `kubectl get svc -l ai.platform.agent=true -n <ns>` |
| Discovery falls back to Traefik in Kubernetes | SA token not mounted | Check pod spec `serviceAccountName` |
| Warning: `kubernetes_credentials_missing` in logs | SA token/namespace file missing | Ensure pod runs with a ServiceAccount |

Logs use structured fields with `event_name` keys:

| `event_name` | Meaning |
|---|---|
| `core.discovery.kubernetes_started` | Discovery run started |
| `core.discovery.kubernetes_credentials_missing` | SA token or namespace file not found |
| `core.discovery.kubernetes_credentials_loaded` | Credentials read successfully |
| `core.discovery.kubernetes_request_started` | API call initiated |
| `core.discovery.kubernetes_response_received` | API responded |
| `core.discovery.kubernetes_http_error` | Non-2xx response (e.g. 403 RBAC denied) |
| `core.discovery.kubernetes_unreachable` | Network/connection error |
| `core.discovery.kubernetes_invalid_json` | Malformed API response |
